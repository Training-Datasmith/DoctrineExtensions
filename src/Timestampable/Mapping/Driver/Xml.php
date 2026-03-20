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
use Gedmo\Mapping\Driver\Xml as BaseXml;
/**
 * This is a xml mapping driver for Timestampable
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for Timestampable
 * extension.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 *
 * @internal
 */
class Xml extends Base_Xml
{
    /**
     * List of types which are valid for timestamp
     *
     * @var string[]
     */
    private const VALID_TYPES = ['date', 'date_immutable', 'time', 'time_immutable', 'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'timestamp', 'vardatetime', 'integer'];
    public function read_extended_metadata($meta, array &$config): array
    {
        /**
         * @var \SimpleXmlElement
         */
        $mapping = $this->_get_mapping($meta->get_name());
        if (isset($mapping->field)) {
            foreach ($mapping->field as $field_mapping) {
                $field_mapping_doctrine = $field_mapping;
                $field_mapping = $field_mapping->children(self::GEDMO_NAMESPACE_URI);
                if (isset($field_mapping->timestampable)) {
                    /**
                     * @var \SimpleXmlElement
                     */
                    $data = $field_mapping->timestampable;
                    $field = $this->_get_attribute($field_mapping_doctrine, 'name');
                    if (!$this->is_valid_field($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Field - [{$field}] type is not valid and must be 'date', 'datetime' or 'time' in class - {$meta->get_name()}");
                    }
                    if (!$this->_is_attribute_set($data, 'on') || !in_array($this->_get_attribute($data, 'on'), ['update', 'create', 'change'], true)) {
                        throw new Invalid_Mapping_Exception("Field - [{$field}] trigger 'on' is not one of [update, create, change] in class - {$meta->get_name()}");
                    }
                    if ('change' === $this->_get_attribute($data, 'on')) {
                        if (!$this->_is_attribute_set($data, 'field')) {
                            throw new Invalid_Mapping_Exception("Missing parameters on property - {$field}, field must be set on [change] trigger in class - {$meta->get_name()}");
                        }
                        $tracked_field_attribute = $this->_get_attribute($data, 'field');
                        $value_attribute = $this->_is_attribute_set($data, 'value') ? $this->_get_attribute($data, 'value') : null;
                        $field = ['field' => $field, 'trackedField' => $tracked_field_attribute, 'value' => $value_attribute];
                    }
                    $config[$this->_get_attribute($data, 'on')][] = $field;
                }
            }
        }
        return $config;
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