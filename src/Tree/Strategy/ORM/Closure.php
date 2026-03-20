<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Strategy\ORM;

use Doctrine\DBAL\Array_Parameter_Type;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Mapping\Association_Mapping;
use Doctrine\ORM\Mapping\Class_Metadata as ORMClassMetadata;
use Doctrine\ORM\Mapping\Property_Accessors\Property_Accessor_Factory;
use Doctrine\ORM\Mapping\To_One_Owning_Side_Mapping;
use Doctrine\ORM\Query;
use Doctrine\Persistence\Mapping\Abstract_Class_Metadata_Factory;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\RuntimeException;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Mapping\Event\Adapter_Interface;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
use Gedmo\Tree\Node;
use Gedmo\Tree\Strategy;
use Gedmo\Tree\Tree_Listener;
use Psr\Cache\Cache_Item_Pool_Interface;
/**
 * This strategy makes tree act like
 * a closure table.
 *
 * @author Gustavo Adrian <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Closure implements Strategy
{
    /**
     * TreeListener
     */
    protected \Gedmo\Tree\Tree_Listener $listener;
    /**
     * List of pending Nodes, which needs to
     * be post processed because of having a parent Node
     * which requires some additional calculations
     *
     * @var array<int, array<int, object|Node>>
     */
    private array $pending_child_node_inserts = [];
    /**
     * List of nodes which has their parents updated, but using
     * new nodes. They have to wait until their parents are inserted
     * on DB to make the update
     *
     * @var array<int, array<string, mixed>>
     *
     * @phpstan-var array<int, array{node: object|Node, oldParent: mixed}>
     */
    private array $pending_node_updates = [];
    /**
     * List of pending Nodes, which needs their "level"
     * field value set
     *
     * @var array<int|string, object|Node>
     *
     * @phpstan-var array<array-key, object|Node>
     */
    private array $pending_nodes_level_process = [];
    public function __construct(Tree_Listener $listener)
    {
        $this->listener = $listener;
    }
    public function get_name(): string
    {
        return Strategy::CLOSURE;
    }
    /**
     * @param EntityManagerInterface   $em
     * @param ORMClassMetadata<object> $meta
     */
    public function process_metadata_load($em, $meta): void
    {
        // TODO: Remove the body of this method in the next major version.
        $config = $this->listener->get_configuration($em, $meta->get_name());
        $closure_metadata = $em->get_class_metadata($config['closure']);
        $cmf = $em->get_metadata_factory();
        $has_the_user_explicitly_defined_mapping = true;
        if (!$closure_metadata->has_association('ancestor')) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2390', 'Not adding mapping explicitly to "ancestor" property in "%s" is deprecated and will not work in' . ' version 4.0. You MUST explicitly set the mapping as in our docs: https://github.com/doctrine-extensions/DoctrineExtensions/blob/main/doc/tree.md#closure-table', $closure_metadata->get_name());
            $has_the_user_explicitly_defined_mapping = false;
            // create ancestor mapping
            $ancestor_mapping = ['fieldName' => 'ancestor', 'id' => false, 'joinColumns' => [['name' => 'ancestor', 'referencedColumnName' => 'id', 'unique' => false, 'nullable' => false, 'onDelete' => 'CASCADE', 'onUpdate' => null, 'columnDefinition' => null]], 'inversedBy' => null, 'targetEntity' => $meta->get_name(), 'cascade' => null, 'fetch' => Orm_Class_Metadata::FETCH_LAZY];
            $closure_metadata->map_many_to_one($ancestor_mapping);
            if (property_exists($closure_metadata, 'propertyAccessors')) {
                // ORM 3.4+
                $closure_metadata->property_accessors['ancestor'] = Property_Accessor_Factory::create_property_accessor($closure_metadata->get_name(), 'ancestor');
            } else {
                // ORM 3.3-
                $closure_metadata->refl_fields['ancestor'] = $cmf->get_reflection_service()->get_accessible_property($closure_metadata->get_name(), 'ancestor');
            }
        }
        if (!$closure_metadata->has_association('descendant')) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2390', 'Not adding mapping explicitly to "descendant" property in "%s" is deprecated and will not work in' . ' version 4.0. You MUST explicitly set the mapping as in our docs: https://github.com/doctrine-extensions/DoctrineExtensions/blob/main/doc/tree.md#closure-table', $closure_metadata->get_name());
            $has_the_user_explicitly_defined_mapping = false;
            // create descendant mapping
            $descendant_mapping = ['fieldName' => 'descendant', 'id' => false, 'joinColumns' => [['name' => 'descendant', 'referencedColumnName' => 'id', 'unique' => false, 'nullable' => false, 'onDelete' => 'CASCADE', 'onUpdate' => null, 'columnDefinition' => null]], 'inversedBy' => null, 'targetEntity' => $meta->get_name(), 'cascade' => null, 'fetch' => Orm_Class_Metadata::FETCH_LAZY];
            $closure_metadata->map_many_to_one($descendant_mapping);
            if (property_exists($closure_metadata, 'propertyAccessors')) {
                // ORM 3.4+
                $closure_metadata->property_accessors['descendant'] = Property_Accessor_Factory::create_property_accessor($closure_metadata->get_name(), 'descendant');
            } else {
                // ORM 3.3-
                $closure_metadata->refl_fields['descendant'] = $cmf->get_reflection_service()->get_accessible_property($closure_metadata->get_name(), 'descendant');
            }
        }
        if (!$this->has_closure_table_unique_constraint($closure_metadata)) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2390', 'Not adding a unique constraint explicitly to "%s" is deprecated and will not be automatically' . ' added in version 4.0. You SHOULD explicitly add the unique constraint as in our docs: https://github.com/doctrine-extensions/DoctrineExtensions/blob/main/doc/tree.md#closure-table', $closure_metadata->get_name());
            $has_the_user_explicitly_defined_mapping = false;
            // create unique index on ancestor and descendant
            $index_name = substr(strtoupper('IDX_' . md5($closure_metadata->get_name())), 0, 20);
            $ancestor_association_mapping = $em->get_class_metadata($config['closure'])->get_association_mapping('ancestor');
            $descendant_association_mapping = $em->get_class_metadata($config['closure'])->get_association_mapping('descendant');
            $closure_metadata->table['uniqueConstraints'][$index_name] = ['columns' => [$this->get_join_column_field_name(is_array($ancestor_association_mapping) ? $ancestor_association_mapping : clone $ancestor_association_mapping), $this->get_join_column_field_name(is_array($descendant_association_mapping) ? $descendant_association_mapping : clone $descendant_association_mapping)]];
        }
        if (!$this->has_closure_table_depth_index($closure_metadata)) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2390', 'Not adding an index with "depth" column explicitly to "%s" is deprecated and will not be automatically' . ' added in version 4.0. You SHOULD explicitly add the index as in our docs: https://github.com/doctrine-extensions/DoctrineExtensions/blob/main/doc/tree.md#closure-table', $closure_metadata->get_name());
            $has_the_user_explicitly_defined_mapping = false;
            // this one may not be very useful
            $index_name = substr(strtoupper('IDX_' . md5($meta->get_name() . 'depth')), 0, 20);
            $closure_metadata->table['indexes'][$index_name] = ['columns' => ['depth']];
        }
        if (!$has_the_user_explicitly_defined_mapping) {
            $metadata_factory = $em->get_metadata_factory();
            $get_cache = \Closure::bind(static fn(Abstract_Class_Metadata_Factory $metadata_factory): ?Cache_Item_Pool_Interface => $metadata_factory->get_cache(), null, \get_class($metadata_factory));
            $metadata_cache = $get_cache($metadata_factory);
            if (null !== $metadata_cache) {
                // @see https://github.com/doctrine/persistence/pull/144
                // @see \Doctrine\Persistence\Mapping\AbstractClassMetadataFactory::getCacheKey()
                $cache_key = str_replace('\\', '__', $closure_metadata->get_name()) . '__CLASSMETADATA__';
                $item = $metadata_cache->get_item($cache_key);
                $metadata_cache->save($item->set($closure_metadata));
            }
        }
    }
    public function on_flush_end($em, Adapter_Interface $ea)
    {
    }
    public function process_pre_persist($em, $node): void
    {
        $this->pending_child_node_inserts[spl_object_id($em)][spl_object_id($node)] = $node;
    }
    public function process_pre_update($em, $node)
    {
    }
    public function process_pre_remove($em, $node)
    {
    }
    public function process_scheduled_insertion($em, $node, Adapter_Interface $ea)
    {
    }
    public function process_scheduled_delete($em, $entity)
    {
    }
    public function process_post_update($em, $entity, Adapter_Interface $ea): void
    {
        \assert($em instanceof Entity_Manager_Interface);
        $meta = $em->get_class_metadata(get_class($entity));
        $config = $this->listener->get_configuration($em, $meta->get_name());
        // Process TreeLevel field value
        if (!empty($config)) {
            $this->set_level_field_on_pending_nodes($em);
        }
    }
    public function process_post_remove($em, $entity, Adapter_Interface $ea)
    {
    }
    /**
     * @param EntityManagerInterface $em
     */
    public function process_post_persist($em, $entity, Adapter_Interface $ea): void
    {
        $uow = $em->get_unit_of_work();
        $em_hash = spl_object_id($em);
        while ($node = array_shift($this->pending_child_node_inserts[$em_hash])) {
            $meta = $em->get_class_metadata(get_class($node));
            $config = $this->listener->get_configuration($em, $meta->get_name());
            $identifier = $meta->get_single_identifier_field_name();
            $node_id = $meta->get_field_value($node, $identifier);
            $parent = $meta->get_field_value($node, $config['parent']);
            $closure_class = $config['closure'];
            $closure_meta = $em->get_class_metadata($closure_class);
            $closure_table = $closure_meta->get_table_name();
            $ancestor_association_mapping = $em->get_class_metadata($config['closure'])->get_association_mapping('ancestor');
            $descendant_association_mapping = $em->get_class_metadata($config['closure'])->get_association_mapping('descendant');
            $ancestor_column_name = $this->get_join_column_field_name(is_array($ancestor_association_mapping) ? $ancestor_association_mapping : clone $ancestor_association_mapping);
            $descendant_column_name = $this->get_join_column_field_name(is_array($descendant_association_mapping) ? $descendant_association_mapping : clone $descendant_association_mapping);
            $depth_column_name = $em->get_class_metadata($config['closure'])->get_column_name('depth');
            $entries = [[$ancestor_column_name => $node_id, $descendant_column_name => $node_id, $depth_column_name => 0]];
            if ($parent) {
                $dql = "SELECT c, a FROM {$closure_meta->get_name()} c";
                $dql .= ' JOIN c.ancestor a';
                $dql .= ' WHERE c.descendant = :parent';
                $q = $em->create_query($dql);
                $q->set_parameter('parent', $parent);
                $must_postpone = true;
                foreach ($q->to_iterable([], Query::HYDRATE_ARRAY) as $ancestor) {
                    $must_postpone = false;
                    $entries[] = [$ancestor_column_name => $ancestor['ancestor'][$identifier], $descendant_column_name => $node_id, $depth_column_name => $ancestor['depth'] + 1];
                }
                if ($must_postpone) {
                    // The parent has been persisted after the child, postpone the evaluation
                    $this->pending_child_node_inserts[$em_hash][] = $node;
                    continue;
                }
                if (isset($config['level'])) {
                    $this->pending_nodes_level_process[$node_id] = $node;
                }
            } elseif (isset($config['level'])) {
                $uow->schedule_extra_update($node, [$config['level'] => [null, 1]]);
                $ea->set_original_object_property($uow, $node, $config['level'], 1);
                $meta->set_field_value($node, $config['level'], 1);
            }
            foreach ($entries as $closure) {
                if (!$em->get_connection()->insert($closure_table, $closure)) {
                    throw new RuntimeException('Failed to insert new Closure record');
                }
            }
        }
        // Process pending node updates
        if (!empty($this->pending_node_updates)) {
            foreach ($this->pending_node_updates as $info) {
                $this->update_node($em, $info['node'], $info['oldParent']);
            }
            $this->pending_node_updates = [];
        }
        // Process TreeLevel field value
        $this->set_level_field_on_pending_nodes($em);
    }
    /**
     * @param EntityManagerInterface $em
     */
    public function process_scheduled_update($em, $node, Adapter_Interface $ea): void
    {
        $meta = $em->get_class_metadata(get_class($node));
        $config = $this->listener->get_configuration($em, $meta->get_name());
        $uow = $em->get_unit_of_work();
        $change_set = $uow->get_entity_change_set($node);
        if (array_key_exists($config['parent'], $change_set)) {
            // If new parent is new, we need to delay the update of the node
            // until it is inserted on DB
            $parent = $change_set[$config['parent']][1] ? Abstract_Wrapper::wrap($change_set[$config['parent']][1], $em) : null;
            if ($parent && !$parent->get_identifier()) {
                $this->pending_node_updates[spl_object_id($node)] = ['node' => $node, 'oldParent' => $change_set[$config['parent']][0]];
            } else {
                $this->update_node($em, $node, $change_set[$config['parent']][0]);
            }
        }
    }
    /**
     * Update node and closures
     *
     * @param object $node
     * @param object $oldParent
     */
    public function update_node(Entity_Manager_Interface $em, $node, $old_parent): void
    {
        $wrapped = Abstract_Wrapper::wrap($node, $em);
        $meta = $wrapped->get_metadata();
        $config = $this->listener->get_configuration($em, $meta->get_name());
        $closure_meta = $em->get_class_metadata($config['closure']);
        $node_id = $wrapped->get_identifier();
        $parent = $wrapped->get_property_value($config['parent']);
        $table = $closure_meta->get_table_name();
        $conn = $em->get_connection();
        // ensure integrity
        if ($parent) {
            $dql = "SELECT COUNT(c) FROM {$closure_meta->get_name()} c";
            $dql .= ' WHERE c.ancestor = :node';
            $dql .= ' AND c.descendant = :parent';
            $q = $em->create_query($dql);
            $q->set_parameters(['node' => $node, 'parent' => $parent]);
            if ($q->get_single_scalar_result()) {
                throw new UnexpectedValueException("Cannot set child as parent to node: {$node_id}");
            }
        }
        if ($old_parent) {
            $sub_query = "SELECT c2.id FROM {$table} c1";
            $sub_query .= " JOIN {$table} c2 ON c1.descendant = c2.descendant";
            $sub_query .= ' WHERE c1.ancestor = :nodeId AND c2.depth > c1.depth';
            $ids = $conn->execute_query($sub_query, ['nodeId' => $node_id])->fetch_first_column();
            if ([] !== $ids) {
                // using subquery directly, sqlite acts unfriendly
                $query = "DELETE FROM {$table} WHERE id IN (" . implode(', ', $ids) . ')';
                if (0 === $conn->execute_statement($query)) {
                    throw new RuntimeException('Failed to remove old closures');
                }
            }
        }
        if ($parent) {
            $wrapped_parent = Abstract_Wrapper::wrap($parent, $em);
            $parent_id = $wrapped_parent->get_identifier();
            $query = 'SELECT c1.ancestor, c2.descendant, (c1.depth + c2.depth + 1) AS depth';
            $query .= " FROM {$table} c1, {$table} c2";
            $query .= ' WHERE c1.descendant = :parentId';
            $query .= ' AND c2.ancestor = :nodeId';
            $closures = $conn->execute_query($query, ['nodeId' => $node_id, 'parentId' => $parent_id])->fetch_all_associative();
            foreach ($closures as $closure) {
                if (!$conn->insert($table, $closure)) {
                    throw new RuntimeException('Failed to insert new Closure record');
                }
            }
        }
        if (isset($config['level'])) {
            $this->pending_nodes_level_process[$node_id] = $node;
        }
    }
    /**
     * @param array<string, mixed>|AssociationMapping $association
     *
     * @return string|null
     */
    protected function get_join_column_field_name($association)
    {
        if (is_array($association)) {
            if (count($association['joinColumnFieldNames']) > 1) {
                throw new RuntimeException('More association on field ' . $association['fieldName']);
            }
            return array_shift($association['joinColumnFieldNames']);
        }
        if ($association instanceof To_One_Owning_Side_Mapping) {
            if (count($association->join_column_field_names) > 1) {
                throw new RuntimeException('More association on field ' . $association->field_name);
            }
            return array_shift($association->join_column_field_names);
        }
        throw new RuntimeException('Unsupported mapping type ' . gettype($association));
    }
    /**
     * Process pending entities to set their "level" value
     *
     * @param EntityManagerInterface $em
     *
     * @return void
     */
    protected function set_level_field_on_pending_nodes(Object_Manager $em)
    {
        if (!empty($this->pending_nodes_level_process)) {
            $first = array_slice($this->pending_nodes_level_process, 0, 1);
            $first = array_shift($first);
            assert(null !== $first);
            $meta = $em->get_class_metadata(get_class($first));
            unset($first);
            $identifier = $meta->get_identifier();
            $mapping = $meta->get_field_mapping($identifier[0]);
            $config = $this->listener->get_configuration($em, $meta->get_name());
            $closure_class = $config['closure'];
            $closure_meta = $em->get_class_metadata($closure_class);
            $uow = $em->get_unit_of_work();
            foreach ($this->pending_nodes_level_process as $node) {
                $children = $em->get_repository($meta->get_name())->children($node);
                foreach ($children as $child) {
                    $this->pending_nodes_level_process[Abstract_Wrapper::wrap($child, $em)->get_identifier()] = $child;
                }
            }
            // Avoid type conversion performance penalty
            $type = 'integer' === ($mapping->type ?? $mapping['type']) ? Array_Parameter_Type::INTEGER : Array_Parameter_Type::STRING;
            // We calculate levels for all nodes
            $sql = 'SELECT c.descendant, MAX(c.depth) + 1 AS levelNum ';
            $sql .= 'FROM ' . $closure_meta->get_table_name() . ' c ';
            $sql .= 'WHERE c.descendant IN (?) ';
            $sql .= 'GROUP BY c.descendant';
            $levels_assoc = $em->get_connection()->execute_query($sql, [array_keys($this->pending_nodes_level_process)], [$type])->fetch_all_numeric();
            // create key pair array with resultset
            $levels = [];
            foreach ($levels_assoc as $level) {
                $levels[$level[0]] = $level[1];
            }
            $levels_assoc = null;
            // Now we update levels
            foreach ($this->pending_nodes_level_process as $node_id => $node) {
                // Update new level
                $level = $levels[$node_id];
                $uow->schedule_extra_update($node, [$config['level'] => [$meta->get_field_value($node, $config['level']), $level]]);
                $meta->set_field_value($node, $config['level'], $level);
                $uow->set_original_entity_property(spl_object_id($node), $config['level'], $level);
            }
            $this->pending_nodes_level_process = [];
        }
    }
    /**
     * @param ORMClassMetadata<object> $closureMetadata
     */
    private function has_closure_table_unique_constraint(Class_Metadata $closure_metadata): bool
    {
        if (!isset($closure_metadata->table['uniqueConstraints'])) {
            return false;
        }
        foreach ($closure_metadata->table['uniqueConstraints'] as $unique_constraint) {
            if ([] === array_diff(['ancestor', 'descendant'], $unique_constraint['columns'])) {
                return true;
            }
        }
        return false;
    }
    /**
     * @param ORMClassMetadata<object> $closureMetadata
     */
    private function has_closure_table_depth_index(Class_Metadata $closure_metadata): bool
    {
        if (!isset($closure_metadata->table['indexes'])) {
            return false;
        }
        foreach ($closure_metadata->table['indexes'] as $unique_constraint) {
            if ([] === array_diff(['depth'], $unique_constraint['columns'])) {
                return true;
            }
        }
        return false;
    }
}