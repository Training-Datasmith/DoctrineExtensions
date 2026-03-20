<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Mapping\Event\Adapter;

use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Doctrine\ODM\Mongo_Db\Types\Type;
use Gedmo\Exception\RuntimeException;
use Gedmo\Mapping\Event\Adapter\ODM as BaseAdapterODM;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
use Gedmo\Tool\Wrapper\Mongo_Document_Wrapper;
use Gedmo\Translatable\Document\Mapped_Superclass\Abstract_Personal_Translation;
use Gedmo\Translatable\Document\Translation;
use Gedmo\Translatable\Mapping\Event\Translatable_Adapter;
/**
 * Doctrine event adapter for ODM adapted
 * for Translatable behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
final class ODM extends Base_Adapter_Odm implements Translatable_Adapter
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
        $dm = $this->get_object_manager();
        $wrapped = Abstract_Wrapper::wrap($object, $dm);
        assert($wrapped instanceof Mongo_Document_Wrapper);
        $result = [];
        if ($this->uses_personal_translation($translation_class)) {
            // first try to load it using collection
            foreach ($wrapped->get_metadata()->field_mappings as $mapping) {
                $is_right_collection = isset($mapping['association']) && Class_Metadata::REFERENCE_MANY === $mapping['association'] && $mapping['targetDocument'] === $translation_class && 'object' === $mapping['mappedBy'];
                if ($is_right_collection) {
                    $collection = $wrapped->get_property_value($mapping['fieldName']);
                    foreach ($collection as $trans) {
                        if ($trans->get_locale() === $locale) {
                            $result[] = ['field' => $trans->get_field(), 'content' => $trans->get_content()];
                        }
                    }
                    return $result;
                }
            }
            $q = $dm->create_query_builder($translation_class)->field('object.$id')->equals($wrapped->get_identifier())->field('locale')->equals($locale)->get_query();
        } else {
            // load translated content for all translatable fields
            // construct query
            $q = $dm->create_query_builder($translation_class)->field('foreignKey')->equals($wrapped->get_identifier())->field('locale')->equals($locale)->field('objectClass')->equals($object_class)->get_query();
        }
        $q->set_hydrate(false);
        return $q->getIterator()->to_array();
    }
    public function find_translation(Abstract_Wrapper $wrapped, $locale, $field, $translation_class, $object_class)
    {
        $dm = $this->get_object_manager();
        $qb = $dm->create_query_builder($translation_class)->field('locale')->equals($locale)->field('field')->equals($field)->limit(1);
        if ($this->uses_personal_translation($translation_class)) {
            $qb->field('object.$id')->equals($wrapped->get_identifier());
        } else {
            $qb->field('foreignKey')->equals($wrapped->get_identifier());
            $qb->field('objectClass')->equals($object_class);
        }
        $q = $qb->get_query();
        return $q->get_single_result();
    }
    public function remove_associated_translations(Abstract_Wrapper $wrapped, $trans_class, $object_class)
    {
        $dm = $this->get_object_manager();
        $qb = $dm->create_query_builder($trans_class)->remove();
        if ($this->uses_personal_translation($trans_class)) {
            $qb->field('object.$id')->equals($wrapped->get_identifier());
        } else {
            $qb->field('foreignKey')->equals($wrapped->get_identifier());
            $qb->field('objectClass')->equals($object_class);
        }
        $q = $qb->get_query();
        return $q->execute();
    }
    public function insert_translation_record($translation): void
    {
        $dm = $this->get_object_manager();
        $meta = $dm->get_class_metadata(get_class($translation));
        $collection = $dm->get_document_collection($meta->get_name());
        $data = [];
        foreach ($meta->get_reflection_properties() as $field_name => $refl_prop) {
            if (!$meta->is_identifier($field_name)) {
                $data[$meta->get_field_mapping($field_name)['name']] = $refl_prop->get_value($translation);
            }
        }
        $insert_result = $collection->insert_one($data);
        if (false === $insert_result->is_acknowledged()) {
            throw new RuntimeException('Failed to insert new Translation record');
        }
    }
    public function get_translation_value($object, $field, $value = false)
    {
        $dm = $this->get_object_manager();
        $wrapped = Abstract_Wrapper::wrap($object, $dm);
        assert($wrapped instanceof Mongo_Document_Wrapper);
        $meta = $wrapped->get_metadata();
        $mapping = $meta->get_field_mapping($field);
        $type = $this->get_type($mapping['type']);
        if (false === $value) {
            $value = $wrapped->get_property_value($field);
        }
        return $type->convert_to_database_value($value);
    }
    public function set_translation_value($object, $field, $value): void
    {
        $dm = $this->get_object_manager();
        $wrapped = Abstract_Wrapper::wrap($object, $dm);
        assert($wrapped instanceof Mongo_Document_Wrapper);
        $meta = $wrapped->get_metadata();
        $mapping = $meta->get_field_mapping($field);
        $type = $this->get_type($mapping['type']);
        $value = $type->convert_to_php_value($value);
        $wrapped->set_property_value($field, $value);
    }
    private function get_type(string $type): Type
    {
        return Type::get_type($type);
    }
}