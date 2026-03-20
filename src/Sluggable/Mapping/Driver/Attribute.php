<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sluggable\Mapping\Driver;

use Doctrine\ORM\Mapping\Embedded_Class_Mapping;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Annotation\Slug;
use Gedmo\Mapping\Annotation\Slug_Handler;
use Gedmo\Mapping\Annotation\Slug_Handler_Option;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
use Gedmo\Sluggable\Handler\Slug_Handler_Interface;
/**
 * Mapping driver for the sluggable extension which reads extended metadata from attributes on a sluggable class.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object configuring a field which should have a slug computed.
     */
    public const SLUG = Slug::class;
    /**
     * Mapping object configuring a slug handler for a sluggable field.
     */
    public const HANDLER = Slug_Handler::class;
    /**
     * Mapping object configuring an option for a slug handler.
     *
     * @deprecated since gedmo/doctrine-extensions 3.18, will be removed in version 4.0.
     */
    public const HANDLER_OPTION = Slug_Handler_Option::class;
    /**
     * List of types which are valid for slug and sluggable fields
     *
     * @var string[]
     */
    protected $valid_types = ['string', 'text', 'integer', 'int', 'date', 'date_immutable', 'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'citext', 'ascii_string'];
    public function read_extended_metadata($meta, array &$config)
    {
        $class = $this->get_meta_reflection_class($meta);
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
            $config = $this->retrieve_slug($meta, $config, $property);
        }
        // Embedded entity
        if (property_exists($meta, 'embeddedClasses') && $meta->embedded_classes) {
            foreach ($meta->embedded_classes as $property_name => $embedded_class_info) {
                /** Remove conditional when ORM 2.x is no longer supported. */
                $class_name = $embedded_class_info instanceof Embedded_Class_Mapping ? $embedded_class_info->class : $embedded_class_info['class'];
                $embedded_class = new \ReflectionClass($class_name);
                foreach ($embedded_class->get_properties() as $embedded_property) {
                    $config = $this->retrieve_slug($meta, $config, $embedded_property, $property_name);
                }
            }
        }
        return $config;
    }
    /**
     * @param ClassMetadata<object> $meta
     *
     * @return array<class-string<SlugHandlerInterface>, SlugHandler[]>
     */
    protected function get_slug_handlers(\ReflectionProperty $property, Slug $slug, Class_Metadata $meta): array
    {
        /** @var list<SlugHandler>|null $attributeHandlers */
        $attribute_handlers = $this->reader->get_property_annotation($property, self::HANDLER);
        if (null === $attribute_handlers) {
            return [];
        }
        $handlers = [];
        foreach ($attribute_handlers as $handler) {
            if (!class_exists($handler->class)) {
                throw new Invalid_Mapping_Exception("SlugHandler class: {$handler->class} should be a valid class name in entity - {$meta->get_name()}");
            }
            /** @var class-string<SlugHandlerInterface> $class */
            $class = $handler->class;
            $handlers[$class] = [];
            foreach ($handler->options as $name => $value) {
                $handlers[$class][$name] = $value;
            }
            $class::validate($handlers[$class], $meta);
        }
        return $handlers;
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     *
     * @return array<string, array<string, mixed>>
     */
    private function retrieve_slug(Class_Metadata $meta, array &$config, \ReflectionProperty $property, ?string $field_name_prefix = null): array
    {
        $field_name = null !== $field_name_prefix ? $field_name_prefix . '.' . $property->get_name() : $property->get_name();
        // slug property
        $slug = $this->reader->get_property_annotation($property, self::SLUG);
        if (null === $slug) {
            return $config;
        }
        assert($slug instanceof Slug);
        if (!$meta->has_field($field_name)) {
            throw new Invalid_Mapping_Exception("Unable to find slug [{$field_name}] as mapped property in entity - {$meta->get_name()}");
        }
        if (!$this->is_valid_field($meta, $field_name)) {
            throw new Invalid_Mapping_Exception("Cannot use field - [{$field_name}] for slug storage, type is not valid and must be 'string' or 'text' in class - {$meta->get_name()}");
        }
        // process slug handlers
        $handlers = $this->get_slug_handlers($property, $slug, $meta);
        // process slug fields
        if ([] === $slug->fields || !is_array($slug->fields)) {
            throw new Invalid_Mapping_Exception("Slug must contain at least one field for slug generation in class - {$meta->get_name()}");
        }
        foreach ($slug->fields as $slug_field) {
            $slug_field_with_prefix = null !== $field_name_prefix ? $field_name_prefix . '.' . $slug_field : $slug_field;
            if (!$meta->has_field($slug_field_with_prefix)) {
                throw new Invalid_Mapping_Exception("Unable to find slug [{$slug_field_with_prefix}] as mapped property in entity - {$meta->get_name()}");
            }
            if (!$this->is_valid_field($meta, $slug_field_with_prefix)) {
                throw new Invalid_Mapping_Exception("Cannot use field - [{$slug_field_with_prefix}] for slug storage, type is not valid and must be 'string' or 'text' in class - {$meta->get_name()}");
            }
        }
        if ([] !== $meta->get_identifier() && $meta->is_identifier($field_name) && !$slug->unique) {
            throw new Invalid_Mapping_Exception("Identifier field - [{$field_name}] slug must be unique in order to maintain primary key in class - {$meta->get_name()}");
        }
        if (false === $slug->unique && $slug->unique_base) {
            throw new Invalid_Mapping_Exception("Slug annotation [unique_base] can not be set if unique is unset or 'false'");
        }
        if (false === $slug->unique && $slug->unique_over_translations) {
            throw new Invalid_Mapping_Exception("Slug annotation [uniqueOverTranslations] can not be set if unique is unset or 'false'");
        }
        if ($slug->unique_base && !$meta->has_field($slug->unique_base) && !$meta->has_association($slug->unique_base)) {
            throw new Invalid_Mapping_Exception("Unable to find [{$slug->unique_base}] as mapped property in entity - {$meta->get_name()}");
        }
        $sluggable_fields = [];
        foreach ($slug->fields as $field) {
            $sluggable_fields[] = null !== $field_name_prefix ? $field_name_prefix . '.' . $field : $field;
        }
        // set all options
        $config['slugs'][$field_name] = ['fields' => $sluggable_fields, 'slug' => $field_name, 'style' => $slug->style, 'dateFormat' => $slug->date_format, 'updatable' => $slug->updatable, 'unique' => $slug->unique, 'unique_base' => $slug->unique_base, 'separator' => $slug->separator, 'prefix' => $slug->prefix, 'suffix' => $slug->suffix, 'handlers' => $handlers, 'uniqueOverTranslations' => $slug->unique_over_translations];
        return $config;
    }
}