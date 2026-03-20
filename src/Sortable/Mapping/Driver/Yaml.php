<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sortable\Mapping\Driver;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver;
use Gedmo\Mapping\Driver\File;
/**
 * This is a yaml mapping driver for Sortable
 * behavioral extension. Used for extraction of extended
 * metadata from yaml specifically for Sortable
 * extension.
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @deprecated since gedmo/doctrine-extensions 3.5, will be removed in version 4.0.
 *
 * @internal
 */
class Yaml extends File implements Driver
{
    /**
     * List of types which are valid for position fields
     *
     * @var string[]
     */
    private const VALID_TYPES = ['int', 'integer', 'smallint', 'bigint'];
    /**
     * File extension
     *
     * @var string
     */
    protected $_extension = '.dcm.yml';
    public function read_extended_metadata($meta, array &$config)
    {
        $mapping = $this->_get_mapping($meta->get_name());
        if (isset($mapping['fields'])) {
            foreach ($mapping['fields'] as $field => $field_mapping) {
                if (!isset($field_mapping['gedmo'])) {
                    continue;
                }
                if (!in_array('sortablePosition', $field_mapping['gedmo'], true)) {
                    continue;
                }
                if (!$this->is_valid_field($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Sortable position field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                }
                $config['position'] = $field;
            }
            $config = $this->read_sortable_groups($mapping['fields'], $config);
        }
        if (isset($mapping['manyToOne'])) {
            $config = $this->read_sortable_groups($mapping['manyToOne'], $config);
        }
        if (isset($mapping['manyToMany'])) {
            $config = $this->read_sortable_groups($mapping['manyToMany'], $config);
        }
        if (!$meta->is_mapped_superclass && $config) {
            if (!isset($config['position'])) {
                throw new Invalid_Mapping_Exception("Missing property: 'position' in class - {$meta->get_name()}");
            }
        }
        return $config;
    }
    protected function _load_mapping_file($file)
    {
        return \Symfony\Component\Yaml\Yaml::parse(file_get_contents($file));
    }
    /**
     * Checks if $field type is valid as SortablePosition field
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    protected function is_valid_field($meta, $field): bool
    {
        $mapping = $meta->get_field_mapping($field);
        return $mapping && in_array($mapping->type ?? $mapping['type'], self::VALID_TYPES, true);
    }
    /**
     * @param iterable<string, array<string, mixed>> $mapping
     * @param array<string, mixed>                   $config
     *
     * @return array<string, mixed>
     */
    private function read_sortable_groups(iterable $mapping, array $config): array
    {
        foreach ($mapping as $field => $field_mapping) {
            if (!isset($field_mapping['gedmo'])) {
                continue;
            }
            if (!in_array('sortableGroup', $field_mapping['gedmo'], true)) {
                continue;
            }
            if (!isset($config['groups'])) {
                $config['groups'] = [];
            }
            $config['groups'][] = $field;
        }
        return $config;
    }
}