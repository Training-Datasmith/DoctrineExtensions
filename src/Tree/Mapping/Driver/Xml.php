<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver\Xml as BaseXml;
use Gedmo\Tree\Mapping\Validator;
/**
 * This is a xml mapping driver for Tree
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for Tree
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
    /**
     * List of tree strategies available
     *
     * @var string[]
     */
    private array $strategies = ['nested', 'closure', 'materializedPath'];
    public function read_extended_metadata($meta, array &$config): array
    {
        /**
         * @var \SimpleXmlElement
         */
        $xml = $this->_get_mapping($meta->get_name());
        $xml_doctrine = $xml;
        $xml = $xml->children(self::GEDMO_NAMESPACE_URI);
        $validator = new Validator();
        if (isset($xml->tree) && $this->_is_attribute_set($xml->tree, 'type')) {
            $strategy = $this->_get_attribute($xml->tree, 'type');
            if (!in_array($strategy, $this->strategies, true)) {
                throw new Invalid_Mapping_Exception("Tree type: {$strategy} is not available.");
            }
            $config['strategy'] = $strategy;
            $config['activate_locking'] = $this->_is_attribute_set($xml->tree, 'activate-locking') && $this->_get_boolean_attribute($xml->tree, 'activate-locking');
            if ($locking_timeout = $this->_get_attribute($xml->tree, 'locking-timeout')) {
                $config['locking_timeout'] = (int) $locking_timeout;
                if ($config['locking_timeout'] < 1) {
                    throw new Invalid_Mapping_Exception('Tree Locking Timeout must be at least of 1 second.');
                }
            } else {
                $config['locking_timeout'] = 3;
            }
        }
        if (isset($xml->{'tree-closure'}) && $this->_is_attribute_set($xml->{'tree-closure'}, 'class')) {
            $class = $this->_get_attribute($xml->{'tree-closure'}, 'class');
            if (!$cl = $this->get_related_class_name($meta, $class)) {
                throw new Invalid_Mapping_Exception("Tree closure class: {$class} does not exist.");
            }
            $config['closure'] = $cl;
        }
        if (isset($xml_doctrine->field)) {
            foreach ($xml_doctrine->field as $mapping) {
                $mapping_doctrine = $mapping;
                $mapping = $mapping->children(self::GEDMO_NAMESPACE_URI);
                $field = $this->_get_attribute($mapping_doctrine, 'name');
                if (isset($mapping->{'tree-left'})) {
                    if (!$validator->is_valid_field($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Tree left field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                    }
                    $config['left'] = $field;
                } elseif (isset($mapping->{'tree-right'})) {
                    if (!$validator->is_valid_field($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Tree right field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                    }
                    $config['right'] = $field;
                } elseif (isset($mapping->{'tree-root'})) {
                    if (!$validator->is_valid_field_for_root($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Tree root field - [{$field}] type is not valid and must be any of the 'integer' types or 'string' in class - {$meta->get_name()}");
                    }
                    $config['root'] = $field;
                } elseif (isset($mapping->{'tree-level'})) {
                    if (!$validator->is_valid_field($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Tree level field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                    }
                    $config['level'] = $field;
                } elseif (isset($mapping->{'tree-path'})) {
                    if (!$validator->is_valid_field_for_path($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Tree Path field - [{$field}] type is not valid. It must be string or text in class - {$meta->get_name()}");
                    }
                    $separator = $this->_get_attribute($mapping->{'tree-path'}, 'separator');
                    if (strlen($separator) > 1) {
                        throw new Invalid_Mapping_Exception("Tree Path field - [{$field}] Separator {$separator} is invalid. It must be only one character long.");
                    }
                    $append_id = $this->_is_attribute_set($mapping->{'tree-path'}, 'append_id') ? $this->_get_boolean_attribute($mapping->{'tree-path'}, 'append_id') : null;
                    $starts_with_separator = $this->_is_attribute_set($mapping->{'tree-path'}, 'starts_with_separator') && $this->_get_boolean_attribute($mapping->{'tree-path'}, 'starts_with_separator');
                    $ends_with_separator = !$this->_is_attribute_set($mapping->{'tree-path'}, 'ends_with_separator') || $this->_get_boolean_attribute($mapping->{'tree-path'}, 'ends_with_separator');
                    $config['path'] = $field;
                    $config['path_separator'] = $separator;
                    $config['path_append_id'] = $append_id;
                    $config['path_starts_with_separator'] = $starts_with_separator;
                    $config['path_ends_with_separator'] = $ends_with_separator;
                } elseif (isset($mapping->{'tree-path-source'})) {
                    if (!$validator->is_valid_field_for_path_source($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Tree PathSource field - [{$field}] type is not valid. It can be any of the integer variants, double, float or string in class - {$meta->get_name()}");
                    }
                    $config['path_source'] = $field;
                } elseif (isset($mapping->{'tree-path-hash'})) {
                    if (!$validator->is_valid_field_for_path_source($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Tree PathHash field - [{$field}] type is not valid and must be 'string' in class - {$meta->get_name()}");
                    }
                    $config['path_hash'] = $field;
                } elseif (isset($mapping->{'tree-lock-time'})) {
                    if (!$validator->is_valid_field_for_lock_time($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Tree LockTime field - [{$field}] type is not valid. It must be \"date\" in class - {$meta->get_name()}");
                    }
                    $config['lock_time'] = $field;
                }
            }
        }
        if (isset($config['activate_locking']) && $config['activate_locking'] && !isset($config['lock_time'])) {
            throw new Invalid_Mapping_Exception('You need to map a date field as the tree lock time field to activate locking support.');
        }
        if ('mapped-superclass' === $xml_doctrine->get_name()) {
            if (isset($xml_doctrine->{'many-to-one'})) {
                foreach ($xml_doctrine->{'many-to-one'} as $many_to_one_mapping) {
                    /**
                     * @var \SimpleXMLElement
                     */
                    $many_to_one_mapping_doctrine = $many_to_one_mapping;
                    $many_to_one_mapping = $many_to_one_mapping->children(self::GEDMO_NAMESPACE_URI);
                    if (isset($many_to_one_mapping->{'tree-parent'})) {
                        $field = $this->_get_attribute($many_to_one_mapping_doctrine, 'field');
                        $target_entity = $meta->get_association_target_class($field);
                        if (!$cl = $this->get_related_class_name($meta, $target_entity)) {
                            throw new Invalid_Mapping_Exception("Unable to find ancestor/parent child relation through ancestor field - [{$field}] in class - {$meta->get_name()}");
                        }
                        $config['parent'] = $field;
                    }
                    if (isset($many_to_one_mapping->{'tree-root'})) {
                        $field = $this->_get_attribute($many_to_one_mapping_doctrine, 'field');
                        $target_entity = $meta->get_association_target_class($field);
                        if (!$cl = $this->get_related_class_name($meta, $target_entity)) {
                            throw new Invalid_Mapping_Exception("Unable to find root descendant relation through root field - [{$field}] in class - {$meta->get_name()}");
                        }
                        $config['root'] = $field;
                    }
                }
            } elseif (isset($xml_doctrine->{'reference-one'})) {
                foreach ($xml_doctrine->{'reference-one'} as $reference_one_mapping) {
                    /**
                     * @var \SimpleXMLElement
                     */
                    $reference_one_mapping_doctrine = $reference_one_mapping;
                    $reference_one_mapping = $reference_one_mapping->children(self::GEDMO_NAMESPACE_URI);
                    if (isset($reference_one_mapping->{'tree-parent'})) {
                        $field = $this->_get_attribute($reference_one_mapping_doctrine, 'field');
                        if (!$cl = $this->get_related_class_name($meta, $this->_get_attribute($reference_one_mapping_doctrine, 'target-document'))) {
                            throw new Invalid_Mapping_Exception("Unable to find ancestor/parent child relation through ancestor field - [{$field}] in class - {$meta->get_name()}");
                        }
                        $config['parent'] = $field;
                    }
                    if (isset($reference_one_mapping->{'tree-root'})) {
                        $field = $this->_get_attribute($reference_one_mapping_doctrine, 'field');
                        if (!$cl = $this->get_related_class_name($meta, $this->_get_attribute($reference_one_mapping_doctrine, 'target-document'))) {
                            throw new Invalid_Mapping_Exception("Unable to find root descendant relation through root field - [{$field}] in class - {$meta->get_name()}");
                        }
                        $config['root'] = $field;
                    }
                }
            }
        } elseif ('entity' === $xml_doctrine->get_name()) {
            if (isset($xml_doctrine->{'many-to-one'})) {
                foreach ($xml_doctrine->{'many-to-one'} as $many_to_one_mapping) {
                    /**
                     * @var \SimpleXMLElement
                     */
                    $many_to_one_mapping_doctrine = $many_to_one_mapping;
                    $many_to_one_mapping = $many_to_one_mapping->children(self::GEDMO_NAMESPACE_URI);
                    if (isset($many_to_one_mapping->{'tree-parent'})) {
                        $field = $this->_get_attribute($many_to_one_mapping_doctrine, 'field');
                        $target_entity = $meta->get_association_target_class($field);
                        if (!$cl = $this->get_related_class_name($meta, $target_entity)) {
                            throw new Invalid_Mapping_Exception("Unable to find ancestor/parent child relation through ancestor field - [{$field}] in class - {$meta->get_name()}");
                        }
                        $config['parent'] = $field;
                    }
                    if (isset($many_to_one_mapping->{'tree-root'})) {
                        $field = $this->_get_attribute($many_to_one_mapping_doctrine, 'field');
                        $target_entity = $meta->get_association_target_class($field);
                        if (!$cl = $this->get_related_class_name($meta, $target_entity)) {
                            throw new Invalid_Mapping_Exception("Unable to find root descendant relation through root field - [{$field}] in class - {$meta->get_name()}");
                        }
                        $config['root'] = $field;
                    }
                }
            }
        } elseif ('document' === $xml_doctrine->get_name()) {
            if (isset($xml_doctrine->{'reference-one'})) {
                foreach ($xml_doctrine->{'reference-one'} as $reference_one_mapping) {
                    /**
                     * @var \SimpleXMLElement
                     */
                    $reference_one_mapping_doctrine = $reference_one_mapping;
                    $reference_one_mapping = $reference_one_mapping->children(self::GEDMO_NAMESPACE_URI);
                    if (isset($reference_one_mapping->{'tree-parent'})) {
                        $field = $this->_get_attribute($reference_one_mapping_doctrine, 'field');
                        if (!$cl = $this->get_related_class_name($meta, $this->_get_attribute($reference_one_mapping_doctrine, 'target-document'))) {
                            throw new Invalid_Mapping_Exception("Unable to find ancestor/parent child relation through ancestor field - [{$field}] in class - {$meta->get_name()}");
                        }
                        $config['parent'] = $field;
                    }
                    if (isset($reference_one_mapping->{'tree-root'})) {
                        $field = $this->_get_attribute($reference_one_mapping_doctrine, 'field');
                        if (!$cl = $this->get_related_class_name($meta, $this->_get_attribute($reference_one_mapping_doctrine, 'target-document'))) {
                            throw new Invalid_Mapping_Exception("Unable to find root descendant relation through root field - [{$field}] in class - {$meta->get_name()}");
                        }
                        $config['root'] = $field;
                    }
                }
            }
        }
        if (!$meta->is_mapped_superclass && $config) {
            if (isset($config['strategy'])) {
                if (is_array($meta->get_identifier()) && count($meta->get_identifier()) > 1) {
                    throw new Invalid_Mapping_Exception("Tree does not support composite identifiers in class - {$meta->get_name()}");
                }
                $method = 'validate' . ucfirst($config['strategy']) . 'TreeMetadata';
                $validator->{$method}($meta, $config);
            } else {
                throw new Invalid_Mapping_Exception("Cannot find Tree type for class: {$meta->get_name()}");
            }
        }
        return $config;
    }
}