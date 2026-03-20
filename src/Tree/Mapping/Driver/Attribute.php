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
use Gedmo\Mapping\Annotation\Tree;
use Gedmo\Mapping\Annotation\Tree_Closure;
use Gedmo\Mapping\Annotation\Tree_Left;
use Gedmo\Mapping\Annotation\Tree_Level;
use Gedmo\Mapping\Annotation\Tree_Lock_Time;
use Gedmo\Mapping\Annotation\Tree_Parent;
use Gedmo\Mapping\Annotation\Tree_Path;
use Gedmo\Mapping\Annotation\Tree_Path_Hash;
use Gedmo\Mapping\Annotation\Tree_Path_Source;
use Gedmo\Mapping\Annotation\Tree_Right;
use Gedmo\Mapping\Annotation\Tree_Root;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
use Gedmo\Tree\Mapping\Validator;
/**
 * Mapping driver for the tree extension which reads extended metadata from attributes on class which is part of a tree.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author <rocco@roccosportal.com>
 * @author Kevin Mian Kraiker <kevin.mian@gmail.com>
 *
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object to configure the type of tree.
     */
    public const TREE = Tree::class;
    /**
     * Mapping object to mark the field which will store the left value of a tree node.
     */
    public const LEFT = Tree_Left::class;
    /**
     * Mapping object to mark the field which will store the right value of a tree node.
     */
    public const RIGHT = Tree_Right::class;
    /**
     * Mapping object to mark the field which will store the reference to the parent of a tree node.
     */
    public const PARENT = Tree_Parent::class;
    /**
     * Mapping object to mark the field which will store the level of a tree node.
     */
    public const LEVEL = Tree_Level::class;
    /**
     * Mapping object to mark the field which will store the reference to the root of a tree node.
     */
    public const ROOT = Tree_Root::class;
    /**
     * Mapping object to configure a closure tree object.
     */
    public const CLOSURE = Tree_Closure::class;
    /**
     * Mapping object to configure a tree path field.
     */
    public const PATH = Tree_Path::class;
    /**
     * Mapping object to specify the source for a tree path.
     */
    public const PATH_SOURCE = Tree_Path_Source::class;
    /**
     * Mapping object to configure the hash for a tree path.
     */
    public const PATH_HASH = Tree_Path_Hash::class;
    /**
     * Mapping object to configure the lock time for a tree.
     */
    public const LOCK_TIME = Tree_Lock_Time::class;
    /**
     * List of tree strategies available
     *
     * @var string[]
     */
    protected $strategies = ['nested', 'closure', 'materializedPath'];
    public function read_extended_metadata($meta, array &$config): array
    {
        $validator = new Validator();
        $class = $this->get_meta_reflection_class($meta);
        // class annotations
        if ($annot = $this->reader->get_class_annotation($class, self::TREE)) {
            \assert($annot instanceof Tree);
            if (!in_array($annot->type, $this->strategies, true)) {
                throw new Invalid_Mapping_Exception("Tree type: {$annot->type} is not available.");
            }
            $config['strategy'] = $annot->type;
            $config['activate_locking'] = $annot->activate_locking;
            $config['locking_timeout'] = $annot->locking_timeout;
            if ($config['locking_timeout'] < 1) {
                throw new Invalid_Mapping_Exception('Tree Locking Timeout must be at least of 1 second.');
            }
        }
        if ($annot = $this->reader->get_class_annotation($class, self::CLOSURE)) {
            \assert($annot instanceof Tree_Closure);
            if (!$cl = $this->get_related_class_name($meta, $annot->class)) {
                throw new Invalid_Mapping_Exception("Tree closure class: {$annot->class} does not exist.");
            }
            $config['closure'] = $cl;
        }
        // property annotations
        foreach ($class->get_properties() as $property) {
            if ($meta->is_mapped_superclass && !$property->is_private()) {
                continue;
            }
            if ($meta->is_inherited_field($property->name)) {
                continue;
            }
            if (isset($meta->association_mappings[$property->name]['inherited'])) {
                continue;
            }
            // left
            if ($this->reader->get_property_annotation($property, self::LEFT)) {
                $field = $property->get_name();
                if (!$meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find 'left' - [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                if (!$validator->is_valid_field($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Tree left field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                }
                $config['left'] = $field;
            }
            // right
            if ($this->reader->get_property_annotation($property, self::RIGHT)) {
                $field = $property->get_name();
                if (!$meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find 'right' - [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                if (!$validator->is_valid_field($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Tree right field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                }
                $config['right'] = $field;
            }
            // ancestor/parent
            if ($this->reader->get_property_annotation($property, self::PARENT)) {
                $field = $property->get_name();
                if (!$meta->is_single_valued_association($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find ancestor/parent child relation through ancestor field - [{$field}] in class - {$meta->get_name()}");
                }
                $config['parent'] = $field;
            }
            // root
            if ($annot = $this->reader->get_property_annotation($property, self::ROOT)) {
                \assert($annot instanceof Tree_Root);
                $field = $property->get_name();
                if (!$meta->is_single_valued_association($field)) {
                    if (!$meta->has_field($field)) {
                        throw new Invalid_Mapping_Exception("Unable to find 'root' - [{$field}] as mapped property in entity - {$meta->get_name()}");
                    }
                    if (!$validator->is_valid_field_for_root($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Tree root field should be either a literal property ('integer' types or 'string') or a many-to-one association through root field - [{$field}] in class - {$meta->get_name()}");
                    }
                }
                $config['rootIdentifierMethod'] = $annot->identifier_method;
                $config['root'] = $field;
            }
            // level
            if ($annot = $this->reader->get_property_annotation($property, self::LEVEL)) {
                \assert($annot instanceof Tree_Level);
                $field = $property->get_name();
                if (!$meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find 'level' - [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                if (!$validator->is_valid_field($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Tree level field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                }
                $config['level'] = $field;
                $config['level_base'] = $annot->base;
            }
            // path
            if ($annot = $this->reader->get_property_annotation($property, self::PATH)) {
                \assert($annot instanceof Tree_Path);
                $field = $property->get_name();
                if (!$meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find 'path' - [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                if (!$validator->is_valid_field_for_path($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Tree Path field - [{$field}] type is not valid. It must be string or text in class - {$meta->get_name()}");
                }
                if (strlen($annot->separator) > 1) {
                    throw new Invalid_Mapping_Exception("Tree Path field - [{$field}] Separator {$annot->separator} is invalid. It must be only one character long.");
                }
                $config['path'] = $field;
                $config['path_separator'] = $annot->separator;
                $config['path_append_id'] = $annot->append_id;
                $config['path_starts_with_separator'] = $annot->starts_with_separator;
                $config['path_ends_with_separator'] = $annot->ends_with_separator;
            }
            // path source
            if (null !== $this->reader->get_property_annotation($property, self::PATH_SOURCE)) {
                $field = $property->get_name();
                if (!$meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find 'path_source' - [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                if (!$validator->is_valid_field_for_path_source($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Tree PathSource field - [{$field}] type is not valid. It can be any of the integer variants, double, float or string in class - {$meta->get_name()}");
                }
                $config['path_source'] = $field;
            }
            // path hash
            if (null !== $this->reader->get_property_annotation($property, self::PATH_HASH)) {
                $field = $property->get_name();
                if (!$meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find 'path_hash' - [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                if (!$validator->is_valid_field_for_path_hash($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Tree PathHash field - [{$field}] type is not valid. It can be any of the integer variants, double, float or string in class - {$meta->get_name()}");
                }
                $config['path_hash'] = $field;
            }
            // lock time
            if (null !== $this->reader->get_property_annotation($property, self::LOCK_TIME)) {
                $field = $property->get_name();
                if (!$meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find 'lock_time' - [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                if (!$validator->is_valid_field_for_lock_time($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Tree PathSource field - [{$field}] type is not valid. It must be \"date\" in class - {$meta->get_name()}");
                }
                $config['lock_time'] = $field;
            }
        }
        if (isset($config['activate_locking']) && $config['activate_locking'] && !isset($config['lock_time'])) {
            throw new Invalid_Mapping_Exception('You need to map a date field as the tree lock time field to activate locking support.');
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