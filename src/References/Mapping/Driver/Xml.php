<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\References\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver\Xml as BaseXml;
/**
 * This is a xml mapping driver for References
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for References
 * extension.
 *
 * @author Aram Alipoor <aram.alipoor@gmail.com>
 *
 * @internal
 */
class Xml extends Base_Xml
{
    /**
     * @var string[]
     */
    private const VALID_TYPES = ['document', 'entity'];
    /**
     * @var string[]
     */
    private array $valid_references = ['referenceOne', 'referenceMany', 'referenceManyEmbed'];
    public function read_extended_metadata($meta, array &$config): array
    {
        /**
         * @var \SimpleXmlElement
         */
        $xml = $this->_get_mapping($meta->get_name());
        $xml_doctrine = $xml;
        $xml = $xml->children(self::GEDMO_NAMESPACE_URI);
        if (in_array($xml_doctrine->get_name(), ['mapped-superclass', 'entity', 'document'], true)) {
            if (isset($xml->reference)) {
                foreach ($xml->reference as $element) {
                    if (!$this->_is_attribute_set($element, 'type')) {
                        throw new Invalid_Mapping_Exception("Reference type (document or entity) is not set in class - {$meta->get_name()}");
                    }
                    $type = $this->_get_attribute($element, 'type');
                    if (!in_array($type, self::VALID_TYPES, true)) {
                        throw new Invalid_Mapping_Exception($type . ' is not a valid reference type, valid types are: ' . implode(', ', self::VALID_TYPES));
                    }
                    $reference = $this->_get_attribute($element, 'reference');
                    if (!in_array($reference, $this->valid_references, true)) {
                        throw new Invalid_Mapping_Exception($reference . ' is not a valid reference, valid references are: ' . implode(', ', $this->valid_references));
                    }
                    if (!$this->_is_attribute_set($element, 'field')) {
                        throw new Invalid_Mapping_Exception("Reference field is not set in class - {$meta->get_name()}");
                    }
                    $field = $this->_get_attribute($element, 'field');
                    if (!$this->_is_attribute_set($element, 'class')) {
                        throw new Invalid_Mapping_Exception("Reference field is not set in class - {$meta->get_name()}");
                    }
                    $class = $this->_get_attribute($element, 'class');
                    if (!$this->_is_attribute_set($element, 'identifier')) {
                        throw new Invalid_Mapping_Exception("Reference identifier is not set in class - {$meta->get_name()}");
                    }
                    $identifier = $this->_get_attribute($element, 'identifier');
                    $config[$reference][$field] = ['field' => $field, 'type' => $type, 'class' => $class, 'identifier' => $identifier];
                    if ($this->_is_attribute_set($element, 'mappedBy')) {
                        $config[$reference][$field]['mappedBy'] = $this->_get_attribute($element, 'mappedBy');
                    }
                    if ($this->_is_attribute_set($element, 'inversedBy')) {
                        $config[$reference][$field]['inversedBy'] = $this->_get_attribute($element, 'inversedBy');
                    }
                }
            }
        }
        return $config;
    }
}