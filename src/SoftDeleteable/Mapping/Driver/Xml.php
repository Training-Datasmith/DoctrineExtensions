<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver\Xml as BaseXml;
use Gedmo\Soft_Deleteable\Mapping\Validator;
/**
 * This is a xml mapping driver for SoftDeleteable
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for SoftDeleteable
 * extension.
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 *
 * @internal
 */
class Xml extends Base_Xml
{
    public function read_extended_metadata($meta, array &$config): array
    {
        /**
         * @var \SimpleXmlElement
         */
        $xml = $this->_get_mapping($meta->get_name());
        $xml_doctrine = $xml;
        $xml = $xml->children(self::GEDMO_NAMESPACE_URI);
        if (in_array($xml_doctrine->get_name(), ['mapped-superclass', 'entity', 'document', 'embedded-document'], true)) {
            if (isset($xml->{'soft-deleteable'})) {
                $field = $this->_get_attribute($xml->{'soft-deleteable'}, 'field-name');
                if (!$field) {
                    throw new Invalid_Mapping_Exception('Field name for SoftDeleteable class is mandatory.');
                }
                Validator::validate_field($meta, $field);
                $config['softDeleteable'] = true;
                $config['fieldName'] = $field;
                $config['timeAware'] = false;
                if ($this->_is_attribute_set($xml->{'soft-deleteable'}, 'time-aware')) {
                    $config['timeAware'] = $this->_get_boolean_attribute($xml->{'soft-deleteable'}, 'time-aware');
                }
                $config['hardDelete'] = true;
                if ($this->_is_attribute_set($xml->{'soft-deleteable'}, 'hard-delete')) {
                    $config['hardDelete'] = $this->_get_boolean_attribute($xml->{'soft-deleteable'}, 'hard-delete');
                }
            }
        }
        return $config;
    }
}