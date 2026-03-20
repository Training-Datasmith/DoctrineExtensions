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
use Gedmo\Mapping\Driver;
use Gedmo\Mapping\Driver\File;
use Gedmo\Tree\Mapping\Validator;
/**
 * This is a yaml mapping driver for Tree
 * behavioral extension. Used for extraction of extended
 * metadata from yaml specifically for Tree
 * extension.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @deprecated since gedmo/doctrine-extensions 3.5, will be removed in version 4.0.
 *
 * @internal
 */
class Yaml extends File implements Driver
{
    /**
     * File extension
     *
     * @var string
     */
    protected $_extension = '.dcm.yml';
    /**
     * List of tree strategies available
     *
     * @var string[]
     */
    private array $strategies = ['nested', 'closure', 'materializedPath'];
    public function read_extended_metadata($meta, array &$config): array
    {
        $mapping = $this->_get_mapping($meta->get_name());
        $validator = new Validator();
        if (isset($mapping['gedmo'])) {
            $class_mapping = $mapping['gedmo'];
            if (isset($class_mapping['tree']['type'])) {
                $strategy = $class_mapping['tree']['type'];
                if (!in_array($strategy, $this->strategies, true)) {
                    throw new Invalid_Mapping_Exception("Tree type: {$strategy} is not available.");
                }
                $config['strategy'] = $strategy;
                $config['activate_locking'] = $class_mapping['tree']['activateLocking'] ?? false;
                $config['locking_timeout'] = isset($class_mapping['tree']['lockingTimeout']) ? (int) $class_mapping['tree']['lockingTimeout'] : 3;
                if ($config['locking_timeout'] < 1) {
                    throw new Invalid_Mapping_Exception('Tree Locking Timeout must be at least of 1 second.');
                }
            }
            if (isset($class_mapping['tree']['closure'])) {
                if (!$class = $this->get_related_class_name($meta, $class_mapping['tree']['closure'])) {
                    throw new Invalid_Mapping_Exception("Tree closure class: {$class_mapping['tree']['closure']} does not exist.");
                }
                $config['closure'] = $class;
            }
        }
        if (isset($mapping['id'])) {
            foreach ($mapping['id'] as $field => $field_mapping) {
                if (!isset($field_mapping['gedmo'])) {
                    continue;
                }
                if (!in_array('treePathSource', $field_mapping['gedmo'], true)) {
                    continue;
                }
                if (!$validator->is_valid_field_for_path_source($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Tree PathSource field - [{$field}] type is not valid. It can be any of the integer variants, double, float or string in class - {$meta->get_name()}");
                }
                $config['path_source'] = $field;
            }
        }
        if (isset($mapping['fields'])) {
            foreach ($mapping['fields'] as $field => $field_mapping) {
                if (isset($field_mapping['gedmo'])) {
                    if (in_array('treeLeft', $field_mapping['gedmo'], true)) {
                        if (!$validator->is_valid_field($meta, $field)) {
                            throw new Invalid_Mapping_Exception("Tree left field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                        }
                        $config['left'] = $field;
                    } elseif (in_array('treeRight', $field_mapping['gedmo'], true)) {
                        if (!$validator->is_valid_field($meta, $field)) {
                            throw new Invalid_Mapping_Exception("Tree right field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                        }
                        $config['right'] = $field;
                    } elseif (in_array('treeLevel', $field_mapping['gedmo'], true)) {
                        if (!$validator->is_valid_field($meta, $field)) {
                            throw new Invalid_Mapping_Exception("Tree level field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                        }
                        $config['level'] = $field;
                    } elseif (in_array('treeRoot', $field_mapping['gedmo'], true)) {
                        if (!$validator->is_valid_field_for_root($meta, $field)) {
                            throw new Invalid_Mapping_Exception("Tree root field - [{$field}] type is not valid and must be any of the 'integer' types or 'string' in class - {$meta->get_name()}");
                        }
                        $config['root'] = $field;
                    } elseif (in_array('treePath', $field_mapping['gedmo'], true) || isset($field_mapping['gedmo']['treePath'])) {
                        if (!$validator->is_valid_field_for_path($meta, $field)) {
                            throw new Invalid_Mapping_Exception("Tree Path field - [{$field}] type is not valid. It must be string or text in class - {$meta->get_name()}");
                        }
                        $tree_path_info = $field_mapping['gedmo']['treePath'] ?? $field_mapping['gedmo'][array_search('treePath', $field_mapping['gedmo'], true)];
                        if (is_array($tree_path_info) && isset($tree_path_info['separator'])) {
                            $separator = $tree_path_info['separator'];
                        } else {
                            $separator = '|';
                        }
                        if (strlen($separator) > 1) {
                            throw new Invalid_Mapping_Exception("Tree Path field - [{$field}] Separator {$separator} is invalid. It must be only one character long.");
                        }
                        if (is_array($tree_path_info) && isset($tree_path_info['appendId'])) {
                            $append_id = $tree_path_info['appendId'];
                        } else {
                            $append_id = null;
                        }
                        if (is_array($tree_path_info) && isset($tree_path_info['startsWithSeparator'])) {
                            $starts_with_separator = $tree_path_info['startsWithSeparator'];
                        } else {
                            $starts_with_separator = false;
                        }
                        if (is_array($tree_path_info) && isset($tree_path_info['endsWithSeparator'])) {
                            $ends_with_separator = $tree_path_info['endsWithSeparator'];
                        } else {
                            $ends_with_separator = true;
                        }
                        $config['path'] = $field;
                        $config['path_separator'] = $separator;
                        $config['path_append_id'] = $append_id;
                        $config['path_starts_with_separator'] = $starts_with_separator;
                        $config['path_ends_with_separator'] = $ends_with_separator;
                    } elseif (in_array('treePathSource', $field_mapping['gedmo'], true)) {
                        if (!$validator->is_valid_field_for_path_source($meta, $field)) {
                            throw new Invalid_Mapping_Exception("Tree PathSource field - [{$field}] type is not valid. It can be any of the integer variants, double, float or string in class - {$meta->get_name()}");
                        }
                        $config['path_source'] = $field;
                    } elseif (in_array('treePathHash', $field_mapping['gedmo'], true)) {
                        if (!$validator->is_valid_field_for_path_source($meta, $field)) {
                            throw new Invalid_Mapping_Exception("Tree PathHash field - [{$field}] type is not valid and must be 'string' in class - {$meta->get_name()}");
                        }
                        $config['path_hash'] = $field;
                    } elseif (in_array('treeLockTime', $field_mapping['gedmo'], true)) {
                        if (!$validator->is_valid_field_for_locktime($meta, $field)) {
                            throw new Invalid_Mapping_Exception("Tree LockTime field - [{$field}] type is not valid. It must be \"date\" in class - {$meta->get_name()}");
                        }
                        $config['lock_time'] = $field;
                    } elseif (in_array('treeParent', $field_mapping['gedmo'], true)) {
                        $config['parent'] = $field;
                    }
                }
            }
        }
        if (isset($config['activate_locking']) && $config['activate_locking'] && !isset($config['lock_time'])) {
            throw new Invalid_Mapping_Exception('You need to map a date|datetime|timestamp field as the tree lock time field to activate locking support.');
        }
        if (isset($mapping['manyToOne'])) {
            foreach ($mapping['manyToOne'] as $field => $relation_mapping) {
                if (isset($relation_mapping['gedmo'])) {
                    if (in_array('treeParent', $relation_mapping['gedmo'], true)) {
                        if (!$this->get_related_class_name($meta, $relation_mapping['targetEntity'])) {
                            throw new Invalid_Mapping_Exception("Unable to find ancestor/parent child relation through ancestor field - [{$field}] in class - {$meta->get_name()}");
                        }
                        $config['parent'] = $field;
                    }
                    if (in_array('treeRoot', $relation_mapping['gedmo'], true)) {
                        if (!$this->get_related_class_name($meta, $relation_mapping['targetEntity'])) {
                            throw new Invalid_Mapping_Exception("Unable to find root-descendant relation through root field - [{$field}] in class - {$meta->get_name()}");
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
    protected function _load_mapping_file($file)
    {
        return \Symfony\Component\Yaml\Yaml::parse(file_get_contents($file));
    }
}