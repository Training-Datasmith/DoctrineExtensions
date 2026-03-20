<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Entity\Repository;

use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Entity_Repository;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Query;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Exception\RuntimeException;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Tool\Wrapper\Entity_Wrapper;
use Gedmo\Translatable\Entity\Mapped_Superclass\Abstract_Personal_Translation;
use Gedmo\Translatable\Mapping\Event\Adapter\ORM as TranslatableAdapterORM;
use Gedmo\Translatable\Translatable_Listener;
/**
 * The TranslationRepository has some useful functions
 * to interact with translations.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @phpstan-extends EntityRepository<object>
 */
class Translation_Repository extends Entity_Repository
{
    /**
     * Current TranslatableListener instance used
     * in EntityManager
     */
    private ?Translatable_Listener $listener = null;
    public function __construct(Entity_Manager_Interface $em, Class_Metadata $class)
    {
        if ($class->get_reflection_class()->is_subclass_of(Abstract_Personal_Translation::class)) {
            throw new UnexpectedValueException('This repository is useless for personal translations');
        }
        parent::__construct($em, $class);
    }
    /**
     * Makes additional translation of $entity $field into $locale
     * using $value
     *
     * @param object $entity
     * @param string $field
     * @param string $locale
     * @param mixed  $value
     *
     * @throws InvalidArgumentException
     *
     * @return static
     */
    public function translate($entity, $field, $locale, $value)
    {
        $meta = $this->get_entity_manager()->get_class_metadata(get_class($entity));
        $listener = $this->get_translatable_listener();
        $config = $listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        if (!isset($config['fields']) || !in_array($field, $config['fields'], true)) {
            throw new InvalidArgumentException("Entity: {$meta->get_name()} does not translate field - {$field}");
        }
        $needs_persist = true;
        if ($locale === $listener->get_translatable_locale($entity, $meta, $this->get_entity_manager())) {
            $meta->set_field_value($entity, $field, $value);
            $this->get_entity_manager()->persist($entity);
        } else {
            if (isset($config['translationClass'])) {
                $class = $config['translationClass'];
            } else {
                $ea = new Translatable_Adapter_Orm();
                $class = $listener->get_translation_class($ea, $config['useObjectClass']);
            }
            $foreign_key = $meta->get_field_value($entity, $meta->get_single_identifier_field_name());
            $object_class = $config['useObjectClass'];
            $trans_meta = $this->get_entity_manager()->get_class_metadata($class);
            $trans = $this->find_one_by(['locale' => $locale, 'objectClass' => $object_class, 'field' => $field, 'foreignKey' => $foreign_key]);
            if (!$trans) {
                $trans = $trans_meta->new_instance();
                $trans_meta->set_field_value($trans, 'foreignKey', $foreign_key);
                $trans_meta->set_field_value($trans, 'objectClass', $object_class);
                $trans_meta->set_field_value($trans, 'field', $field);
                $trans_meta->set_field_value($trans, 'locale', $locale);
            }
            if ($listener->get_default_locale() != $listener->get_translatable_locale($entity, $meta, $this->get_entity_manager()) && $locale === $listener->get_default_locale()) {
                $listener->set_translation_in_default_locale(spl_object_id($entity), $field, $trans);
                $needs_persist = $listener->get_persist_default_locale_translation();
            }
            $transformed = $this->get_entity_manager()->get_connection()->convert_to_database_value($value, $meta->get_type_of_field($field));
            $trans_meta->set_field_value($trans, 'content', $transformed);
            if ($needs_persist) {
                if ($this->get_entity_manager()->get_unit_of_work()->is_in_identity_map($entity)) {
                    $this->get_entity_manager()->persist($trans);
                } else {
                    $oid = spl_object_id($entity);
                    $listener->add_pending_translation_insert($oid, $trans);
                }
            }
        }
        return $this;
    }
    /**
     * Loads all translations with all translatable
     * fields from the given entity
     *
     * @param object $entity Must implement Translatable
     *
     * @return array<string, array<string, string>> list of translations in locale groups
     */
    public function find_translations($entity)
    {
        $result = [];
        $wrapped = new Entity_Wrapper($entity, $this->get_entity_manager());
        if ($wrapped->has_valid_identifier()) {
            $entity_id = $wrapped->get_identifier();
            $config = $this->get_translatable_listener()->get_configuration($this->get_entity_manager(), $wrapped->get_metadata()->get_name());
            if (!$config) {
                return $result;
            }
            $entity_class = $config['useObjectClass'];
            $translation_meta = $this->get_class_metadata();
            // table inheritance support
            $translation_class = $config['translationClass'] ?? $translation_meta->root_entity_name;
            $qb = $this->get_entity_manager()->create_query_builder();
            $qb->select('trans.content, trans.field, trans.locale')->from($translation_class, 'trans')->where('trans.foreignKey = :entityId', 'trans.objectClass = :entityClass')->order_by('trans.locale')->set_parameter('entityId', $entity_id)->set_parameter('entityClass', $entity_class);
            foreach ($qb->get_query()->to_iterable([], Query::HYDRATE_ARRAY) as $row) {
                $result[$row['locale']][$row['field']] = $row['content'];
            }
        }
        return $result;
    }
    /**
     * Find the entity $class by the translated field.
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
     * @return object instance of $class or null if not found
     */
    public function find_object_by_translated_field($field, $value, $class)
    {
        $entity = null;
        $meta = $this->get_entity_manager()->get_class_metadata($class);
        $translation_meta = $this->get_class_metadata();
        // table inheritance support
        if ($meta->has_field($field)) {
            $dql = "SELECT trans.foreignKey FROM {$translation_meta->root_entity_name} trans";
            $dql .= ' WHERE trans.objectClass = :class';
            $dql .= ' AND trans.field = :field';
            $dql .= ' AND trans.content = :value';
            $q = $this->get_entity_manager()->create_query($dql);
            $q->set_parameters(['class' => $class, 'field' => $field, 'value' => $value]);
            $q->set_max_results(1);
            $id = $q->get_single_scalar_result();
            if (null !== $id) {
                $entity = $this->get_entity_manager()->find($class, $id);
            }
        }
        return $entity;
    }
    /**
     * Loads all translations with all translatable
     * fields by a given entity primary key
     *
     * @param mixed $id primary key value of an entity
     *
     * @return array<string, array<string, string>>
     */
    public function find_translations_by_object_id($id)
    {
        $result = [];
        if ($id) {
            $translation_meta = $this->get_class_metadata();
            // table inheritance support
            $qb = $this->get_entity_manager()->create_query_builder();
            $qb->select('trans.content, trans.field, trans.locale')->from($translation_meta->root_entity_name, 'trans')->where('trans.foreignKey = :entityId')->order_by('trans.locale')->set_parameter('entityId', $id);
            $q = $qb->get_query();
            foreach ($q->to_iterable([], Query::HYDRATE_ARRAY) as $row) {
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
            foreach ($this->get_entity_manager()->get_event_manager()->get_all_listeners() as $listeners) {
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
}