<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sluggable\Mapping\Driver;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver;
use Gedmo\Mapping\Driver\File;
/**
 * This is a yaml mapping driver for Sluggable
 * behavioral extension. Used for extraction of extended
 * metadata from yaml specifically for Sluggable
 * extension.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @deprecated since gedmo/doctrine-extensions 3.5, will be removed in version 4.0.
 *
 * @internal
 */
class Yaml extends File implements Driver
{
    /**
     * List of types which are valid for slug and sluggable fields
     *
     * @var string[]
     */
    private const VALID_TYPES = ['string', 'text', 'integer', 'int', 'datetime', 'citext'];
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
                $config = $this->build_field_configuration($field, $field_mapping, $meta, $config);
            }
        }
        if (isset($mapping['attributeOverride'])) {
            foreach ($mapping['attributeOverride'] as $field => $override_mapping) {
                $config = $this->build_field_configuration($field, $override_mapping, $meta, $config);
            }
        }
        return $config;
    }
    protected function _load_mapping_file($file)
    {
        return \Symfony\Component\Yaml\Yaml::parse(file_get_contents($file));
    }
    /**
     * Checks if $field type is valid as Sluggable field
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
     * @param array<string, mixed>  $fieldMapping
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     *
     * @return array<string, mixed>
     */
    private function build_field_configuration(string $field, array $field_mapping, Class_Metadata $meta, array $config): array
    {
        if (isset($field_mapping['gedmo'])) {
            if (isset($field_mapping['gedmo']['slug'])) {
                $slug = $field_mapping['gedmo']['slug'];
                if (!$this->is_valid_field($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Cannot use field - [{$field}] for slug storage, type is not valid and must be 'string' or 'text' in class - {$meta->get_name()}");
                }
                // process slug handlers
                $handlers = [];
                if (isset($slug['handlers'])) {
                    foreach ($slug['handlers'] as $handler_class => $options) {
                        if (!strlen($handler_class)) {
                            throw new Invalid_Mapping_Exception("SlugHandler class: {$handler_class} should be a valid class name in entity - {$meta->get_name()}");
                        }
                        $handlers[$handler_class] = $options;
                        $handler_class::validate($handlers[$handler_class], $meta);
                    }
                }
                // process slug fields
                if (empty($slug['fields']) || !is_array($slug['fields'])) {
                    throw new Invalid_Mapping_Exception("Slug must contain at least one field for slug generation in class - {$meta->get_name()}");
                }
                foreach ($slug['fields'] as $slug_field) {
                    if (!$meta->has_field($slug_field)) {
                        throw new Invalid_Mapping_Exception("Unable to find slug [{$slug_field}] as mapped property in entity - {$meta->get_name()}");
                    }
                    if (!$this->is_valid_field($meta, $slug_field)) {
                        throw new Invalid_Mapping_Exception("Cannot use field - [{$slug_field}] for slug storage, type is not valid and must be 'string' or 'text' in class - {$meta->get_name()}");
                    }
                }
                $config['slugs'][$field]['fields'] = $slug['fields'];
                $config['slugs'][$field]['handlers'] = $handlers;
                $config['slugs'][$field]['slug'] = $field;
                $config['slugs'][$field]['style'] = isset($slug['style']) ? (string) $slug['style'] : 'default';
                $config['slugs'][$field]['dateFormat'] = isset($slug['dateFormat']) ? (string) $slug['dateFormat'] : 'Y-m-d-H:i';
                $config['slugs'][$field]['updatable'] = isset($slug['updatable']) ? (bool) $slug['updatable'] : true;
                $config['slugs'][$field]['unique'] = isset($slug['unique']) ? (bool) $slug['unique'] : true;
                $config['slugs'][$field]['unique_base'] = $slug['unique_base'] ?? null;
                $config['slugs'][$field]['separator'] = isset($slug['separator']) ? (string) $slug['separator'] : '-';
                $config['slugs'][$field]['prefix'] = isset($slug['prefix']) ? (string) $slug['prefix'] : '';
                $config['slugs'][$field]['suffix'] = isset($slug['suffix']) ? (string) $slug['suffix'] : '';
                if (!$meta->is_mapped_superclass && $meta->is_identifier($field) && !$config['slugs'][$field]['unique']) {
                    throw new Invalid_Mapping_Exception("Identifier field - [{$field}] slug must be unique in order to maintain primary key in class - {$meta->get_name()}");
                }
                $ubase = $config['slugs'][$field]['unique_base'];
                if (false === $config['slugs'][$field]['unique'] && $ubase) {
                    throw new Invalid_Mapping_Exception("Slug annotation [unique_base] can not be set if unique is unset or 'false'");
                }
                if ($ubase && !$meta->has_field($ubase) && !$meta->has_association($ubase)) {
                    throw new Invalid_Mapping_Exception("Unable to find [{$ubase}] as mapped property in entity - {$meta->get_name()}");
                }
            }
        }
        return $config;
    }
}