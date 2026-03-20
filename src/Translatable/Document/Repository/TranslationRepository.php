<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Document\Repository;

use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Doctrine\ODM\Mongo_Db\Repository\Document_Repository;
use Doctrine\ODM\Mongo_Db\Types\Type;
use Doctrine\ODM\Mongo_Db\Unit_Of_Work;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Exception\RuntimeException;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Tool\Wrapper\Mongo_Document_Wrapper;
use Gedmo\Translatable\Document\Mapped_Superclass\Abstract_Personal_Translation;
use Gedmo\Translatable\Mapping\Event\Adapter\ODM as TranslatableAdapterODM;
use Gedmo\Translatable\Translatable_Listener;
/**
 * The TranslationRepository has some useful functions
 * to interact with translations.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @template T of object
 *
 * @template-extends DocumentRepository<T>
 */
class Translation_Repository extends Document_Repository
{
    /**
     * Current TranslatableListener instance used
     * in EntityManager
     */
    private ?Translatable_Listener $listener = null;
    /**
     * @param ClassMetadata<T> $class
     */
    public function __construct(Document_Manager $dm, Unit_Of_Work $uow, Class_Metadata $class)
    {
        if ($class->get_reflection_class()->is_subclass_of(Abstract_Personal_Translation::class)) {
            throw new UnexpectedValueException('This repository is useless for personal translations');
        }
        parent::__construct($dm, $uow, $class);
    }
    /**
     * Makes additional translation of $document $field into $locale
     * using $value
     *
     * @param object $document
     * @param string $field
     * @param string $locale
     * @param mixed  $value
     *
     * @return static
     */
    public function translate($document, $field, $locale, $value)
    {
        $meta = $this->dm->get_class_metadata(get_class($document));
        $listener = $this->get_translatable_listener();
        $config = $listener->get_configuration($this->dm, $meta->get_name());
        if (!isset($config['fields']) || !in_array($field, $config['fields'], true)) {
            throw new InvalidArgumentException("Document: {$meta->get_name()} does not translate field - {$field}");
        }
        $mod_record_value = !$listener->get_persist_default_locale_translation() && $locale === $listener->get_default_locale() || $listener->get_translatable_locale($document, $meta, $this->get_document_manager()) === $locale;
        if ($mod_record_value) {
            $meta->set_field_value($document, $field, $value);
            $this->dm->persist($document);
        } else {
            if (isset($config['translationClass'])) {
                $class = $config['translationClass'];
            } else {
                $ea = new Translatable_Adapter_Odm();
                $class = $listener->get_translation_class($ea, $config['useObjectClass']);
            }
            $foreign_key = $meta->get_field_value($document, $meta->get_identifier()[0]);
            $object_class = $config['useObjectClass'];
            $trans_meta = $this->dm->get_class_metadata($class);
            $trans = $this->find_one_by(['locale' => $locale, 'field' => $field, 'objectClass' => $object_class, 'foreignKey' => $foreign_key]);
            if (!$trans) {
                $trans = $trans_meta->new_instance();
                $trans_meta->set_field_value($trans, 'foreignKey', $foreign_key);
                $trans_meta->set_field_value($trans, 'objectClass', $object_class);
                $trans_meta->set_field_value($trans, 'field', $field);
                $trans_meta->set_field_value($trans, 'locale', $locale);
            }
            $mapping = $meta->get_field_mapping($field);
            $type = $this->get_type($mapping['type']);
            $transformed = $type->convert_to_database_value($value);
            $trans_meta->set_field_value($trans, 'content', $transformed);
            if ($this->dm->get_unit_of_work()->is_in_identity_map($document)) {
                $this->dm->persist($trans);
            } else {
                $oid = spl_object_id($document);
                $listener->add_pending_translation_insert($oid, $trans);
            }
        }
        return $this;
    }
    /**
     * Loads all translations with all translatable
     * fields from the given entity
     *
     * @param object $document
     *
     * @return array<string, array<string, string>> list of translations in locale groups
     */
    public function find_translations($document)
    {
        $result = [];
        $wrapped = new Mongo_Document_Wrapper($document, $this->dm);
        if ($wrapped->has_valid_identifier()) {
            $document_id = $wrapped->get_identifier();
            $translation_meta = $this->get_class_metadata();
            // table inheritance support
            $config = $this->get_translatable_listener()->get_configuration($this->dm, $wrapped->get_metadata()->get_name());
            if (!$config) {
                return $result;
            }
            $document_class = $config['useObjectClass'];
            $translation_class = $config['translationClass'] ?? $translation_meta->root_document_name;
            $qb = $this->dm->create_query_builder($translation_class);
            $q = $qb->field('foreignKey')->equals($document_id)->field('objectClass')->equals($document_class)->field('content')->exists(true)->not_equal(null)->sort('locale', 'asc')->get_query();
            $q->set_hydrate(false);
            foreach ($q->getIterator() as $row) {
                $result[$row['locale']][$row['field']] = $row['content'];
            }
        }
        return $result;
    }
    /**
     * Find the object $class by the translated field.
     * Result is the first occurrence of translated field.
     * Query can be slow, since there are no indexes on such
     * columns
     *
     * @param string $field
     * @param string $value
     * @param string $class
     *
     * @phpstan-param class-string $class
     *
     * @return object|null instance of $class or null if not found
     */
    public function find_object_by_translated_field($field, $value, $class)
    {
        $meta = $this->dm->get_class_metadata($class);
        if (!$meta->has_field($field)) {
            return null;
        }
        $qb = $this->create_query_builder();
        $q = $qb->field('field')->equals($field)->field('objectClass')->equals($meta->root_document_name)->field('content')->equals($value)->get_query();
        $q->set_hydrate(false);
        $result = $q->get_single_result();
        $id = $result['foreign_key'] ?? null;
        if (null === $id) {
            return null;
        }
        return $this->dm->find($class, $id);
    }
    /**
     * Loads all translations with all translatable
     * fields by a given document primary key
     *
     * @param mixed $id primary key value of document
     *
     * @return array<string, array<string, string>>
     */
    public function find_translations_by_object_id($id)
    {
        $result = [];
        if ($id) {
            $qb = $this->create_query_builder();
            $q = $qb->field('foreignKey')->equals($id)->field('content')->exists(true)->not_equal(null)->sort('locale', 'asc')->get_query();
            $q->set_hydrate(false);
            foreach ($q->getIterator() as $row) {
                $result[$row['locale']][$row['field']] = $row['content'];
            }
        }
        return $result;
    }
    /**
     * Get the currently used TranslatableListener
     *
     * @throws RuntimeException if listener is not found
     */
    private function get_translatable_listener(): Translatable_Listener
    {
        if (null === $this->listener) {
            foreach ($this->dm->get_event_manager()->get_all_listeners() as $listeners) {
                foreach ($listeners as $listener) {
                    if ($listener instanceof Translatable_Listener) {
                        return $this->listener = $listener;
                    }
                }
            }
            throw new RuntimeException('The translation listener could not be found');
        }
        return $this->listener;
    }
    private function get_type(string $type): Type
    {
        return Type::get_type($type);
    }
}