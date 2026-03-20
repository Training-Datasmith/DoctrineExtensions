<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Mapping\Event\Adapter;

use Doctrine\Common\Proxy\Proxy;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Class_Metadata as EntityClassMetadata;
use Doctrine\ORM\Mapping\Class_Metadata_Info as LegacyEntityClassMetadata;
use Gedmo\Exception\RuntimeException;
use Gedmo\Mapping\Event\Adapter\ORM as BaseAdapterORM;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
use Gedmo\Translatable\Entity\Mapped_Superclass\Abstract_Personal_Translation;
use Gedmo\Translatable\Entity\Translation;
use Gedmo\Translatable\Mapping\Event\Translatable_Adapter;
/**
 * Doctrine event adapter for ORM adapted
 * for Translatable behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
final class ORM extends Base_Adapter_Orm implements Translatable_Adapter
{
    public function uses_personal_translation($translation_class_name)
    {
        return $this->get_object_manager()->get_class_metadata($translation_class_name)->get_reflection_class()->is_subclass_of(Abstract_Personal_Translation::class);
    }
    public function get_default_translation_class(): string
    {
        return Translation::class;
    }
    public function load_translations($object, $translation_class, $locale, $object_class)
    {
        $em = $this->get_object_manager();
        $wrapped = Abstract_Wrapper::wrap($object, $em);
        $result = [];
        if ($this->uses_personal_translation($translation_class)) {
            // first try to load it using collection
            $found = false;
            $metadata = $wrapped->get_metadata();
            assert($metadata instanceof Entity_Class_Metadata || $metadata instanceof Legacy_Entity_Class_Metadata);
            foreach ($metadata->get_association_mappings() as $assoc) {
                $is_right_collection = $assoc['targetEntity'] === $translation_class && 'object' === $assoc['mappedBy'] && Entity_Class_Metadata::ONE_TO_MANY === $assoc['type'];
                if ($is_right_collection) {
                    $collection = $wrapped->get_property_value($assoc['fieldName']);
                    foreach ($collection as $trans) {
                        if ($trans->get_locale() === $locale) {
                            $result[] = ['field' => $trans->get_field(), 'content' => $trans->get_content()];
                        }
                    }
                    $found = true;
                    break;
                }
            }
            // if collection is not set, fetch it through relation
            if (!$found) {
                $dql = 'SELECT t.content, t.field FROM ' . $translation_class . ' t';
                $dql .= ' WHERE t.locale = :locale';
                $dql .= ' AND t.object = :object';
                $q = $em->create_query($dql);
                $q->set_parameters(['object' => $object, 'locale' => $locale]);
                $result = $q->get_array_result();
            }
        } else {
            // load translated content for all translatable fields
            $object_id = $this->foreign_key($wrapped->get_identifier(), $translation_class);
            // construct query
            $dql = 'SELECT t.content, t.field FROM ' . $translation_class . ' t';
            $dql .= ' WHERE t.foreignKey = :objectId';
            $dql .= ' AND t.locale = :locale';
            $dql .= ' AND t.objectClass = :objectClass';
            // fetch results
            $q = $em->create_query($dql);
            $q->set_parameters(['objectId' => $object_id, 'locale' => $locale, 'objectClass' => $object_class]);
            $result = $q->get_array_result();
        }
        return $result;
    }
    public function find_translation(Abstract_Wrapper $wrapped, $locale, $field, $translation_class, $object_class)
    {
        $em = $this->get_object_manager();
        // first look in identityMap, will save one SELECT query
        foreach ($em->get_unit_of_work()->get_identity_map() as $class_name => $objects) {
            if ($class_name === $translation_class) {
                foreach ($objects as $trans) {
                    $is_requested_translation = !$trans instanceof Proxy && $trans->get_locale() === $locale && $trans->get_field() === $field;
                    if ($is_requested_translation) {
                        if ($this->uses_personal_translation($translation_class)) {
                            $is_requested_translation = $trans->get_object() === $wrapped->get_object();
                        } else {
                            $object_id = $this->foreign_key($wrapped->get_identifier(), $translation_class);
                            $is_requested_translation = $trans->get_foreign_key() === $object_id && $trans->get_object_class() === $wrapped->get_metadata()->get_name();
                        }
                    }
                    if ($is_requested_translation) {
                        return $trans;
                    }
                }
            }
        }
        $qb = $em->create_query_builder();
        $qb->select('trans')->from($translation_class, 'trans')->where('trans.locale = :locale', 'trans.field = :field')->set_parameter('locale', $locale)->set_parameter('field', $field);
        if ($this->uses_personal_translation($translation_class)) {
            $qb->and_where('trans.object = :object');
            if ($wrapped->get_identifier()) {
                $qb->set_parameter('object', $wrapped->get_object());
            } else {
                $qb->set_parameter('object', null);
            }
        } else {
            $qb->and_where('trans.foreignKey = :objectId');
            $qb->and_where('trans.objectClass = :objectClass');
            $qb->set_parameter('objectId', $this->foreign_key($wrapped->get_identifier(), $translation_class));
            $qb->set_parameter('objectClass', $object_class);
        }
        $q = $qb->get_query();
        $q->set_max_results(1);
        return $q->get_one_or_null_result();
    }
    public function remove_associated_translations(Abstract_Wrapper $wrapped, $trans_class, $object_class)
    {
        $qb = $this->get_object_manager()->create_query_builder()->delete($trans_class, 'trans');
        if ($this->uses_personal_translation($trans_class)) {
            $qb->where('trans.object = :object');
            $qb->set_parameter('object', $wrapped->get_object());
        } else {
            $qb->where('trans.foreignKey = :objectId', 'trans.objectClass = :class');
            $qb->set_parameter('objectId', $this->foreign_key($wrapped->get_identifier(), $trans_class));
            $qb->set_parameter('class', $object_class);
        }
        return $qb->get_query()->get_single_scalar_result();
    }
    public function insert_translation_record($translation): void
    {
        $em = $this->get_object_manager();
        $meta = $em->get_class_metadata(get_class($translation));
        $data = [];
        foreach ($meta->get_reflection_properties() as $field_name => $refl_prop) {
            if (!$meta->is_identifier($field_name)) {
                $data[$meta->get_column_name($field_name)] = $refl_prop->get_value($translation);
            }
        }
        $table = $meta->get_table_name();
        if (!$em->get_connection()->insert($table, $data)) {
            throw new RuntimeException('Failed to insert new Translation record');
        }
    }
    public function get_translation_value($object, $field, $value = false)
    {
        $em = $this->get_object_manager();
        $wrapped = Abstract_Wrapper::wrap($object, $em);
        $meta = $wrapped->get_metadata();
        if (false === $value) {
            $value = $wrapped->get_property_value($field);
        }
        return $em->get_connection()->convert_to_database_value($value, $meta->get_type_of_field($field));
    }
    public function set_translation_value($object, $field, $value): void
    {
        $em = $this->get_object_manager();
        $wrapped = Abstract_Wrapper::wrap($object, $em);
        $meta = $wrapped->get_metadata();
        $value = $em->get_connection()->convert_to_php_value($value, $meta->get_type_of_field($field));
        $wrapped->set_property_value($field, $value);
    }
    /**
     * Transforms foreign key of translation to appropriate PHP value
     * to prevent database level cast
     *
     * @param mixed  $key       foreign key value
     * @param string $className translation class name
     *
     * @phpstan-param class-string $className translation class name
     *
     * @return int|string transformed foreign key
     */
    private function foreign_key($key, string $class_name)
    {
        $em = $this->get_object_manager();
        $meta = $em->get_class_metadata($class_name);
        $type = Type::get_type($meta->get_type_of_field('foreignKey'));
        switch (Type::lookup_name($type)) {
            case Types::BIGINT:
            case Types::INTEGER:
            case Types::SMALLINT:
                return (int) $key;
            default:
                return (string) $key;
        }
    }
}