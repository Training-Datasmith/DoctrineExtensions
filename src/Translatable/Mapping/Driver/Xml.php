<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Mapping\Driver;

use Doctrine\ORM\Mapping\Embedded_Class_Mapping;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver\Xml as BaseXml;
/**
 * This is a xml mapping driver for Translatable
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for Translatable
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
     * @return mixed[]
     */
    public function read_extended_metadata($meta, array &$config): array
    {
        /**
         * @var \SimpleXmlElement
         */
        $xml = $this->_get_mapping($meta->get_name());
        $xml_doctrine = $xml;
        $xml = $xml->children(self::GEDMO_NAMESPACE_URI);
        if ('entity' === $xml_doctrine->get_name() || 'mapped-superclass' === $xml_doctrine->get_name()) {
            if ($xml->count() && isset($xml->translation)) {
                /**
                 * @var \SimpleXmlElement
                 */
                $data = $xml->translation;
                if ($this->_is_attribute_set($data, 'locale')) {
                    $config['locale'] = $this->_get_attribute($data, 'locale');
                } elseif ($this->_is_attribute_set($data, 'language')) {
                    $config['locale'] = $this->_get_attribute($data, 'language');
                }
                if ($this->_is_attribute_set($data, 'entity')) {
                    $entity = $this->_get_attribute($data, 'entity');
                    if (!$cl = $this->get_related_class_name($meta, $entity)) {
                        throw new Invalid_Mapping_Exception("Translation entity class: {$entity} does not exist.");
                    }
                    $config['translationClass'] = $cl;
                }
            }
        }
        if (property_exists($meta, 'embeddedClasses') && $meta->embedded_classes) {
            foreach ($meta->embedded_classes as $property_name => $embedded_class_info) {
                if ($meta->is_inherited_embedded_class($property_name)) {
                    continue;
                }
                /** Remove conditional when ORM 2.x is no longer supported. */
                $class_name = $embedded_class_info instanceof Embedded_Class_Mapping ? $embedded_class_info->class : $embedded_class_info['class'];
                $xml_embedded_class = $this->_get_mapping($class_name);
                $config = $this->inspect_elements_for_translatable_fields($xml_embedded_class, $config, $property_name);
            }
        }
        if ($xml_doctrine->{'attribute-overrides'}->count() > 0) {
            foreach ($xml_doctrine->{'attribute-overrides'}->{'attribute-override'} as $override_mapping) {
                $config = $this->build_field_configuration($this->_get_attribute($override_mapping, 'name'), $override_mapping->field, $config);
            }
        }
        $config = $this->inspect_elements_for_translatable_fields($xml_doctrine, $config);
        if (!$meta->is_mapped_superclass && $config) {
            if (is_array($meta->get_identifier()) && count($meta->get_identifier()) > 1) {
                throw new Invalid_Mapping_Exception("Translatable does not support composite identifiers in class - {$meta->get_name()}");
            }
        }
        return $config;
    }
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function inspect_elements_for_translatable_fields(\Simple_Xml_Element $xml, array $config, ?string $prefix = null): array
    {
        if (!isset($xml->field)) {
            return $config;
        }
        foreach ($xml->field as $mapping) {
            $mapping_doctrine = $mapping;
            $field_name = $this->_get_attribute($mapping_doctrine, 'name');
            if (null !== $prefix) {
                $field_name = $prefix . '.' . $field_name;
            }
            $config = $this->build_field_configuration($field_name, $mapping, $config);
        }
        return $config;
    }
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function build_field_configuration(string $field_name, \Simple_Xml_Element $mapping, array $config): array
    {
        $mapping = $mapping->children(self::GEDMO_NAMESPACE_URI);
        if ($mapping->count() > 0 && isset($mapping->translatable)) {
            $config['fields'][] = $field_name;
            /** @var \SimpleXmlElement $data */
            $data = $mapping->translatable;
            if ($this->_is_attribute_set($data, 'fallback')) {
                $config['fallback'][$field_name] = $this->_get_boolean_attribute($data, 'fallback');
            }
        }
        return $config;
    }
}