<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable\Entity\Repository;

use Doctrine\ORM\Entity_Repository;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Query;
use Gedmo\Exception\RuntimeException;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Loggable\Entity\Mapped_Superclass\Abstract_Log_Entry;
use Gedmo\Loggable\Loggable;
use Gedmo\Loggable\Loggable_Listener;
use Gedmo\Tool\Wrapper\Entity_Wrapper;
/**
 * The LogEntryRepository has some useful functions
 * to interact with log entries.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @template T of Loggable|object
 *
 * @template-extends EntityRepository<AbstractLogEntry<T>>
 */
class Log_Entry_Repository extends Entity_Repository
{
    /**
     * Currently used loggable listener
     *
     * @var LoggableListener<T>|null
     */
    private ?Loggable_Listener $listener = null;
    /**
     * Loads all log entries for the given entity
     *
     * @param T $entity
     *
     * @return array<array-key, AbstractLogEntry<T>>
     */
    public function get_log_entries($entity)
    {
        return $this->get_log_entries_query($entity)->get_result();
    }
    /**
     * Get the query for loading of log entries
     *
     * @param T $entity
     *
     * @return Query
     */
    public function get_log_entries_query($entity)
    {
        $wrapped = new Entity_Wrapper($entity, $this->get_entity_manager());
        $object_class = $wrapped->get_metadata()->get_name();
        $meta = $this->get_class_metadata();
        $dql = "SELECT log FROM {$meta->get_name()} log";
        $dql .= ' WHERE log.objectId = :objectId';
        $dql .= ' AND log.objectClass = :objectClass';
        $dql .= ' ORDER BY log.version DESC';
        $object_id = (string) $wrapped->get_identifier(false, true);
        $q = $this->get_entity_manager()->create_query($dql);
        $q->set_parameters(['objectId' => $object_id, 'objectClass' => $object_class]);
        return $q;
    }
    /**
     * Reverts given $entity to $revision by
     * restoring all fields from that $revision.
     * After this operation you will need to
     * persist and flush the $entity.
     *
     * @param T   $entity
     * @param int $version
     *
     * @throws UnexpectedValueException
     */
    public function revert($entity, $version = 1): void
    {
        $wrapped = new Entity_Wrapper($entity, $this->get_entity_manager());
        $object_meta = $wrapped->get_metadata();
        $object_class = $object_meta->get_name();
        $meta = $this->get_class_metadata();
        $dql = "SELECT log FROM {$meta->get_name()} log";
        $dql .= ' WHERE log.objectId = :objectId';
        $dql .= ' AND log.objectClass = :objectClass';
        $dql .= ' AND log.version <= :version';
        $dql .= ' ORDER BY log.version DESC';
        $object_id = (string) $wrapped->get_identifier(false, true);
        $q = $this->get_entity_manager()->create_query($dql);
        $q->set_parameters(['objectId' => $object_id, 'objectClass' => $object_class, 'version' => $version]);
        $config = $this->get_loggable_listener()->get_configuration($this->get_entity_manager(), $object_meta->get_name());
        $fields = $config['versioned'];
        $filled = false;
        $logs_found = false;
        $logs = $q->to_iterable();
        assert($logs instanceof \Generator);
        while (null !== ($log = $logs->current()) && !$filled) {
            $logs_found = true;
            $logs->next();
            if ($data = $log->get_data()) {
                foreach ($data as $field => $value) {
                    if (in_array($field, $fields, true)) {
                        $this->map_value($object_meta, $field, $value);
                        $wrapped->set_property_value($field, $value);
                        unset($fields[array_search($field, $fields, true)]);
                    }
                }
            }
            $filled = [] === $fields;
        }
        if (!$logs_found) {
            throw new UnexpectedValueException('Could not find any log entries under version: ' . $version);
        }
        /*if (count($fields)) {
              throw new \Gedmo\Exception\UnexpectedValueException('Could not fully revert the entity to version: '.$version);
          }*/
    }
    /**
     * @param ClassMetadata<T> $objectMeta
     * @param string           $field
     * @param mixed            $value
     *
     * @return void
     */
    protected function map_value(Class_Metadata $object_meta, $field, &$value)
    {
        if (!$object_meta->is_single_valued_association($field)) {
            return;
        }
        $mapping = $object_meta->get_association_mapping($field);
        $value = $value ? $this->get_entity_manager()->get_reference($mapping->target_entity ?? $mapping['targetEntity'], $value) : null;
    }
    /**
     * Get the currently used LoggableListener
     *
     * @throws RuntimeException if listener is not found
     *
     * @return LoggableListener<T>
     */
    private function get_loggable_listener(): Loggable_Listener
    {
        if (null === $this->listener) {
            foreach ($this->get_entity_manager()->get_event_manager()->get_all_listeners() as $listeners) {
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