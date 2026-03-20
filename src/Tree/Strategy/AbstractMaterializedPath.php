<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Strategy;

use Doctrine\ODM\Mongo_Db\Unit_Of_Work as MongoDBUnitOfWork;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\RuntimeException;
use Gedmo\Exception\Tree_Locking_Exception;
use Gedmo\Mapping\Event\Adapter_Interface;
use Gedmo\Tree\Node;
use Gedmo\Tree\Strategy;
use Gedmo\Tree\Tree_Listener;
use Mongo_Db\BSON\Utc_Date_Time;
use Proxy_Manager\Proxy\Ghost_Object_Interface;
/**
 * This strategy makes tree using materialized path strategy
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author <rocco@roccosportal.com>
 */
abstract class Abstract_Materialized_Path implements Strategy
{
    public const ACTION_INSERT = 'insert';
    public const ACTION_UPDATE = 'update';
    public const ACTION_REMOVE = 'remove';
    protected \Gedmo\Tree\Tree_Listener $listener;
    /**
     * Array of objects which were scheduled for path processes
     *
     * @var array<int, object|Node>
     */
    protected $scheduled_for_path_process = [];
    /**
     * Array of objects which were scheduled for path process.
     * This time, this array contains the objects with their ID
     * already set
     *
     * @var array<int, object|Node>
     */
    protected $scheduled_for_path_process_with_id_set = [];
    /**
     * Roots of trees which needs to be locked
     *
     * @var array<int, object|Node>
     */
    protected $roots_of_trees_which_needs_locking = [];
    /**
     * Objects which are going to be inserted (set only if tree locking is used)
     *
     * @var array<int, object|Node>
     */
    protected $pending_objects_to_insert = [];
    /**
     * Objects which are going to be updated (set only if tree locking is used)
     *
     * @var array<int, object|Node>
     */
    protected $pending_objects_to_update = [];
    /**
     * Objects which are going to be removed (set only if tree locking is used)
     *
     * @var array<int, object|Node>
     */
    protected $pending_objects_to_remove = [];
    public function __construct(Tree_Listener $listener)
    {
        $this->listener = $listener;
    }
    public function get_name()
    {
        return Strategy::MATERIALIZED_PATH;
    }
    public function process_scheduled_insertion($om, $node, Adapter_Interface $ea): void
    {
        $meta = $om->get_class_metadata(get_class($node));
        // ID is always used in a path,
        // and if it is generated value from engine (like AUTO_INCREMENT),
        // we need to schedule the path update
        if ([] === $meta->get_identifier_values($node)) {
            $this->scheduled_for_path_process[spl_object_id($node)] = $node;
        } else {
            $this->update_node($om, $node, $ea);
        }
    }
    public function process_scheduled_update($om, $node, Adapter_Interface $ea): void
    {
        $meta = $om->get_class_metadata(get_class($node));
        $config = $this->listener->get_configuration($om, $meta->get_name());
        $uow = $om->get_unit_of_work();
        $change_set = $ea->get_object_change_set($uow, $node);
        if (isset($change_set[$config['parent']]) || isset($change_set[$config['path_source']])) {
            if (isset($change_set[$config['path']])) {
                $original_path = $change_set[$config['path']][0];
            } else {
                $original_path = $meta->get_field_value($node, $config['path']);
            }
            $this->update_node($om, $node, $ea);
            $this->update_children($om, $node, $ea, $original_path);
        }
    }
    public function process_post_persist($om, $node, Adapter_Interface $ea): void
    {
        $oid = spl_object_id($node);
        if ($this->scheduled_for_path_process && array_key_exists($oid, $this->scheduled_for_path_process)) {
            $this->scheduled_for_path_process_with_id_set[$oid] = $node;
            unset($this->scheduled_for_path_process[$oid]);
            if (empty($this->scheduled_for_path_process)) {
                foreach ($this->scheduled_for_path_process_with_id_set as $oid => $node) {
                    $this->update_node($om, $node, $ea);
                    unset($this->scheduled_for_path_process_with_id_set[$oid]);
                }
            }
        }
        $this->process_post_events_actions($om, $ea, $node, self::ACTION_INSERT);
    }
    public function process_post_update($om, $node, Adapter_Interface $ea): void
    {
        $this->process_post_events_actions($om, $ea, $node, self::ACTION_UPDATE);
    }
    public function process_post_remove($om, $node, Adapter_Interface $ea): void
    {
        $this->process_post_events_actions($om, $ea, $node, self::ACTION_REMOVE);
    }
    public function on_flush_end($om, Adapter_Interface $ea): void
    {
        $this->lock_trees($om, $ea);
    }
    public function process_pre_remove($om, $node): void
    {
        $this->process_pre_locking_actions($om, $node, self::ACTION_REMOVE);
    }
    public function process_pre_persist($om, $node): void
    {
        $this->process_pre_locking_actions($om, $node, self::ACTION_INSERT);
    }
    public function process_pre_update($om, $node): void
    {
        $this->process_pre_locking_actions($om, $node, self::ACTION_UPDATE);
    }
    public function process_metadata_load($om, $meta)
    {
    }
    public function process_scheduled_delete($om, $node): void
    {
        $meta = $om->get_class_metadata(get_class($node));
        $config = $this->listener->get_configuration($om, $meta->get_name());
        $this->remove_node($om, $meta, $config, $node);
    }
    /**
     * Update the $node
     *
     * @param object           $node target node
     * @param AdapterInterface $ea   event adapter
     */
    public function update_node(Object_Manager $om, $node, Adapter_Interface $ea): void
    {
        $meta = $om->get_class_metadata(get_class($node));
        $config = $this->listener->get_configuration($om, $meta->get_name());
        $uow = $om->get_unit_of_work();
        $parent = $meta->get_field_value($node, $config['parent']);
        $path = (string) $meta->get_field_value($node, $config['path_source']);
        // We need to avoid the presence of the path separator in the path source
        if (false !== strpos($path, $config['path_separator'])) {
            $msg = 'You can\'t use the Path separator ("%s") as a character for your PathSource field value.';
            throw new RuntimeException(sprintf($msg, $config['path_separator']));
        }
        $field_mapping = $meta->get_field_mapping($config['path_source']);
        // default behavior: if PathSource field is a string, we append the ID to the path
        // path_append_id is true: always append id
        // path_append_id is false: never append id
        if (true === $config['path_append_id'] || 'string' === ($field_mapping->type ?? $field_mapping['type']) && false !== $config['path_append_id']) {
            if (method_exists($meta, 'getIdentifierValue')) {
                $identifier = $meta->get_identifier_value($node);
            } else {
                $identifier = $meta->get_field_value($node, $meta->get_single_identifier_field_name());
            }
            $path .= '-' . $identifier;
        }
        if ($parent) {
            // Ensure parent has been initialized in the case where it's a proxy
            $om->initialize_object($parent);
            $change_set = $uow->is_scheduled_for_update($parent) ? $ea->get_object_change_set($uow, $parent) : false;
            $path_or_path_source_has_changed = $change_set && (isset($change_set[$config['path_source']]) || isset($change_set[$config['path']]));
            if ($path_or_path_source_has_changed || !$meta->get_field_value($node, $config['path'])) {
                $this->update_node($om, $parent, $ea);
            }
            $parent_path = $meta->get_field_value($parent, $config['path']);
            // if parent path not ends with separator
            if ($parent_path[strlen($parent_path) - 1] !== $config['path_separator']) {
                // add separator
                $path = $parent_path . $config['path_separator'] . $path;
            } else {
                // don't add separator
                $path = $parent_path . $path;
            }
        }
        if ($config['path_starts_with_separator'] && (strlen($path) > 0 && $path[0] !== $config['path_separator'])) {
            $path = $config['path_separator'] . $path;
        }
        if ($config['path_ends_with_separator'] && $path[strlen($path) - 1] !== $config['path_separator']) {
            $path .= $config['path_separator'];
        }
        $meta->set_field_value($node, $config['path'], $path);
        $changes = [$config['path'] => [null, $path]];
        $path_hash = null;
        if (isset($config['path_hash'])) {
            $path_hash = md5($path);
            $meta->set_field_value($node, $config['path_hash'], $path_hash);
            $changes[$config['path_hash']] = [null, $path_hash];
        }
        if (isset($config['root'])) {
            $root = null;
            // Define the root value by grabbing the top of the current path
            $root_finder_path = explode($config['path_separator'], $path);
            $root_index = $config['path_starts_with_separator'] ? 1 : 0;
            $root = $root_finder_path[$root_index];
            // If it is an association, then make it an reference
            // to the entity
            if ($meta->has_association($config['root'])) {
                $root_class = $meta->get_association_target_class($config['root']);
                $root = $om->get_reference($root_class, $root);
            }
            $meta->set_field_value($node, $config['root'], $root);
            $changes[$config['root']] = [null, $root];
        }
        if (isset($config['level'])) {
            $level = substr_count($path, $config['path_separator']);
            $meta->set_field_value($node, $config['level'], $level);
            $changes[$config['level']] = [null, $level];
        }
        if (!$uow instanceof Mongo_Db_Unit_Of_Work) {
            $ea->set_original_object_property($uow, $node, $config['path'], $path);
            $uow->schedule_extra_update($node, $changes);
        } else {
            $ea->recompute_single_object_change_set($uow, $meta, $node);
        }
        if (isset($config['path_hash'])) {
            $ea->set_original_object_property($uow, $node, $config['path_hash'], $path_hash);
        }
    }
    /**
     * Update node's children
     *
     * @param object $node
     * @param string $originalPath
     */
    public function update_children(Object_Manager $om, $node, Adapter_Interface $ea, $original_path): void
    {
        $meta = $om->get_class_metadata(get_class($node));
        $config = $this->listener->get_configuration($om, $meta->get_name());
        $children = $this->get_children($om, $meta, $config, $original_path);
        foreach ($children as $child) {
            $this->update_node($om, $child, $ea);
        }
    }
    /**
     * Process pre-locking actions
     *
     * @param object        $node
     * @param string        $action
     *
     */
    public function process_pre_locking_actions(\Doctrine\Persistence\Object_Manager $om, $node, $action): void
    {
        $meta = $om->get_class_metadata(get_class($node));
        $config = $this->listener->get_configuration($om, $meta->get_name());
        if ($config['activate_locking']) {
            $parent_node = $node;
            while (($parent = $meta->get_field_value($parent_node, $config['parent'])) !== null) {
                $parent_node = $parent;
            }
            // In some cases, the parent could be a not initialized proxy. In this case, the
            // "lockTime" field may NOT be loaded yet and have null instead of the date.
            // We need to be sure that this field has its real value
            if ($parent_node !== $node && $parent_node instanceof Ghost_Object_Interface) {
                $parent_node->initialize_proxy();
            }
            // If tree is already locked, we throw an exception
            $lock_time = $meta->get_field_value($parent_node, $config['lock_time']);
            if (null !== $lock_time) {
                $lock_time = $lock_time instanceof Utc_Date_Time ? $lock_time->to_date_time()->get_timestamp() : $lock_time->get_timestamp();
            }
            if (null !== $lock_time && $lock_time >= time() - $config['locking_timeout']) {
                $msg = 'Tree with root id "%s" is locked.';
                $id = $meta->get_identifier_value($parent_node);
                throw new Tree_Locking_Exception(sprintf($msg, $id));
            }
            $this->roots_of_trees_which_needs_locking[spl_object_id($parent_node)] = $parent_node;
            $oid = spl_object_id($node);
            switch ($action) {
                case self::ACTION_INSERT:
                    $this->pending_objects_to_insert[$oid] = $node;
                    break;
                case self::ACTION_UPDATE:
                    $this->pending_objects_to_update[$oid] = $node;
                    break;
                case self::ACTION_REMOVE:
                    $this->pending_objects_to_remove[$oid] = $node;
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('"%s" is not a valid action.', $action));
            }
        }
    }
    /**
     * Process pre-locking actions
     *
     * @param object $node
     * @param string $action
     */
    public function process_post_events_actions(Object_Manager $om, Adapter_Interface $ea, $node, $action): void
    {
        $meta = $om->get_class_metadata(get_class($node));
        $config = $this->listener->get_configuration($om, $meta->get_name());
        if ($config['activate_locking']) {
            switch ($action) {
                case self::ACTION_INSERT:
                    unset($this->pending_objects_to_insert[spl_object_id($node)]);
                    break;
                case self::ACTION_UPDATE:
                    unset($this->pending_objects_to_update[spl_object_id($node)]);
                    break;
                case self::ACTION_REMOVE:
                    unset($this->pending_objects_to_remove[spl_object_id($node)]);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('"%s" is not a valid action.', $action));
            }
            if (empty($this->pending_objects_to_insert) && empty($this->pending_objects_to_update) && empty($this->pending_objects_to_remove)) {
                $this->release_tree_locks($om, $ea);
            }
        }
    }
    /**
     * Remove node and its children
     *
     * @param ObjectManager         $om
     * @param ClassMetadata<object> $meta   Metadata
     * @param array<string, mixed>  $config config
     * @param object                $node   node to remove
     *
     * @return void
     */
    abstract public function remove_node($om, $meta, $config, $node);
    /**
     * Returns children of the node with its original path
     *
     * @param ObjectManager         $om
     * @param ClassMetadata<object> $meta         Metadata
     * @param array<string, mixed>  $config       config
     * @param string                $originalPath original path of object
     *
     * @return array<int, object>|\Traversable<int, object>
     */
    abstract public function get_children($om, $meta, $config, $original_path);
    /**
     * Locks all needed trees
     *
     * @return void
     */
    protected function lock_trees(Object_Manager $om, Adapter_Interface $ea)
    {
        // Do nothing by default
    }
    /**
     * Releases all trees which are locked
     *
     * @return void
     */
    protected function release_tree_locks(Object_Manager $om, Adapter_Interface $ea)
    {
        // Do nothing by default
    }
}