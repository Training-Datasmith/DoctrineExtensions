<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Timestampable\Mapping\Driver;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver;
use Gedmo\Mapping\Driver\File;
/**
 * This is a yaml mapping driver for Timestampable
 * behavioral extension. Used for extraction of extended
 * metadata from yaml specifically for Timestampable
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
     * List of types which are valid for timestamp
     *
     * @var string[]
     */
    private const VALID_TYPES = ['date', 'date_immutable', 'time', 'time_immutable', 'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'timestamp', 'vardatetime', 'integer'];
    /**
     * File extension
     *
     * @var string
     */
    protected $_extension = '.dcm.yml';
    public function read_extended_metadata($meta, array &$config): array
    {
        $mapping = $this->_get_mapping($meta->get_name());
        if (isset($mapping['fields'])) {
            foreach ($mapping['fields'] as $field => $field_mapping) {
                if (isset($field_mapping['gedmo']['timestampable'])) {
                    $mapping_property = $field_mapping['gedmo']['timestampable'];
                    if (!$this->is_valid_field($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Field - [{$field}] type is not valid and must be 'date', 'datetime' or 'time' in class - {$meta->get_name()}");
                    }
                    if (!isset($mapping_property['on']) || !in_array($mapping_property['on'], ['update', 'create', 'change'], true)) {
                        throw new Invalid_Mapping_Exception("Field - [{$field}] trigger 'on' is not one of [update, create, change] in class - {$meta->get_name()}");
                    }
                    if ('change' === $mapping_property['on']) {
                        if (!isset($mapping_property['field'])) {
                            throw new Invalid_Mapping_Exception("Missing parameters on property - {$field}, field must be set on [change] trigger in class - {$meta->get_name()}");
                        }
                        $tracked_field_attribute = $mapping_property['field'];
                        $value_attribute = $mapping_property['value'] ?? null;
                        if (is_array($tracked_field_attribute) && null !== $value_attribute) {
                            throw new Invalid_Mapping_Exception('Timestampable extension does not support multiple value changeset detection yet.');
                        }
                        $field = ['field' => $field, 'trackedField' => $tracked_field_attribute, 'value' => $value_attribute];
                    }
                    $config[$mapping_property['on']][] = $field;
                }
            }
        }
        return $config;
    }
    protected function _load_mapping_file($file)
    {
        return \Symfony\Component\Yaml\Yaml::parse(file_get_contents($file));
    }
    /**
     * Checks if $field type is valid
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    protected function is_valid_field($meta, $field): bool
    {
        $mapping = $meta->get_field_mapping($field);
        return $mapping && in_array($mapping->type ?? $mapping['type'], self::VALID_TYPES, true);
    }
}