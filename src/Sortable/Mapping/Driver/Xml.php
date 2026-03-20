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
use Gedmo\Mapping\Driver\Xml as BaseXml;
/**
 * This is a xml mapping driver for Sortable
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for Sortable
 * extension.
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @internal
 */
class Xml extends Base_Xml
{
    /**
     * List of types which are valid for position field
     *
     * @var string[]
     */
    private const VALID_TYPES = ['int', 'integer', 'smallint', 'bigint'];
    public function read_extended_metadata($meta, array &$config)
    {
        /**
         * @var \SimpleXmlElement
         */
        $xml = $this->_get_mapping($meta->get_name());
        if (isset($xml->field)) {
            foreach ($xml->field as $mapping_doctrine) {
                $mapping = $mapping_doctrine->children(self::GEDMO_NAMESPACE_URI);
                $field = $this->_get_attribute($mapping_doctrine, 'name');
                if (isset($mapping->{'sortable-position'})) {
                    if (!$this->is_valid_field($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Sortable position field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                    }
                    $config['position'] = $field;
                }
            }
            $config = $this->read_sortable_groups($xml->field, $config, 'name');
        }
        // Search for sortable-groups in association mappings
        if (isset($xml->{'many-to-one'})) {
            $config = $this->read_sortable_groups($xml->{'many-to-one'}, $config);
        }
        // Search for sortable-groups in association mappings
        if (isset($xml->{'many-to-many'})) {
            $config = $this->read_sortable_groups($xml->{'many-to-many'}, $config);
        }
        if (!$meta->is_mapped_superclass && $config) {
            if (!isset($config['position'])) {
                throw new Invalid_Mapping_Exception("Missing property: 'position' in class - {$meta->get_name()}");
            }
        }
        return $config;
    }
    /**
     * Checks if $field type is valid as Sortable Position field
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
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function read_sortable_groups(\Simple_Xml_Element $mapping, array $config, string $field_attr = 'field'): array
    {
        foreach ($mapping as $mapping_doctrine) {
            $map = $mapping_doctrine->children(self::GEDMO_NAMESPACE_URI);
            $field = $this->_get_attribute($mapping_doctrine, $field_attr);
            if (isset($map->{'sortable-group'})) {
                if (!isset($config['groups'])) {
                    $config['groups'] = [];
                }
                $config['groups'][] = $field;
            }
        }
        return $config;
    }
}