<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Blameable\Mapping\Driver;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver\Xml as BaseXml;
/**
 * This is a xml mapping driver for Blameable
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for Blameable
 * extension.
 *
 * @author David Buchmann <mail@davidbu.ch>
 *
 * @internal
 */
class Xml extends Base_Xml
{
    /**
     * List of types which are valid for blame
     *
     * @var string[]
     */
    private const VALID_TYPES = ['one', 'string', 'int', 'ulid', 'uuid'];
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
                if (isset($field_mapping->blameable)) {
                    /**
                     * @var \SimpleXmlElement
                     */
                    $data = $field_mapping->blameable;
                    $field = $this->_get_attribute($field_mapping_doctrine, 'name');
                    if (!$this->is_valid_field($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Field - [{$field}] type is not valid and must be 'string' or a reference in class - {$meta->get_name()}");
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
        if (isset($mapping->{'many-to-one'})) {
            foreach ($mapping->{'many-to-one'} as $field_mapping) {
                $field = $this->_get_attribute($field_mapping, 'field');
                $field_mapping = $field_mapping->children(self::GEDMO_NAMESPACE_URI);
                if (isset($field_mapping->blameable)) {
                    $data = $field_mapping->blameable;
                    if (!$meta->is_single_valued_association($field)) {
                        throw new Invalid_Mapping_Exception("Association - [{$field}] is not valid, it must be a one-to-many relation or a string field - {$meta->get_name()}");
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