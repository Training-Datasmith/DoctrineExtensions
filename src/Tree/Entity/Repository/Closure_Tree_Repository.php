<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Entity\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query_Builder;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Tool\Wrapper\Entity_Wrapper;
use Gedmo\Tree\Entity\Mapped_Superclass\Abstract_Closure;
use Gedmo\Tree\Strategy;
/**
 * The ClosureTreeRepository has some useful functions
 * to interact with Closure tree. Repository uses
 * the strategy used by listener
 *
 * @author Gustavo Adrian <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @template T of object
 *
 * @template-extends AbstractTreeRepository<T>
 */
class Closure_Tree_Repository extends Abstract_Tree_Repository
{
    /** Alias for the level value used in the subquery of the getNodesHierarchy method */
    public const SUBQUERY_LEVEL = 'level';
    public function get_root_nodes_query_builder($sort_by_field = null, $direction = 'asc')
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $qb = $this->get_query_builder();
        $qb->select('node')->from($config['useObjectClass'], 'node')->where('node.' . $config['parent'] . ' IS NULL');
        if (null !== $sort_by_field) {
            $sort_by_field = (array) $sort_by_field;
            $direction = (array) $direction;
            foreach ($sort_by_field as $key => $field) {
                $field_direction = $direction[$key] ?? 'asc';
                if ($meta->has_field($field) || $meta->is_single_valued_association($field)) {
                    $qb->add_order_by('node.' . $field, 'asc' === strtolower($field_direction) ? 'asc' : 'desc');
                }
            }
        }
        return $qb;
    }
    public function get_root_nodes_query($sort_by_field = null, $direction = 'asc')
    {
        return $this->get_root_nodes_query_builder($sort_by_field, $direction)->get_query();
    }
    public function get_root_nodes($sort_by_field = null, $direction = 'asc')
    {
        return $this->get_root_nodes_query($sort_by_field, $direction)->get_result();
    }
    /**
     * Get the Tree path query by given $node
     *
     * @param object $node
     *
     * @throws InvalidArgumentException if input is not valid
     *
     * @return Query
     */
    public function get_path_query($node)
    {
        $meta = $this->get_class_metadata();
        if (!is_a($node, $meta->get_name())) {
            throw new InvalidArgumentException('Node is not related to this repository');
        }
        if (!$this->get_entity_manager()->get_unit_of_work()->is_in_identity_map($node)) {
            throw new InvalidArgumentException('Node is not managed by UnitOfWork');
        }
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $closure_meta = $this->get_entity_manager()->get_class_metadata($config['closure']);
        $dql = "SELECT c, node FROM {$closure_meta->get_name()} c";
        $dql .= ' INNER JOIN c.ancestor node';
        $dql .= ' WHERE c.descendant = :node';
        $dql .= ' ORDER BY c.depth DESC';
        $q = $this->get_entity_manager()->create_query($dql);
        $q->set_parameter('node', $node);
        return $q;
    }
    /**
     * Get the Tree path of Nodes by given $node
     *
     * @param object $node
     *
     * @return array<int, object|null> list of Nodes in path
     */
    public function get_path($node)
    {
        return array_map(static fn(Abstract_Closure $closure) => $closure->get_ancestor(), $this->get_path_query($node)->get_result());
    }
    /**
     * @param object|null          $node        If null, all tree nodes will be taken
     * @param bool                 $direct      True to take only direct children
     * @param string|string[]|null $sortByField Field name or array of fields names to sort by
     * @param string|string[]      $direction   Sort order ('asc'|'desc'|'ASC'|'DESC'). If $sortByField is an array, this may also be an array with matching number of elements
     * @param bool                 $includeNode Include the root node in results?
     *
     * @phpstan-param 'asc'|'desc'|'ASC'|'DESC'|array<int, 'asc'|'desc'|'ASC'|'DESC'> $direction
     *
     * @return QueryBuilder QueryBuilder object
     */
    public function children_query_builder($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false)
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $qb = $this->get_query_builder();
        if (null !== $node) {
            if (is_a($node, $meta->get_name())) {
                if (!$this->get_entity_manager()->get_unit_of_work()->is_in_identity_map($node)) {
                    throw new InvalidArgumentException('Node is not managed by UnitOfWork');
                }
                $where = 'c.ancestor = :node AND ';
                $qb->select('c, node')->from($config['closure'], 'c')->inner_join('c.descendant', 'node');
                if ($direct) {
                    $where .= 'c.depth = 1';
                } else {
                    $where .= 'c.descendant <> :node';
                }
                $qb->where($where);
                if ($include_node) {
                    $qb->or_where('c.ancestor = :node AND c.descendant = :node');
                }
            } else {
                throw new \InvalidArgumentException('Node is not related to this repository');
            }
        } else {
            $qb->select('node')->from($config['useObjectClass'], 'node');
            if ($direct) {
                $qb->where('node.' . $config['parent'] . ' IS NULL');
            }
        }
        if ($sort_by_field) {
            if (is_array($sort_by_field)) {
                foreach ($sort_by_field as $key => $field) {
                    $field_direction = is_array($direction) ? $direction[$key] ?? 'asc' : $direction;
                    if (($meta->has_field($field) || $meta->is_single_valued_association($field)) && in_array(strtolower($field_direction), ['asc', 'desc'], true)) {
                        $qb->add_order_by('node.' . $field, $field_direction);
                    } else {
                        throw new InvalidArgumentException(sprintf('Invalid sort options specified: field - %s, direction - %s', $field, $field_direction));
                    }
                }
            } else if (($meta->has_field($sort_by_field) || $meta->is_single_valued_association($sort_by_field)) && in_array(strtolower($direction), ['asc', 'desc'], true)) {
                $qb->order_by('node.' . $sort_by_field, $direction);
            } else {
                throw new InvalidArgumentException(sprintf('Invalid sort options specified: field - %s, direction - %s', $sort_by_field, $direction));
            }
        }
        if ($node) {
            $qb->set_parameter('node', $node);
        }
        return $qb;
    }
    /**
     * @param object|null          $node        If null, all tree nodes will be taken
     * @param bool                 $direct      True to take only direct children
     * @param string|string[]|null $sortByField Field name or array of fields names to sort by
     * @param string|string[]      $direction   Sort order ('asc'|'desc'|'ASC'|'DESC'). If $sortByField is an array, this may also be an array with matching number of elements
     * @param bool                 $includeNode Include the root node in results?
     *
     * @phpstan-param 'asc'|'desc'|'ASC'|'DESC'|array<int, 'asc'|'desc'|'ASC'|'DESC'> $direction
     *
     * @return Query Query object
     */
    public function children_query($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false)
    {
        return $this->children_query_builder($node, $direct, $sort_by_field, $direction, $include_node)->get_query();
    }
    /**
     * @param object|null          $node        If null, all tree nodes will be taken
     * @param bool                 $direct      True to take only direct children
     * @param string|string[]|null $sortByField Field name or array of fields names to sort by
     * @param string|string[]      $direction   Sort order ('asc'|'desc'|'ASC'|'DESC'). If $sortByField is an array, this may also be an array with matching number of elements
     * @param bool                 $includeNode Include the root node in results?
     *
     * @phpstan-param 'asc'|'desc'|'ASC'|'DESC'|array<int, 'asc'|'desc'|'ASC'|'DESC'> $direction
     *
     * @return array<int, object|null> List of children or null on failure
     */
    public function children($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false)
    {
        $result = $this->children_query($node, $direct, $sort_by_field, $direction, $include_node)->get_result();
        if ($node) {
            return array_map(static fn(Abstract_Closure $closure) => $closure->get_descendant(), $result);
        }
        return $result;
    }
    public function get_children_query_builder($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false)
    {
        return $this->children_query_builder($node, $direct, $sort_by_field, $direction, $include_node);
    }
    public function get_children_query($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false)
    {
        return $this->children_query($node, $direct, $sort_by_field, $direction, $include_node);
    }
    /**
     * @return array<int, object|null>
     */
    public function get_children($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false)
    {
        return $this->children($node, $direct, $sort_by_field, $direction, $include_node);
    }
    /**
     * Removes given $node from the tree and reparents its descendants
     *
     * @todo may be improved, to issue single query on reparenting
     *
     * @param object $node
     *
     * @throws InvalidArgumentException
     * @throws \Gedmo\Exception\RuntimeException if something fails in transaction
     */
    public function remove_from_tree($node): void
    {
        $meta = $this->get_class_metadata();
        if (!is_a($node, $meta->get_name())) {
            throw new InvalidArgumentException('Node is not related to this repository');
        }
        $wrapped = new Entity_Wrapper($node, $this->get_entity_manager());
        if (!$wrapped->has_valid_identifier()) {
            throw new InvalidArgumentException('Node is not managed by UnitOfWork');
        }
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $pk = $meta->get_single_identifier_field_name();
        $node_id = $wrapped->get_identifier();
        $parent = $wrapped->get_property_value($config['parent']);
        $dql = "SELECT node FROM {$config['useObjectClass']} node";
        $dql .= " WHERE node.{$config['parent']} = :node";
        $q = $this->get_entity_manager()->create_query($dql);
        $q->set_parameter('node', $node);
        $nodes_to_reparent = $q->to_iterable();
        // process updates in transaction
        $this->get_entity_manager()->get_connection()->begin_transaction();
        try {
            foreach ($nodes_to_reparent as $node_to_reparent) {
                $id = $meta->get_field_value($node_to_reparent, $pk);
                $meta->set_field_value($node_to_reparent, $config['parent'], $parent);
                $dql = "UPDATE {$config['useObjectClass']} node";
                $dql .= " SET node.{$config['parent']} = :parent";
                $dql .= " WHERE node.{$pk} = :id";
                $q = $this->get_entity_manager()->create_query($dql);
                $q->set_parameters(['parent' => $parent, 'id' => $id]);
                $q->get_single_scalar_result();
                $this->listener->get_strategy($this->get_entity_manager(), $meta->get_name())->update_node($this->get_entity_manager(), $node_to_reparent, $node);
                $oid = spl_object_id($node_to_reparent);
                $this->get_entity_manager()->get_unit_of_work()->set_original_entity_property($oid, $config['parent'], $parent);
            }
            $dql = "DELETE {$config['useObjectClass']} node";
            $dql .= " WHERE node.{$pk} = :nodeId";
            $q = $this->get_entity_manager()->create_query($dql);
            $q->set_parameter('nodeId', $node_id);
            $q->get_single_scalar_result();
            $this->get_entity_manager()->get_connection()->commit();
        } catch (\Exception $e) {
            $this->get_entity_manager()->close();
            $this->get_entity_manager()->get_connection()->rollback();
            throw new \Gedmo\Exception\RuntimeException('Transaction failed: ' . $e->get_message(), $e->get_code(), $e);
        }
        // remove from identity map
        $this->get_entity_manager()->get_unit_of_work()->remove_from_identity_map($node);
    }
    public function build_tree_array(array $nodes): array
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $nested_tree = [];
        $id_field = $meta->get_single_identifier_field_name();
        $has_level_prop = !empty($config['level']);
        $level_prop = $has_level_prop ? $config['level'] : self::SUBQUERY_LEVEL;
        $children_index = $this->repo_utils->get_children_index();
        if ([] !== $nodes) {
            $first_level = $has_level_prop ? $nodes[0][0]['descendant'][$level_prop] : $nodes[0][$level_prop];
            $l = 1;
            // 1 is only an initial value. We could have a tree which has a root node with any level (subtrees)
            $refs = [];
            foreach ($nodes as $n) {
                $node = $n[0]['descendant'];
                $node[$children_index] = [];
                $level = $has_level_prop ? $node[$level_prop] : $n[$level_prop];
                if ($l < $level) {
                    $l = $level;
                }
                if ($l == $first_level) {
                    $tmp =& $nested_tree;
                } else {
                    $tmp =& $refs[$n['parent_id']][$children_index];
                }
                $key = count($tmp);
                $tmp[$key] = $node;
                $refs[$node[$id_field]] =& $tmp[$key];
            }
            unset($refs);
        }
        return $nested_tree;
    }
    public function get_nodes_hierarchy($node = null, $direct = false, array $options = [], $include_node = false)
    {
        return $this->get_nodes_hierarchy_query($node, $direct, $options, $include_node)->get_array_result();
    }
    public function get_nodes_hierarchy_query($node = null, $direct = false, array $options = [], $include_node = false)
    {
        return $this->get_nodes_hierarchy_query_builder($node, $direct, $options, $include_node)->get_query();
    }
    public function get_nodes_hierarchy_query_builder($node = null, $direct = false, array $options = [], $include_node = false)
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $id_field = $meta->get_single_identifier_field_name();
        $sub_query = '';
        $has_level_prop = isset($config['level']) && $config['level'];
        if (!$has_level_prop) {
            $sub_query = ', (SELECT MAX(c2.depth) + 1 FROM ' . $config['closure'];
            $sub_query .= ' c2 WHERE c2.descendant = c.descendant GROUP BY c2.descendant) AS ' . self::SUBQUERY_LEVEL;
        }
        $q = $this->get_entity_manager()->create_query_builder()->select('c, node, p.' . $id_field . ' AS parent_id' . $sub_query)->from($config['closure'], 'c')->inner_join('c.descendant', 'node')->left_join('node.parent', 'p')->add_order_by($has_level_prop ? 'node.' . $config['level'] : self::SUBQUERY_LEVEL, 'asc');
        if (null !== $node) {
            $q->where('c.ancestor = :node');
            $q->set_parameter('node', $node);
        } else {
            $q->group_by('c.descendant');
        }
        if (!$include_node) {
            $q->and_where('c.ancestor != c.descendant');
        }
        $default_options = [];
        $options = array_merge($default_options, $options);
        if (isset($options['childSort']) && is_array($options['childSort']) && isset($options['childSort']['field'], $options['childSort']['dir'])) {
            $q->add_order_by('node.' . $options['childSort']['field'], 'asc' === strtolower($options['childSort']['dir']) ? 'asc' : 'desc');
        }
        return $q;
    }
    /**
     * @return array<int, string>|bool
     */
    public function verify()
    {
        $node_meta = $this->get_class_metadata();
        $node_id_field = $node_meta->get_single_identifier_field_name();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $node_meta->get_name());
        $closure_meta = $this->get_entity_manager()->get_class_metadata($config['closure']);
        $errors = [];
        $q = $this->get_entity_manager()->create_query("\n          SELECT COUNT(node)\n          FROM {$node_meta->get_name()} AS node\n          LEFT JOIN {$closure_meta->get_name()} AS c WITH c.ancestor = node AND c.depth = 0\n          WHERE c.id IS NULL\n        ");
        if ($missing_self_refs_count = (int) $q->get_single_scalar_result()) {
            $errors[] = "Missing {$missing_self_refs_count} self referencing closures";
        }
        $q = $this->get_entity_manager()->create_query("\n          SELECT COUNT(node)\n          FROM {$node_meta->get_name()} AS node\n          INNER JOIN {$closure_meta->get_name()} AS c1 WITH c1.descendant = node.{$config['parent']}\n          LEFT  JOIN {$closure_meta->get_name()} AS c2 WITH c2.descendant = node.{$node_id_field} AND c2.ancestor = c1.ancestor\n          WHERE c2.id IS NULL AND node.{$node_id_field} <> c1.ancestor\n        ");
        if ($missing_closures_count = (int) $q->get_single_scalar_result()) {
            $errors[] = "Missing {$missing_closures_count} closures";
        }
        $q = $this->get_entity_manager()->create_query("\n            SELECT COUNT(c1.id)\n            FROM {$closure_meta->get_name()} AS c1\n            LEFT JOIN {$node_meta->get_name()} AS node WITH c1.descendant = node.{$node_id_field}\n            LEFT JOIN {$closure_meta->get_name()} AS c2 WITH c2.descendant = node.{$config['parent']} AND c2.ancestor = c1.ancestor\n            WHERE c2.id IS NULL AND c1.descendant <> c1.ancestor\n        ");
        if ($invalid_closures_count = (int) $q->get_single_scalar_result()) {
            $errors[] = "Found {$invalid_closures_count} invalid closures";
        }
        if (!empty($config['level'])) {
            $level_field = $config['level'];
            $max_results = 1000;
            $q = $this->get_entity_manager()->create_query("\n                SELECT node.{$node_id_field} AS id, node.{$level_field} AS node_level, MAX(c.depth) AS closure_level\n                FROM {$node_meta->get_name()} AS node\n                INNER JOIN {$closure_meta->get_name()} AS c WITH c.descendant = node.{$node_id_field}\n                GROUP BY node.{$node_id_field}, node.{$level_field}\n                HAVING node.{$level_field} IS NULL OR node.{$level_field} <> MAX(c.depth) + 1\n            ")->set_max_results($max_results);
            if ($invalid_levels_count = count($q->get_scalar_result())) {
                $errors[] = "Found {$invalid_levels_count} invalid level values";
            }
        }
        return [] !== $errors ? $errors : true;
    }
    public function recover(): void
    {
        if (true === $this->verify()) {
            return;
        }
        $this->clean_up_closure();
        $this->rebuild_closure();
    }
    /**
     * @return int
     */
    public function rebuild_closure()
    {
        $node_meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $node_meta->get_name());
        $closure_meta = $this->get_entity_manager()->get_class_metadata($config['closure']);
        $insert_closures = function ($entries) use ($closure_meta): void {
            $closure_table = $closure_meta->get_table_name();
            $ancestor_column_name = $this->get_join_column_field_name($closure_meta->get_association_mapping('ancestor'));
            $descendant_column_name = $this->get_join_column_field_name($closure_meta->get_association_mapping('descendant'));
            $depth_column_name = $closure_meta->get_column_name('depth');
            $conn = $this->get_entity_manager()->get_connection();
            $conn->begin_transaction();
            foreach ($entries as $entry) {
                $conn->insert($closure_table, array_combine([$ancestor_column_name, $descendant_column_name, $depth_column_name], $entry));
            }
            $conn->commit();
        };
        $build_closures = function ($dql) use ($insert_closures): int {
            $new_closures_count = 0;
            $batch_size = 1000;
            $q = $this->get_entity_manager()->create_query($dql)->set_max_results($batch_size)->set_cacheable(false);
            do {
                $entries = $q->get_scalar_result();
                $insert_closures($entries);
                $new_closures_count += count($entries);
            } while ([] !== $entries);
            return $new_closures_count;
        };
        $node_id_field = $node_meta->get_single_identifier_field_name();
        $new_closures_count = $build_closures("\n          SELECT node.{$node_id_field} AS ancestor, node.{$node_id_field} AS descendant, 0 AS depth\n          FROM {$node_meta->get_name()} AS node\n          LEFT JOIN {$closure_meta->get_name()} AS c WITH c.ancestor = node AND c.depth = 0\n          WHERE c.id IS NULL\n        ");
        return $new_closures_count + $build_closures("\n          SELECT IDENTITY(c1.ancestor) AS ancestor, node.{$node_id_field} AS descendant, c1.depth + 1 AS depth\n          FROM {$node_meta->get_name()} AS node\n          INNER JOIN {$closure_meta->get_name()} AS c1 WITH c1.descendant = node.{$config['parent']}\n          LEFT  JOIN {$closure_meta->get_name()} AS c2 WITH c2.descendant = node.{$node_id_field} AND c2.ancestor = c1.ancestor\n          WHERE c2.id IS NULL AND node.{$node_id_field} <> c1.ancestor\n        ");
    }
    /**
     * @return int
     */
    public function clean_up_closure()
    {
        $conn = $this->get_entity_manager()->get_connection();
        $node_meta = $this->get_class_metadata();
        $node_id_field = $node_meta->get_single_identifier_field_name();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $node_meta->get_name());
        $closure_meta = $this->get_entity_manager()->get_class_metadata($config['closure']);
        $closure_table_name = $closure_meta->get_table_name();
        $dql = "\n            SELECT c1.id AS id\n            FROM {$closure_meta->get_name()} AS c1\n            LEFT JOIN {$node_meta->get_name()} AS node WITH c1.descendant = node.{$node_id_field}\n            LEFT JOIN {$closure_meta->get_name()} AS c2 WITH c2.descendant = node.{$config['parent']} AND c2.ancestor = c1.ancestor\n            WHERE c2.id IS NULL AND c1.descendant <> c1.ancestor\n        ";
        $deleted_closures_count = 0;
        $batch_size = 1000;
        $q = $this->get_entity_manager()->create_query($dql)->set_max_results($batch_size)->set_cacheable(false);
        while (($ids = $q->get_scalar_result()) && [] !== $ids) {
            $ids = array_map(static fn(array $el) => $el['id'], $ids);
            $query = "DELETE FROM {$closure_table_name} WHERE id IN (" . implode(', ', $ids) . ')';
            if (0 === $conn->execute_statement($query)) {
                throw new \RuntimeException('Failed to remove incorrect closures');
            }
            $deleted_closures_count += count($ids);
        }
        return $deleted_closures_count;
    }
    /**
     * @return int
     */
    public function update_level_values()
    {
        $node_meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $node_meta->get_name());
        $level_updates_count = 0;
        if (!empty($config['level'])) {
            $level_field = $config['level'];
            $node_id_field = $node_meta->get_single_identifier_field_name();
            $closure_meta = $this->get_entity_manager()->get_class_metadata($config['closure']);
            $batch_size = 1000;
            $q = $this->get_entity_manager()->create_query("\n                SELECT node.{$node_id_field} AS id, node.{$level_field} AS node_level, MAX(c.depth) AS closure_level\n                FROM {$node_meta->get_name()} AS node\n                INNER JOIN {$closure_meta->get_name()} AS c WITH c.descendant = node.{$node_id_field}\n                GROUP BY node.{$node_id_field}, node.{$level_field}\n                HAVING node.{$level_field} IS NULL OR node.{$level_field} <> MAX(c.depth) + 1\n            ")->set_max_results($batch_size)->set_cacheable(false);
            do {
                $entries = $q->get_scalar_result();
                $this->get_entity_manager()->get_connection()->begin_transaction();
                foreach ($entries as $entry) {
                    unset($entry['node_level']);
                    $this->get_entity_manager()->create_query("\n                      UPDATE {$node_meta->get_name()} AS node SET node.{$level_field} = (:closure_level + 1) WHERE node.{$node_id_field} = :id\n                    ")->execute($entry);
                }
                $this->get_entity_manager()->get_connection()->commit();
                $level_updates_count += count($entries);
            } while ([] !== $entries);
        }
        return $level_updates_count;
    }
    protected function validate(): bool
    {
        return Strategy::CLOSURE === $this->listener->get_strategy($this->get_entity_manager(), $this->get_class_metadata()->name)->get_name();
    }
    /**
     * @param array<string, mixed> $association
     *
     * @return string|null
     */
    protected function get_join_column_field_name($association)
    {
        if (count($association['joinColumnFieldNames']) > 1) {
            throw new \RuntimeException('More association on field ' . $association['fieldName']);
        }
        return array_shift($association['joinColumnFieldNames']);
    }
}