<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Uploadable\Mapping\Driver;

use Gedmo\Mapping\Driver\Xml as BaseXml;
use Gedmo\Uploadable\Mapping\Validator;
/**
 * This is a xml mapping driver for Uploadable
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for Uploadable
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
    public function read_extended_metadata($meta, array &$config)
    {
        /**
         * @var \SimpleXmlElement
         */
        $xml = $this->_get_mapping($meta->get_name());
        $xml_doctrine = $xml;
        $xml = $xml->children(self::GEDMO_NAMESPACE_URI);
        if (in_array($xml_doctrine->get_name(), ['mapped-superclass', 'entity'], true)) {
            if (isset($xml->uploadable)) {
                $xml_uploadable = $xml->uploadable;
                $config['uploadable'] = true;
                $config['allowOverwrite'] = $this->_is_attribute_set($xml_uploadable, 'allow-overwrite') && (bool) $this->_get_attribute($xml_uploadable, 'allow-overwrite');
                $config['appendNumber'] = $this->_is_attribute_set($xml_uploadable, 'append-number') && (bool) $this->_get_attribute($xml_uploadable, 'append-number');
                $config['path'] = $this->_is_attribute_set($xml_uploadable, 'path') ? $this->_get_attribute($xml->{'uploadable'}, 'path') : '';
                $config['pathMethod'] = $this->_is_attribute_set($xml_uploadable, 'path-method') ? $this->_get_attribute($xml->{'uploadable'}, 'path-method') : '';
                $config['callback'] = $this->_is_attribute_set($xml_uploadable, 'callback') ? $this->_get_attribute($xml->{'uploadable'}, 'callback') : '';
                $config['fileMimeTypeField'] = false;
                $config['fileNameField'] = false;
                $config['filePathField'] = false;
                $config['fileSizeField'] = false;
                $config['filenameGenerator'] = $this->_is_attribute_set($xml_uploadable, 'filename-generator') ? $this->_get_attribute($xml->{'uploadable'}, 'filename-generator') : Validator::FILENAME_GENERATOR_NONE;
                $config['maxSize'] = $this->_is_attribute_set($xml_uploadable, 'max-size') ? (float) $this->_get_attribute($xml->{'uploadable'}, 'max-size') : (float) 0;
                $config['allowedTypes'] = $this->_is_attribute_set($xml_uploadable, 'allowed-types') ? $this->_get_attribute($xml->{'uploadable'}, 'allowed-types') : '';
                $config['disallowedTypes'] = $this->_is_attribute_set($xml_uploadable, 'disallowed-types') ? $this->_get_attribute($xml->{'uploadable'}, 'disallowed-types') : '';
                if (isset($xml_doctrine->field)) {
                    foreach ($xml_doctrine->field as $mapping) {
                        $mapping_doctrine = $mapping;
                        $mapping = $mapping->children(self::GEDMO_NAMESPACE_URI);
                        $field = $this->_get_attribute($mapping_doctrine, 'name');
                        if (isset($mapping->{'uploadable-file-mime-type'})) {
                            $config['fileMimeTypeField'] = $field;
                        } elseif (isset($mapping->{'uploadable-file-size'})) {
                            $config['fileSizeField'] = $field;
                        } elseif (isset($mapping->{'uploadable-file-name'})) {
                            $config['fileNameField'] = $field;
                        } elseif (isset($mapping->{'uploadable-file-path'})) {
                            $config['filePathField'] = $field;
                        }
                    }
                }
                $config = Validator::validate_configuration($meta, $config);
            }
        }
        return $config;
    }
}