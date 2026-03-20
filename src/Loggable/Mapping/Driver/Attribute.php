<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable\Mapping\Driver;

use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata as ClassMetadataODM;
use Doctrine\ORM\Mapping\Class_Metadata as ClassMetadataORM;
use Doctrine\ORM\Mapping\Embedded_Class_Mapping;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Annotation\Loggable;
use Gedmo\Mapping\Annotation\Versioned;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
/**
 * Mapping driver for the loggable extension which reads extended metadata from attributes on a loggable class.
 *
 * @author Boussekeyt Jules <jules.boussekeyt@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object defining a loggable class.
     */
    public const LOGGABLE = Loggable::class;
    /**
     * Mapping object defining a versioned property from a loggable class.
     */
    public const VERSIONED = Versioned::class;
    public function validate_full_metadata(Class_Metadata $meta, array $config): void
    {
        if ($config && $meta instanceof Class_Metadata_Odm && count($meta->get_identifier()) > 1) {
            throw new Invalid_Mapping_Exception("Loggable does not support composite identifiers in class - {$meta->get_name()}");
        }
        if (isset($config['versioned']) && !isset($config['loggable'])) {
            throw new Invalid_Mapping_Exception("Class must be annotated with Loggable annotation in order to track versioned fields in class - {$meta->get_name()}");
        }
    }
    public function read_extended_metadata($meta, array &$config): array
    {
        $class = $this->get_meta_reflection_class($meta);
        // class annotations
        if ($annot = $this->reader->get_class_annotation($class, self::LOGGABLE)) {
            \assert($annot instanceof Loggable);
            $config['loggable'] = true;
            if ($annot->log_entry_class) {
                if (!$cl = $this->get_related_class_name($meta, $annot->log_entry_class)) {
                    throw new Invalid_Mapping_Exception("LogEntry class: {$annot->log_entry_class} does not exist.");
                }
                $config['logEntryClass'] = $cl;
            }
        }
        // property annotations
        foreach ($class->get_properties() as $property) {
            $field = $property->get_name();
            if ($meta->is_mapped_superclass && !$property->is_private()) {
                continue;
            }
            // versioned property
            if ($this->reader->get_property_annotation($property, self::VERSIONED)) {
                if (!$this->is_mapping_valid($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Cannot apply versioning to field [{$field}] as it is collection in object - {$meta->get_name()}");
                }
                if (isset($meta->embedded_classes[$field])) {
                    $this->inspect_embedded_for_versioned($field, $config, $meta);
                    continue;
                }
                // fields cannot be overridden and throws mapping exception
                if (!in_array($field, $config['versioned'] ?? [], true)) {
                    $config['versioned'][] = $field;
                }
            }
        }
        if (!$meta->is_mapped_superclass && $config) {
            if ($meta instanceof Class_Metadata_Odm && count($meta->get_identifier()) > 1) {
                throw new Invalid_Mapping_Exception("Loggable does not support composite identifiers in class - {$meta->get_name()}");
            }
            if ($this->is_class_annotation_in_valid($meta, $config)) {
                throw new Invalid_Mapping_Exception("Class must be annotated with Loggable annotation in order to track versioned fields in class - {$meta->get_name()}");
            }
        }
        return $config;
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    protected function is_mapping_valid(Class_Metadata $meta, $field): bool
    {
        return false == $meta->is_collection_valued_association($field);
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     */
    protected function is_class_annotation_in_valid(Class_Metadata $meta, array &$config): bool
    {
        return isset($config['versioned']) && !isset($config['loggable']) && (!isset($meta->is_embedded_class) || !$meta->is_embedded_class);
    }
    /**
     * Searches properties of embedded objects for versioned fields
     *
     * @param array<string, mixed>     $config
     * @param ClassMetadataORM<object> $meta
     */
    private function inspect_embedded_for_versioned(string $field, array &$config, Class_Metadata_Orm $meta): void
    {
        /** Remove conditional when ORM 2.x is no longer supported. */
        $class_name = $meta->embedded_classes[$field] instanceof Embedded_Class_Mapping ? $meta->embedded_classes[$field]->class : $meta->embedded_classes[$field]['class'];
        $class = new \ReflectionClass($class_name);
        // property annotations
        foreach ($class->get_properties() as $property) {
            // versioned property
            if ($this->reader->get_property_annotation($property, self::VERSIONED)) {
                $embedded_field = $field . '.' . $property->get_name();
                $config['versioned'][] = $embedded_field;
                if (isset($meta->embedded_classes[$embedded_field])) {
                    $this->inspect_embedded_for_versioned($embedded_field, $config, $meta);
                }
            }
        }
    }
}