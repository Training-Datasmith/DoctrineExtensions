<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Mapping\Driver;

use Doctrine\ORM\Mapping\Embedded_Class_Mapping;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Annotation\Language;
use Gedmo\Mapping\Annotation\Locale;
use Gedmo\Mapping\Annotation\Translatable;
use Gedmo\Mapping\Annotation\Translation_Entity;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
/**
 * Mapping driver for the translatable extension which reads extended metadata from annotations on a translatable class.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object to configure the translation model for a translatable class.
     */
    public const ENTITY_CLASS = Translation_Entity::class;
    /**
     * Mapping object to identify a field as translatable in a translatable class.
     */
    public const TRANSLATABLE = Translatable::class;
    /**
     * Mapping object to identify the field which stores the locale or language for the translation.
     *
     * This object is an alias of {@see self::LANGUAGE}
     */
    public const LOCALE = Locale::class;
    /**
     * Mapping object to identify the field which stores the locale or language for the translation.
     *
     * This object is an alias of {@see self::LOCALE}
     */
    public const LANGUAGE = Language::class;
    public function read_extended_metadata($meta, array &$config): array
    {
        $class = $this->get_meta_reflection_class($meta);
        // class annotations
        if ($annot = $this->reader->get_class_annotation($class, self::ENTITY_CLASS)) {
            \assert($annot instanceof Translation_Entity);
            if (!$cl = $this->get_related_class_name($meta, $annot->class)) {
                throw new Invalid_Mapping_Exception("Translation class: {$annot->class} does not exist.");
            }
            $config['translationClass'] = $cl;
        }
        // property annotations
        foreach ($class->get_properties() as $property) {
            if ($meta->is_mapped_superclass && !$property->is_private()) {
                continue;
            }
            if ($meta->is_inherited_field($property->name)) {
                continue;
            }
            if (isset($meta->association_mappings[$property->name]['inherited'])) {
                continue;
            }
            // translatable property
            if ($translatable = $this->reader->get_property_annotation($property, self::TRANSLATABLE)) {
                \assert($translatable instanceof Translatable);
                $field = $property->get_name();
                if (!$meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find translatable [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                // fields cannot be overrided and throws mapping exception
                $config['fields'][] = $field;
                if (isset($translatable->fallback)) {
                    $config['fallback'][$field] = $translatable->fallback;
                }
            }
            // locale property
            if ($this->reader->get_property_annotation($property, self::LOCALE)) {
                $field = $property->get_name();
                if ($meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Locale field [{$field}] should not be mapped as column property in entity - {$meta->get_name()}, since it makes no sense");
                }
                $config['locale'] = $field;
            } elseif ($this->reader->get_property_annotation($property, self::LANGUAGE)) {
                $field = $property->get_name();
                if ($meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Language field [{$field}] should not be mapped as column property in entity - {$meta->get_name()}, since it makes no sense");
                }
                $config['locale'] = $field;
            }
        }
        // Embedded entity
        if (property_exists($meta, 'embeddedClasses') && $meta->embedded_classes) {
            foreach ($meta->embedded_classes as $property_name => $embedded_class_info) {
                if ($meta->is_inherited_embedded_class($property_name)) {
                    continue;
                }
                /** Remove conditional when ORM 2.x is no longer supported. */
                $class_name = $embedded_class_info instanceof Embedded_Class_Mapping ? $embedded_class_info->class : $embedded_class_info['class'];
                $embedded_class = new \ReflectionClass($class_name);
                foreach ($embedded_class->get_properties() as $embedded_property) {
                    if ($translatable = $this->reader->get_property_annotation($embedded_property, self::TRANSLATABLE)) {
                        \assert($translatable instanceof Translatable);
                        $field = $property_name . '.' . $embedded_property->get_name();
                        $config['fields'][] = $field;
                        if (isset($translatable->fallback)) {
                            $config['fallback'][$field] = $translatable->fallback;
                        }
                    }
                }
            }
        }
        if (!$meta->is_mapped_superclass && $config) {
            if (is_array($meta->get_identifier()) && count($meta->get_identifier()) > 1) {
                throw new Invalid_Mapping_Exception("Translatable does not support composite identifiers in class - {$meta->get_name()}");
            }
        }
        return $config;
    }
}