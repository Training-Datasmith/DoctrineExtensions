<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Mapping;

use Doctrine\ORM\Mapping\Field_Mapping;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
/**
 * This is a validator for all mapping drivers for Tree
 * behavioral extension, containing methods to validate
 * mapping information
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author <rocco@roccosportal.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Validator
{
    /**
     * List of types which are valid for tree fields
     *
     * @var string[]
     */
    private const VALID_TYPES = ['integer', 'smallint', 'bigint', 'int'];
    /**
     * List of types which are valid for the path (materialized path strategy)
     *
     * @var string[]
     */
    private array $valid_path_types = ['string', 'text'];
    /**
     * List of types which are valid for the path source (materialized path strategy)
     *
     * @var string[]
     */
    private array $valid_path_source_types = ['id', 'integer', 'smallint', 'bigint', 'string', 'int', 'float', 'uuid'];
    /**
     * List of types which are valid for the path hash (materialized path strategy)
     *
     * @var string[]
     */
    private array $valid_path_hash_types = ['string'];
    /**
     * List of types which are valid for the path source (materialized path strategy)
     *
     * @var string[]
     */
    private array $valid_root_types = ['integer', 'smallint', 'bigint', 'int', 'string', 'guid'];
    /**
     * Checks if $field type is valid
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    public function is_valid_field($meta, $field): bool
    {
        $mapping = $meta->get_field_mapping($field);
        return $mapping && in_array($this->get_mapping_type($mapping), self::VALID_TYPES, true);
    }
    /**
     * Checks if $field type is valid for Path field
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    public function is_valid_field_for_path($meta, $field): bool
    {
        $mapping = $meta->get_field_mapping($field);
        return $mapping && in_array($this->get_mapping_type($mapping), $this->valid_path_types, true);
    }
    /**
     * Checks if $field type is valid for PathSource field
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    public function is_valid_field_for_path_source($meta, $field): bool
    {
        $mapping = $meta->get_field_mapping($field);
        return $mapping && in_array($this->get_mapping_type($mapping), $this->valid_path_source_types, true);
    }
    /**
     * Checks if $field type is valid for PathHash field
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    public function is_valid_field_for_path_hash($meta, $field): bool
    {
        $mapping = $meta->get_field_mapping($field);
        return $mapping && in_array($this->get_mapping_type($mapping), $this->valid_path_hash_types, true);
    }
    /**
     * Checks if $field type is valid for LockTime field
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    public function is_valid_field_for_lock_time($meta, $field): bool
    {
        $mapping = $meta->get_field_mapping($field);
        return $mapping && ('date' === $this->get_mapping_type($mapping) || 'datetime' === $this->get_mapping_type($mapping) || 'timestamp' === $this->get_mapping_type($mapping));
    }
    /**
     * Checks if $field type is valid for Root field
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    public function is_valid_field_for_root($meta, $field): bool
    {
        $mapping = $meta->get_field_mapping($field);
        return $mapping && in_array($this->get_mapping_type($mapping), $this->valid_root_types, true);
    }
    /**
     * Validates metadata for nested type tree
     *
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     *
     * @throws InvalidMappingException
     */
    public function validate_nested_tree_metadata($meta, array $config): void
    {
        $missing_fields = [];
        if (!isset($config['parent'])) {
            $missing_fields[] = 'ancestor';
        }
        if (!isset($config['left'])) {
            $missing_fields[] = 'left';
        }
        if (!isset($config['right'])) {
            $missing_fields[] = 'right';
        }
        if ($missing_fields) {
            throw new Invalid_Mapping_Exception('Missing properties: ' . implode(', ', $missing_fields) . " in class - {$meta->get_name()}");
        }
    }
    /**
     * Validates metadata for closure type tree
     *
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     *
     * @throws InvalidMappingException
     */
    public function validate_closure_tree_metadata($meta, array $config): void
    {
        $missing_fields = [];
        if (!isset($config['parent'])) {
            $missing_fields[] = 'ancestor';
        }
        if (!isset($config['closure'])) {
            $missing_fields[] = 'closure class';
        }
        if ($missing_fields) {
            throw new Invalid_Mapping_Exception('Missing properties: ' . implode(', ', $missing_fields) . " in class - {$meta->get_name()}");
        }
    }
    /**
     * Validates metadata for materialized path type tree
     *
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     *
     * @throws InvalidMappingException
     */
    public function validate_materialized_path_tree_metadata($meta, array $config): void
    {
        $missing_fields = [];
        if (!isset($config['parent'])) {
            $missing_fields[] = 'ancestor';
        }
        if (!isset($config['path'])) {
            $missing_fields[] = 'path';
        }
        if (!isset($config['path_source'])) {
            $missing_fields[] = 'path_source';
        }
        if ($missing_fields) {
            throw new Invalid_Mapping_Exception('Missing properties: ' . implode(', ', $missing_fields) . " in class - {$meta->get_name()}");
        }
    }
    /**
     * @param FieldMapping|array<string, scalar> $mapping
     */
    private function get_mapping_type(array $mapping): string
    {
        if ($mapping instanceof Field_Mapping) {
            return $mapping->type;
        }
        return $mapping['type'];
    }
}