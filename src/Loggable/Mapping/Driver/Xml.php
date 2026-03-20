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
use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver\Xml as BaseXml;
/**
 * This is a xml mapping driver for Loggable
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for Loggable
 * extension.
 *
 * @author Boussekeyt Jules <jules.boussekeyt@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 *
 * @internal
 */
class Xml extends Base_Xml
{
    public function read_extended_metadata($meta, array &$config)
    {
        /**
         * @var \SimpleXmlElement
         */
        $xml = $this->_get_mapping($meta->get_name());
        $xml_doctrine = $xml;
        $xml = $xml->children(self::GEDMO_NAMESPACE_URI);
        if (in_array($xml_doctrine->get_name(), ['mapped-superclass', 'entity', 'document'], true)) {
            if (isset($xml->loggable)) {
                /**
                 * @var \SimpleXMLElement
                 */
                $data = $xml->loggable;
                $config['loggable'] = true;
                if ($this->_is_attribute_set($data, 'log-entry-class')) {
                    $class = $this->_get_attribute($data, 'log-entry-class');
                    if (!$cl = $this->get_related_class_name($meta, $class)) {
                        throw new Invalid_Mapping_Exception("LogEntry class: {$class} does not exist.");
                    }
                    $config['logEntryClass'] = $cl;
                }
            }
        }
        if (isset($xml_doctrine->field)) {
            $config = $this->inspect_element_for_versioned($xml_doctrine->field, $config, $meta);
        }
        foreach ($xml_doctrine->{'attribute-overrides'}->{'attribute-override'} ?? [] as $override_mapping) {
            $config = $this->inspect_element_for_versioned($override_mapping, $config, $meta);
        }
        if (isset($xml_doctrine->{'many-to-one'})) {
            $config = $this->inspect_element_for_versioned($xml_doctrine->{'many-to-one'}, $config, $meta);
        }
        if (isset($xml_doctrine->{'one-to-one'})) {
            $config = $this->inspect_element_for_versioned($xml_doctrine->{'one-to-one'}, $config, $meta);
        }
        if (isset($xml_doctrine->{'reference-one'})) {
            $config = $this->inspect_element_for_versioned($xml_doctrine->{'reference-one'}, $config, $meta);
        }
        if (isset($xml_doctrine->{'embedded'})) {
            $config = $this->inspect_element_for_versioned($xml_doctrine->{'embedded'}, $config, $meta);
        }
        if (!$meta->is_mapped_superclass && $config) {
            if ($meta instanceof Class_Metadata_Odm && count($meta->get_identifier()) > 1) {
                throw new Invalid_Mapping_Exception("Loggable does not support composite identifiers in class - {$meta->get_name()}");
            }
            if (isset($config['versioned']) && !isset($config['loggable'])) {
                throw new Invalid_Mapping_Exception("Class must be annotated with Loggable annotation in order to track versioned fields in class - {$meta->get_name()}");
            }
        }
        return $config;
    }
    /**
     * Searches mappings on element for versioned fields
     *
     * @param array<string, mixed>  $config
     * @param ClassMetadata<object> $meta
     *
     * @return array<string, mixed>
     */
    private function inspect_element_for_versioned(\Simple_Xml_Element $element, array $config, Class_Metadata $meta): array
    {
        foreach ($element as $mapping) {
            $mapping_doctrine = $mapping;
            /**
             * @var \SimpleXmlElement
             */
            $mapping = $mapping->children(self::GEDMO_NAMESPACE_URI);
            $is_assoc = $this->_is_attribute_set($mapping_doctrine, 'field');
            $field = $this->_get_attribute($mapping_doctrine, $is_assoc ? 'field' : 'name');
            if (isset($mapping->versioned)) {
                if ($is_assoc && !$meta->association_mappings[$field]['isOwningSide']) {
                    throw new Invalid_Mapping_Exception("Cannot version [{$field}] as it is not the owning side in object - {$meta->get_name()}");
                }
                $config['versioned'][] = $field;
            }
        }
        return $config;
    }
}