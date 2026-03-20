<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
/**
 * The mapping XmlDriver abstract class, defines the
 * metadata extraction function common among all
 * all drivers used on these extensions by file based
 * drivers.
 *
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
abstract class Xml extends File
{
    public const GEDMO_NAMESPACE_URI = 'http://gediminasm.org/schemas/orm/doctrine-extensions-mapping';
    public const DOCTRINE_NAMESPACE_URI = 'http://doctrine-project.org/schemas/orm/doctrine-mapping';
    /**
     * File extension
     *
     * @var string
     */
    protected $_extension = '.dcm.xml';
    /**
     * Get attribute value.
     * As we are supporting namespaces the only way to get to the attributes under a node is to use attributes function on it
     *
     * @param string $attributeName
     *
     * @return string
     */
    protected function _get_attribute(\Simple_Xml_Element $node, $attribute_name)
    {
        $attributes = $node->attributes();
        return (string) $attributes[$attribute_name];
    }
    /**
     * Get boolean attribute value.
     * As we are supporting namespaces the only way to get to the attributes under a node is to use attributes function on it
     *
     *
     * @return bool
     */
    protected function _get_boolean_attribute(\Simple_Xml_Element $node, string $attribute_name)
    {
        $raw_value = strtolower($this->_get_attribute($node, $attribute_name));
        if ('1' === $raw_value || 'true' === $raw_value) {
            return true;
        }
        if ('0' === $raw_value || 'false' === $raw_value) {
            return false;
        }
        throw new Invalid_Mapping_Exception(sprintf("Attribute %s must have a valid boolean value, '%s' found", $attribute_name, $this->_get_attribute($node, $attribute_name)));
    }
    /**
     * does attribute exist under a specific node
     * As we are supporting namespaces the only way to get to the attributes under a node is to use attributes function on it
     *
     * @param string $attributeName
     *
     * @return bool
     */
    protected function _is_attribute_set(\Simple_Xml_Element $node, $attribute_name)
    {
        $attributes = $node->attributes();
        return isset($attributes[$attribute_name]);
    }
    protected function _load_mapping_file($file)
    {
        $result = [];
        // We avoid calling `simplexml_load_file()` in order to prevent file operations in libXML.
        // If `libxml_disable_entity_loader(true)` is called before, `simplexml_load_file()` fails,
        // that's why we use `simplexml_load_string()` instead.
        // @see https://bugs.php.net/bug.php?id=62577.
        $xml_element = simplexml_load_string(file_get_contents($file));
        $xml_element = $xml_element->children(self::DOCTRINE_NAMESPACE_URI);
        if (isset($xml_element->entity)) {
            foreach ($xml_element->entity as $entity_element) {
                $entity_name = $this->_get_attribute($entity_element, 'name');
                $result[$entity_name] = $entity_element;
            }
        } elseif (isset($xml_element->{'mapped-superclass'})) {
            foreach ($xml_element->{'mapped-superclass'} as $mapped_super_class) {
                $class_name = $this->_get_attribute($mapped_super_class, 'name');
                $result[$class_name] = $mapped_super_class;
            }
        }
        return $result;
    }
}