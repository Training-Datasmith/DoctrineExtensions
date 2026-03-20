<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable\Document\Repository;

use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Doctrine\ODM\Mongo_Db\Repository\Document_Repository;
use Gedmo\Exception\RuntimeException;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Loggable\Document\Log_Entry;
use Gedmo\Loggable\Loggable;
use Gedmo\Loggable\Loggable_Listener;
use Gedmo\Tool\Wrapper\Mongo_Document_Wrapper;
/**
 * The LogEntryRepository has some useful functions
 * to interact with log entries.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @phpstan-template T of Loggable|object
 *
 * @phpstan-extends DocumentRepository<T>
 */
class Log_Entry_Repository extends Document_Repository
{
    /**
     * Currently used loggable listener
     *
     * @var LoggableListener<T>|null
     */
    private ?Loggable_Listener $listener = null;
    /**
     * Loads all log entries for the
     * given $document
     *
     * @param object $document
     *
     * @phpstan-param T $document
     *
     * @return LogEntry[]
     *
     * @phpstan-return array<array-key, LogEntry<T>>
     */
    public function get_log_entries($document)
    {
        $wrapped = new Mongo_Document_Wrapper($document, $this->dm);
        $object_id = $wrapped->get_identifier();
        $qb = $this->create_query_builder();
        $qb->field('objectId')->equals($object_id);
        $qb->field('objectClass')->equals($wrapped->get_metadata()->get_name());
        $qb->sort('version', 'DESC');
        return $qb->get_query()->getIterator()->to_array();
    }
    /**
     * Reverts given $document to $revision by
     * restoring all fields from that $revision.
     * After this operation you will need to
     * persist and flush the $document.
     *
     * @param object $document
     * @param int    $version
     *
     * @phpstan-param T $document
     *
     * @throws UnexpectedValueException
     */
    public function revert($document, $version = 1): void
    {
        $wrapped = new Mongo_Document_Wrapper($document, $this->dm);
        $object_meta = $wrapped->get_metadata();
        $object_id = $wrapped->get_identifier();
        $qb = $this->create_query_builder();
        $qb->field('objectId')->equals($object_id);
        $qb->field('objectClass')->equals($object_meta->get_name());
        $qb->field('version')->lte((int) $version);
        $qb->sort('version', 'ASC');
        $logs = $qb->get_query()->getIterator()->to_array();
        if ([] === $logs) {
            throw new UnexpectedValueException('Count not find any log entries under version: ' . $version);
        }
        $data = [[]];
        while ($log = array_shift($logs)) {
            $data[] = $log->get_data();
        }
        $data = array_merge(...$data);
        $this->fill_document($document, $data);
    }
    /**
     * Fills a documents versioned fields with data
     *
     * @param object               $document
     * @param array<string, mixed> $data
     *
     * @phpstan-param T $document
     *
     * @return void
     */
    protected function fill_document($document, array $data)
    {
        $wrapped = new Mongo_Document_Wrapper($document, $this->dm);
        $object_meta = $wrapped->get_metadata();
        assert($object_meta instanceof Class_Metadata);
        $config = $this->get_loggable_listener()->get_configuration($this->dm, $object_meta->get_name());
        $fields = $config['versioned'];
        foreach ($data as $field => $value) {
            if (!in_array($field, $fields, true)) {
                continue;
            }
            $mapping = $object_meta->get_field_mapping($field);
            // Fill the embedded document
            if ($wrapped->is_embedded_association($field)) {
                if (!empty($value)) {
                    assert(class_exists($mapping['targetDocument']));
                    $embedded_metadata = $this->dm->get_class_metadata($mapping['targetDocument']);
                    $document = $embedded_metadata->new_instance();
                    $this->fill_document($document, $value);
                    $value = $document;
                }
            } elseif ($object_meta->is_single_valued_association($field)) {
                assert(class_exists($mapping['targetDocument']));
                $value = $value ? $this->dm->get_reference($mapping['targetDocument'], $value) : null;
            }
            $wrapped->set_property_value($field, $value);
            unset($fields[$field]);
        }
        /*
        if (count($fields)) {
            throw new \Gedmo\Exception\UnexpectedValueException('Cound not fully revert the document to version: '.$version);
        }
        */
    }
    /**
     * Get the currently used LoggableListener
     *
     * @throws RuntimeException if listener is not found
     *
     * @phpstan-return LoggableListener<T>
     */
    private function get_loggable_listener(): Loggable_Listener
    {
        if (null === $this->listener) {
            foreach ($this->dm->get_event_manager()->get_all_listeners() as $listeners) {
                foreach ($listeners as $listener) {
                    if ($listener instanceof Loggable_Listener) {
                        $this->listener = $listener;
                        break 2;
                    }
                }
            }
            if (null === $this->listener) {
                throw new RuntimeException('The loggable listener could not be found');
            }
        }
        return $this->listener;
    }
}