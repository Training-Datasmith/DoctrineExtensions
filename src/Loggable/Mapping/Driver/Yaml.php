<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable\Mapping\Driver;

use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver;
use Gedmo\Mapping\Driver\File;
/**
 * This is a yaml mapping driver for Loggable
 * behavioral extension. Used for extraction of extended
 * metadata from yaml specifically for Loggable
 * extension.
 *
 * @author Boussekeyt Jules <jules.boussekeyt@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @deprecated since gedmo/doctrine-extensions 3.5, will be removed in version 4.0.
 *
 * @internal
 */
class Yaml extends File
{
    /**
     * File extension
     *
     * @var string
     */
    protected $_extension = '.dcm.yml';
    public function read_extended_metadata($meta, array &$config)
    {
        $mapping = $this->_get_mapping($meta->get_name());
        if (isset($mapping['gedmo'])) {
            $class_mapping = $mapping['gedmo'];
            if (isset($class_mapping['loggable'])) {
                $config['loggable'] = true;
                if (isset($class_mapping['loggable']['logEntryClass'])) {
                    if (!$cl = $this->get_related_class_name($meta, $class_mapping['loggable']['logEntryClass'])) {
                        throw new Invalid_Mapping_Exception("LogEntry class: {$class_mapping['loggable']['logEntryClass']} does not exist.");
                    }
                    $config['logEntryClass'] = $cl;
                }
            }
        }
        if (isset($mapping['fields'])) {
            foreach ($mapping['fields'] as $field => $field_mapping) {
                if (!isset($field_mapping['gedmo'])) {
                    continue;
                }
                if (!in_array('versioned', $field_mapping['gedmo'], true)) {
                    continue;
                }
                if ($meta->is_collection_valued_association($field)) {
                    throw new Invalid_Mapping_Exception("Cannot apply versioning to field [{$field}] as it is collection in object - {$meta->get_name()}");
                }
                // fields cannot be overrided and throws mapping exception
                $config['versioned'][] = $field;
            }
        }
        if (isset($mapping['attributeOverride'])) {
            foreach ($mapping['attributeOverride'] as $field => $field_mapping) {
                if (!isset($field_mapping['gedmo'])) {
                    continue;
                }
                if (!in_array('versioned', $field_mapping['gedmo'], true)) {
                    continue;
                }
                if ($meta->is_collection_valued_association($field)) {
                    throw new Invalid_Mapping_Exception("Cannot apply versioning to field [{$field}] as it is collection in object - {$meta->get_name()}");
                }
                // fields cannot be overrided and throws mapping exception
                $config['versioned'][] = $field;
            }
        }
        if (isset($mapping['manyToOne'])) {
            foreach ($mapping['manyToOne'] as $field => $field_mapping) {
                if (!isset($field_mapping['gedmo'])) {
                    continue;
                }
                if (!in_array('versioned', $field_mapping['gedmo'], true)) {
                    continue;
                }
                if ($meta->is_collection_valued_association($field)) {
                    throw new Invalid_Mapping_Exception("Cannot apply versioning to field [{$field}] as it is collection in object - {$meta->get_name()}");
                }
                // fields cannot be overrided and throws mapping exception
                $config['versioned'][] = $field;
            }
        }
        if (isset($mapping['oneToOne'])) {
            foreach ($mapping['oneToOne'] as $field => $field_mapping) {
                if (!isset($field_mapping['gedmo'])) {
                    continue;
                }
                if (!in_array('versioned', $field_mapping['gedmo'], true)) {
                    continue;
                }
                if ($meta->is_collection_valued_association($field)) {
                    throw new Invalid_Mapping_Exception("Cannot apply versioning to field [{$field}] as it is collection in object - {$meta->get_name()}");
                }
                // fields cannot be overrided and throws mapping exception
                $config['versioned'][] = $field;
            }
        }
        if (isset($mapping['embedded'])) {
            foreach ($mapping['embedded'] as $field => $field_mapping) {
                if (!isset($field_mapping['gedmo'])) {
                    continue;
                }
                if (!in_array('versioned', $field_mapping['gedmo'], true)) {
                    continue;
                }
                if ($meta->is_collection_valued_association($field)) {
                    throw new Invalid_Mapping_Exception("Cannot apply versioning to field [{$field}] as it is collection in object - {$meta->get_name()}");
                }
                // fields cannot be overrided and throws mapping exception
                $mapping = $this->_get_mapping($field_mapping['class']);
                $config = $this->inspect_embedded_for_versioned($field, $mapping, $config);
            }
        }
        if (!$meta->is_mapped_superclass && $config) {
            if ($meta instanceof Class_Metadata && count($meta->get_identifier()) > 1) {
                throw new Invalid_Mapping_Exception("Loggable does not support composite identifiers in class - {$meta->get_name()}");
            }
            if (isset($config['versioned']) && !isset($config['loggable'])) {
                throw new Invalid_Mapping_Exception("Class must be annotated with Loggable annotation in order to track versioned fields in class - {$meta->get_name()}");
            }
        }
        return $config;
    }
    protected function _load_mapping_file($file)
    {
        return \Symfony\Component\Yaml\Yaml::parse(file_get_contents($file));
    }
    /**
     * @param array<string, array<string, array<string, mixed>>> $mapping
     * @param array<string, mixed>                               $config
     *
     * @return array<string, mixed>
     */
    private function inspect_embedded_for_versioned(string $field, array $mapping, array $config): array
    {
        if (isset($mapping['fields'])) {
            foreach ($mapping['fields'] as $property => $field_mapping) {
                $config['versioned'][] = $field . '.' . $property;
            }
        }
        return $config;
    }
}