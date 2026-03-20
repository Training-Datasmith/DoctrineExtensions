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
use Gedmo\Mapping\Driver\Xml as BaseXml;
/**
 * This is a xml mapping driver for Sluggable
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for Sluggable
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
     * List of types which are valid for slug and sluggable fields
     *
     * @var string[]
     */
    private const VALID_TYPES = ['string', 'text', 'integer', 'int', 'datetime', 'citext'];
    public function read_extended_metadata($meta, array &$config)
    {
        /**
         * @var \SimpleXmlElement
         */
        $xml = $this->_get_mapping($meta->get_name());
        if (isset($xml->field)) {
            foreach ($xml->field as $mapping) {
                $field = $this->_get_attribute($mapping, 'name');
                $config = $this->build_field_configuration($meta, $field, $mapping, $config);
            }
        }
        if (isset($xml->{'attribute-overrides'})) {
            foreach ($xml->{'attribute-overrides'}->{'attribute-override'} as $mapping) {
                $field = $this->_get_attribute($mapping, 'name');
                $config = $this->build_field_configuration($meta, $field, $mapping->field, $config);
            }
        }
        return $config;
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
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     *
     * @return array<string, mixed>
     */
    private function build_field_configuration(Class_Metadata $meta, string $field, \Simple_Xml_Element $mapping, array $config): array
    {
        /**
         * @var \SimpleXmlElement
         */
        $mapping = $mapping->children(self::GEDMO_NAMESPACE_URI);
        if (isset($mapping->slug)) {
            /**
             * @var \SimpleXmlElement
             */
            $slug = $mapping->slug;
            if (!$this->is_valid_field($meta, $field)) {
                throw new Invalid_Mapping_Exception("Cannot use field - [{$field}] for slug storage, type is not valid and must be 'string' in class - {$meta->get_name()}");
            }
            $fields = array_map('trim', explode(',', (string) $this->_get_attribute($slug, 'fields')));
            foreach ($fields as $slug_field) {
                if (!$meta->has_field($slug_field)) {
                    throw new Invalid_Mapping_Exception("Unable to find slug [{$slug_field}] as mapped property in entity - {$meta->get_name()}");
                }
                if (!$this->is_valid_field($meta, $slug_field)) {
                    throw new Invalid_Mapping_Exception("Cannot use field - [{$slug_field}] for slug storage, type is not valid and must be 'string' or 'text' in class - {$meta->get_name()}");
                }
            }
            $handlers = [];
            if (isset($slug->handler)) {
                foreach ($slug->handler as $handler) {
                    $class = (string) $this->_get_attribute($handler, 'class');
                    $handlers[$class] = [];
                    foreach ($handler->{'handler-option'} as $option) {
                        $handlers[$class][(string) $this->_get_attribute($option, 'name')] = (string) $this->_get_attribute($option, 'value');
                    }
                    $class::validate($handlers[$class], $meta);
                }
            }
            // set all options
            $config['slugs'][$field] = ['fields' => $fields, 'slug' => $field, 'style' => $this->_is_attribute_set($slug, 'style') ? $this->_get_attribute($slug, 'style') : 'default', 'updatable' => $this->_is_attribute_set($slug, 'updatable') ? $this->_get_boolean_attribute($slug, 'updatable') : true, 'dateFormat' => $this->_is_attribute_set($slug, 'dateFormat') ? $this->_get_attribute($slug, 'dateFormat') : 'Y-m-d-H:i', 'unique' => $this->_is_attribute_set($slug, 'unique') ? $this->_get_boolean_attribute($slug, 'unique') : true, 'unique_base' => $this->_is_attribute_set($slug, 'unique-base') ? $this->_get_attribute($slug, 'unique-base') : null, 'separator' => $this->_is_attribute_set($slug, 'separator') ? $this->_get_attribute($slug, 'separator') : '-', 'prefix' => $this->_is_attribute_set($slug, 'prefix') ? $this->_get_attribute($slug, 'prefix') : '', 'suffix' => $this->_is_attribute_set($slug, 'suffix') ? $this->_get_attribute($slug, 'suffix') : '', 'handlers' => $handlers, 'uniqueOverTranslations' => $this->_is_attribute_set($slug, 'uniqueOverTranslations') && $this->_get_boolean_attribute($slug, 'uniqueOverTranslations')];
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
        return $config;
    }
}