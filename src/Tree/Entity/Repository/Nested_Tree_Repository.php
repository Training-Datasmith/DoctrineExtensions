<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Entity\Repository;

use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\Exception\Orm_Exception;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query_Builder;
use Doctrine\Persistence\Proxy;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Exception\RuntimeException;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Tool\ORM\Repository\Entity_Repository_Compat;
use Gedmo\Tool\Wrapper\Entity_Wrapper;
use Gedmo\Tree\Node;
use Gedmo\Tree\Strategy;
use Gedmo\Tree\Strategy\ORM\Nested;
/**
 * The NestedTreeRepository has some useful functions
 * to interact with NestedSet tree. Repository uses
 * the strategy used by listener
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @template T of object
 *
 * @template-extends AbstractTreeRepository<T>
 *
 * @method persistAsFirstChild($node)
 * @method persistAsFirstChildOf($node, $parent)
 * @method persistAsLastChild($node)
 * @method persistAsLastChildOf($node, $parent)
 * @method persistAsNextSibling($node)
 * @method persistAsNextSiblingOf($node, $sibling)
 * @method persistAsPrevSibling($node)
 * @method persistAsPrevSiblingOf($node, $sibling)
 */
class Nested_Tree_Repository extends Abstract_Tree_Repository
{
    use Entity_Repository_Compat;
    public function get_root_nodes_query_builder($sort_by_field = null, $direction = 'asc')
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $qb = $this->get_query_builder();
        $qb->select('node')->from($config['useObjectClass'], 'node')->where($qb->expr()->is_null('node.' . $config['parent']));
        if (null !== $sort_by_field) {
            $sort_by_field = (array) $sort_by_field;
            $direction = (array) $direction;
            foreach ($sort_by_field as $key => $field) {
                $field_direction = $direction[$key] ?? 'asc';
                if ($meta->has_field($field) || $meta->is_single_valued_association($field)) {
                    $qb->add_order_by('node.' . $field, 'asc' === strtolower($field_direction) ? 'asc' : 'desc');
                }
            }
        } else {
            $qb->order_by('node.' . $config['left'], 'ASC');
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
     * Get the Tree path query builder by given $node
     *
     * @param object $node
     *
     * @phpstan-param array{includeNode?: bool} $options
     *
     * options:
     * - includeNode: (bool) Whether to include the node itself. Defaults to true.
     *
     * @throws InvalidArgumentException if input is not valid
     *
     * @return QueryBuilder
     */
    public function get_path_query_builder($node)
    {
        $options = func_get_args()[1] ?? [];
        if (!\is_array($options)) {
            throw new \TypeError('Argument 2 MUST be an array.');
        }
        $default_options = ['includeNode' => true];
        $options += $default_options;
        $meta = $this->get_class_metadata();
        if (!is_a($node, $meta->get_name())) {
            throw new InvalidArgumentException('Node is not related to this repository');
        }
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $wrapped = new Entity_Wrapper($node, $this->get_entity_manager());
        if (!$wrapped->has_valid_identifier()) {
            throw new InvalidArgumentException('Node is not managed by UnitOfWork');
        }
        $left = $wrapped->get_property_value($config['left']);
        $right = $wrapped->get_property_value($config['right']);
        $qb = $this->get_query_builder();
        $qb->select('node')->from($config['useObjectClass'], 'node')->order_by('node.' . $config['left'], 'ASC');
        if ($options['includeNode']) {
            $qb->where($qb->expr()->lte('node.' . $config['left'], $left))->and_where($qb->expr()->gte('node.' . $config['right'], $right));
        } else {
            $qb->where($qb->expr()->lt('node.' . $config['left'], $left))->and_where($qb->expr()->gt('node.' . $config['right'], $right));
        }
        if (isset($config['root'])) {
            $root_id = $wrapped->get_property_value($config['root']);
            $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
            $qb->set_parameter('rid', $root_id);
        }
        return $qb;
    }
    /**
     * Get the Tree path query by given $node
     *
     * @param object $node
     *
     * @phpstan-param array{includeNode?: bool} $options
     *
     * options:
     * - includeNode: (bool) Whether to include the node itself. Defaults to true.
     *
     * @return Query
     */
    public function get_path_query($node)
    {
        $options = func_get_args()[1] ?? [];
        if (!\is_array($options)) {
            throw new \TypeError('Argument 2 MUST be an array.');
        }
        return $this->get_path_query_builder($node, $options)->get_query();
    }
    /**
     * Get the Tree path of Nodes by given $node
     *
     * @param object $node
     *
     * @phpstan-param array{includeNode?: bool} $options
     *
     * options:
     * - includeNode: (bool) Whether to include the node itself. Defaults to true.
     *
     * @return array list of Nodes in path
     */
    public function get_path($node)
    {
        $options = func_get_args()[1] ?? [];
        if (!\is_array($options)) {
            throw new \TypeError('Argument 2 MUST be an array.');
        }
        return $this->get_path_query($node, $options)->get_result();
    }
    /**
     * Get the Tree path of Nodes by given $node as a string
     *
     * @phpstan-param array{
     *     includeNode?: bool,
     *     separator?: string,
     *     stringMethod?: string
     * } $options
     *
     * options:
     * - includeNode:  (bool)   Whether to include the node itself. Defaults to true.
     * - separator:    (string) The string separating the nodes of the tree. Defaults to ' > '.
     * - stringMethod: (string) Entity method returning its displayable name. Defaults to '__toString'.
     *
     * @throws InvalidArgumentException
     */
    public function get_path_as_string(object $node, array $options = []): string
    {
        $default_options = ['includeNode' => true, 'separator' => ' > ', 'stringMethod' => '__toString'];
        $options += $default_options;
        if (!is_string($options['stringMethod'])) {
            throw new InvalidArgumentException(sprintf('"stringMethod" option passed in argument 2 to %s must be a valid string.', __METHOD__));
        }
        if (!method_exists($node, $options['stringMethod'])) {
            throw new InvalidArgumentException(sprintf('%s must implement method "%s".', get_class($node), $options['stringMethod']));
        }
        $path = [];
        foreach ($this->get_path($node, $options) as $path_node) {
            $path[] = $path_node->{$options['stringMethod']}();
        }
        return implode($options['separator'], $path);
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
        $qb->select('node')->from($config['useObjectClass'], 'node');
        if (null !== $node) {
            if (is_a($node, $meta->get_name())) {
                $wrapped = new Entity_Wrapper($node, $this->get_entity_manager());
                if (!$wrapped->has_valid_identifier()) {
                    throw new InvalidArgumentException('Node is not managed by UnitOfWork');
                }
                if ($direct) {
                    $qb->where($qb->expr()->eq('node.' . $config['parent'], ':pid'));
                    $qb->set_parameter('pid', $wrapped->get_identifier());
                } else {
                    $left = $wrapped->get_property_value($config['left']);
                    $right = $wrapped->get_property_value($config['right']);
                    if ($left && $right) {
                        $qb->where($qb->expr()->lt('node.' . $config['right'], $right));
                        $qb->and_where($qb->expr()->gt('node.' . $config['left'], $left));
                    }
                }
                if (isset($config['root'])) {
                    $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
                    $qb->set_parameter('rid', $wrapped->get_property_value($config['root']));
                }
                if ($include_node) {
                    $id_field = $meta->get_single_identifier_field_name();
                    $qb->where('(' . $qb->get_dql_part('where') . ') OR node.' . $id_field . ' = :rootNode');
                    $qb->set_parameter('rootNode', $node);
                }
            } else {
                throw new \InvalidArgumentException('Node is not related to this repository');
            }
        } else if ($direct) {
            $qb->where($qb->expr()->is_null('node.' . $config['parent']));
        }
        if (!$sort_by_field) {
            $qb->order_by('node.' . $config['left'], 'ASC');
        } elseif (is_array($sort_by_field)) {
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
        return $qb;
    }
    /**
     * @param object|null          $node        if null, all tree nodes will be taken
     * @param bool                 $direct      true to take only direct children
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
     * @param object|null          $node        The object to fetch children for; if null, all nodes will be retrieved
     * @param bool                 $direct      Flag indicating whether only direct children should be retrieved
     * @param string|string[]|null $sortByField Field name or array of fields names to sort by
     * @param string|string[]      $direction   Sort order ('asc'|'desc'|'ASC'|'DESC'). If $sortByField is an array, this may also be an array with matching number of elements
     * @param bool                 $includeNode Flag indicating whether the given node should be included in the results
     *
     * @phpstan-param 'asc'|'desc'|'ASC'|'DESC'|array<int, 'asc'|'desc'|'ASC'|'DESC'> $direction
     *
     * @return array<int, object> List of children
     */
    public function children($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false)
    {
        return $this->children_query($node, $direct, $sort_by_field, $direction, $include_node)->get_result();
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
     * @return array<int, object>
     */
    public function get_children($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false)
    {
        return $this->children($node, $direct, $sort_by_field, $direction, $include_node);
    }
    /**
     * Get tree leafs query builder
     *
     * @param object $root        root node in case of root tree is required
     * @param string $sortByField field name to sort by
     * @param string $direction   sort direction : "ASC" or "DESC"
     *
     * @phpstan-param 'asc'|'desc'|'ASC'|'DESC' $direction
     *
     * @throws InvalidArgumentException if input is not valid
     *
     * @return QueryBuilder
     */
    public function get_leafs_query_builder($root = null, $sort_by_field = null, $direction = 'ASC')
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        if (isset($config['root']) && null === $root) {
            throw new InvalidArgumentException('If tree has root, getLeafs method requires any node of this tree');
        }
        $qb = $this->get_query_builder();
        $qb->select('node')->from($config['useObjectClass'], 'node')->where($qb->expr()->eq('node.' . $config['right'], '1 + node.' . $config['left']));
        if (isset($config['root'])) {
            if (is_a($root, $meta->get_name())) {
                $wrapped = new Entity_Wrapper($root, $this->get_entity_manager());
                $root_id = $wrapped->get_property_value($config['root']);
                if (!$root_id) {
                    throw new InvalidArgumentException('Root node must be managed');
                }
                $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
                $qb->set_parameter('rid', $root_id);
            } else {
                throw new InvalidArgumentException('Node is not related to this repository');
            }
        }
        if (!$sort_by_field) {
            if (isset($config['root'])) {
                $qb->add_order_by('node.' . $config['root'], 'ASC');
            }
            $qb->add_order_by('node.' . $config['left'], 'ASC');
        } else if ($meta->has_field($sort_by_field) && in_array(strtolower($direction), ['asc', 'desc'], true)) {
            $qb->order_by('node.' . $sort_by_field, $direction);
        } else {
            throw new InvalidArgumentException("Invalid sort options specified: field - {$sort_by_field}, direction - {$direction}");
        }
        return $qb;
    }
    /**
     * Get tree leafs query
     *
     * @param object $root        root node in case of root tree is required
     * @param string $sortByField field name to sort by
     * @param string $direction   sort direction : "ASC" or "DESC"
     *
     * @phpstan-param 'asc'|'desc'|'ASC'|'DESC' $direction
     *
     * @return Query
     */
    public function get_leafs_query($root = null, $sort_by_field = null, $direction = 'ASC')
    {
        return $this->get_leafs_query_builder($root, $sort_by_field, $direction)->get_query();
    }
    /**
     * Get list of leaf nodes of the tree
     *
     * @param object $root        root node in case of root tree is required
     * @param string $sortByField field name to sort by
     * @param string $direction   sort direction : "ASC" or "DESC"
     *
     * @phpstan-param 'asc'|'desc'|'ASC'|'DESC' $direction
     *
     * @return array<int, object>
     */
    public function get_leafs($root = null, $sort_by_field = null, $direction = 'ASC')
    {
        return $this->get_leafs_query($root, $sort_by_field, $direction)->get_result();
    }
    /**
     * Get the query builder for next siblings of the given $node
     *
     * @param object $node
     * @param bool   $includeSelf include the node itself
     *
     * @throws InvalidArgumentException if input is invalid
     *
     * @return QueryBuilder
     */
    public function get_next_siblings_query_builder($node, $include_self = false)
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
        $parent = $wrapped->get_property_value($config['parent']);
        $left = $wrapped->get_property_value($config['left']);
        $qb = $this->get_query_builder();
        $qb->select('node')->from($config['useObjectClass'], 'node')->where($include_self ? $qb->expr()->gte('node.' . $config['left'], $left) : $qb->expr()->gt('node.' . $config['left'], $left))->order_by("node.{$config['left']}", 'ASC');
        if ($parent) {
            $wrapped_parent = new Entity_Wrapper($parent, $this->get_entity_manager());
            $qb->and_where($qb->expr()->eq('node.' . $config['parent'], ':pid'));
            $qb->set_parameter('pid', $wrapped_parent->get_identifier());
        } elseif (isset($config['root'])) {
            $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':root'));
            $qb->and_where($qb->expr()->is_null('node.' . $config['parent']));
            $root = isset($config['rootIdentifierMethod']) ? $node->{$config['rootIdentifierMethod']}() : $wrapped->get_property_value($config['root']);
            $qb->set_parameter('root', $root);
        } else {
            $qb->and_where($qb->expr()->is_null('node.' . $config['parent']));
        }
        return $qb;
    }
    /**
     * Get the query for next siblings of the given $node
     *
     * @param object $node
     * @param bool   $includeSelf include the node itself
     *
     * @return Query
     */
    public function get_next_siblings_query($node, $include_self = false)
    {
        return $this->get_next_siblings_query_builder($node, $include_self)->get_query();
    }
    /**
     * Find the next siblings of the given $node
     *
     * @param object $node
     * @param bool   $includeSelf include the node itself
     *
     * @return array<int, object>
     */
    public function get_next_siblings($node, $include_self = false)
    {
        return $this->get_next_siblings_query($node, $include_self)->get_result();
    }
    /**
     * Get query builder for previous siblings of the given $node
     *
     * @param object $node
     * @param bool   $includeSelf include the node itself
     *
     * @throws InvalidArgumentException if input is invalid
     *
     * @return QueryBuilder
     */
    public function get_prev_siblings_query_builder($node, $include_self = false)
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
        $parent = $wrapped->get_property_value($config['parent']);
        $left = $wrapped->get_property_value($config['left']);
        $qb = $this->get_query_builder();
        $qb->select('node')->from($config['useObjectClass'], 'node')->where($include_self ? $qb->expr()->lte('node.' . $config['left'], $left) : $qb->expr()->lt('node.' . $config['left'], $left))->order_by("node.{$config['left']}", 'ASC');
        if ($parent) {
            $wrapped_parent = new Entity_Wrapper($parent, $this->get_entity_manager());
            $qb->and_where($qb->expr()->eq('node.' . $config['parent'], ':pid'));
            $qb->set_parameter('pid', $wrapped_parent->get_identifier());
        } elseif (isset($config['root'])) {
            $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':root'));
            $qb->and_where($qb->expr()->is_null('node.' . $config['parent']));
            $method = $config['rootIdentifierMethod'];
            $qb->set_parameter('root', $node->{$method}());
        } else {
            $qb->and_where($qb->expr()->is_null('node.' . $config['parent']));
        }
        return $qb;
    }
    /**
     * Get query for previous siblings of the given $node
     *
     * @param object $node
     * @param bool   $includeSelf include the node itself
     *
     * @throws InvalidArgumentException if input is invalid
     *
     * @return Query
     */
    public function get_prev_siblings_query($node, $include_self = false)
    {
        return $this->get_prev_siblings_query_builder($node, $include_self)->get_query();
    }
    /**
     * Find the previous siblings of the given $node
     *
     * @param object $node
     * @param bool   $includeSelf include the node itself
     *
     * @return array<int, object>
     */
    public function get_prev_siblings($node, $include_self = false)
    {
        return $this->get_prev_siblings_query($node, $include_self)->get_result();
    }
    /**
     * Move the node down in the same level
     *
     * @param object   $node
     * @param int|bool $number integer - number of positions to shift
     *                         boolean - if "true" - shift till last position
     *
     * @throws \RuntimeException if something fails in transaction
     *
     * @return bool true if shifted
     */
    public function move_down($node, $number = 1)
    {
        $result = false;
        $meta = $this->get_class_metadata();
        if (is_a($node, $meta->get_name())) {
            $next_siblings = $this->get_next_siblings($node);
            if ($num_siblings = count($next_siblings)) {
                $result = true;
                if (true === $number) {
                    $number = $num_siblings;
                } elseif ($number > $num_siblings) {
                    $number = $num_siblings;
                }
                $this->listener->get_strategy($this->get_entity_manager(), $meta->get_name())->update_node($this->get_entity_manager(), $node, $next_siblings[$number - 1], Nested::NEXT_SIBLING);
            }
        } else {
            throw new InvalidArgumentException('Node is not related to this repository');
        }
        return $result;
    }
    /**
     * Move the node up in the same level
     *
     * @param object   $node
     * @param int|bool $number integer - number of positions to shift
     *                         boolean - true shift till first position
     *
     * @throws \RuntimeException if something fails in transaction
     *
     * @return bool true if shifted
     */
    public function move_up($node, $number = 1)
    {
        $result = false;
        $meta = $this->get_class_metadata();
        if (is_a($node, $meta->get_name())) {
            $prev_siblings = array_reverse($this->get_prev_siblings($node));
            if ($num_siblings = count($prev_siblings)) {
                $result = true;
                if (true === $number) {
                    $number = $num_siblings;
                } elseif ($number > $num_siblings) {
                    $number = $num_siblings;
                }
                $this->listener->get_strategy($this->get_entity_manager(), $meta->get_name())->update_node($this->get_entity_manager(), $node, $prev_siblings[$number - 1], Nested::PREV_SIBLING);
            }
        } else {
            throw new InvalidArgumentException('Node is not related to this repository');
        }
        return $result;
    }
    /**
     * UNSAFE: be sure to backup before running this method when necessary
     *
     * Removes given $node from the tree and reparents its descendants
     *
     * @param object $node
     *
     * @throws \RuntimeException if something fails in transaction
     */
    public function remove_from_tree($node): void
    {
        $meta = $this->get_class_metadata();
        if (is_a($node, $meta->get_name())) {
            $wrapped = new Entity_Wrapper($node, $this->get_entity_manager());
            $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
            $right = $wrapped->get_property_value($config['right']);
            $left = $wrapped->get_property_value($config['left']);
            $root_id = isset($config['root']) ? $wrapped->get_property_value($config['root']) : null;
            // if node has no children
            if ($right == $left + 1) {
                $this->remove_single($wrapped);
                $this->listener->get_strategy($this->get_entity_manager(), $meta->get_name())->shift_rl($this->get_entity_manager(), $config['useObjectClass'], $right, -2, $root_id);
                return;
                // node was a leaf
            }
            // process updates in transaction
            $this->get_entity_manager()->get_connection()->begin_transaction();
            try {
                $parent = $wrapped->get_property_value($config['parent']);
                $parent_id = null;
                if ($parent) {
                    $wrapped_parent = new Entity_Wrapper($parent, $this->get_entity_manager());
                    $parent_id = $wrapped_parent->get_identifier();
                }
                $pk = $meta->get_single_identifier_field_name();
                $node_id = $wrapped->get_identifier();
                $shift = -1;
                // in case if root node is removed, children become roots
                if (isset($config['root']) && !$parent) {
                    // get node's children
                    $qb = $this->get_query_builder();
                    $qb->select('node.' . $pk, 'node.' . $config['left'], 'node.' . $config['right'])->from($config['useObjectClass'], 'node');
                    $qb->and_where($qb->expr()->eq('node.' . $config['parent'], ':pid'));
                    $qb->set_parameter('pid', $node_id);
                    $nodes = $qb->get_query()->to_iterable([], Query::HYDRATE_ARRAY);
                    // go through each of the node's children
                    foreach ($nodes as $new_root) {
                        $left = $new_root[$config['left']];
                        $right = $new_root[$config['right']];
                        $root_id = $new_root[$pk];
                        $shift = -($left - 1);
                        // set the root of this child node and its children to the newly formed tree
                        $qb = $this->get_query_builder();
                        $qb->update($config['useObjectClass'], 'node');
                        $qb->set('node.' . $config['root'], ':rid');
                        $qb->set_parameter('rid', $root_id);
                        $qb->where($qb->expr()->eq('node.' . $config['root'], ':rpid'));
                        $qb->set_parameter('rpid', $node_id);
                        $qb->and_where($qb->expr()->gte('node.' . $config['left'], $left));
                        $qb->and_where($qb->expr()->lte('node.' . $config['right'], $right));
                        $qb->get_query()->get_single_scalar_result();
                        // Set the parent to NULL for this child node, i.e. make it root
                        $qb = $this->get_query_builder();
                        $qb->update($config['useObjectClass'], 'node');
                        $qb->set('node.' . $config['parent'], ':pid');
                        $qb->set_parameter('pid', $parent_id);
                        $qb->where($qb->expr()->eq('node.' . $config['parent'], ':rpid'));
                        $qb->set_parameter('rpid', $node_id);
                        $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
                        $qb->set_parameter('rid', $root_id);
                        $qb->get_query()->get_single_scalar_result();
                        // fix left, right and level values for the newly formed tree
                        $this->listener->get_strategy($this->get_entity_manager(), $meta->get_name())->shift_range_rl($this->get_entity_manager(), $config['useObjectClass'], $left, $right, $shift, $root_id, $root_id, -1);
                        $this->listener->get_strategy($this->get_entity_manager(), $meta->get_name())->shift_rl($this->get_entity_manager(), $config['useObjectClass'], $right, -2, $root_id);
                    }
                } else {
                    // set parent of all direct children to be the parent of the node being deleted
                    $qb = $this->get_query_builder();
                    $qb->update($config['useObjectClass'], 'node');
                    $qb->set('node.' . $config['parent'], ':pid');
                    $qb->set_parameter('pid', $parent_id);
                    $qb->where($qb->expr()->eq('node.' . $config['parent'], ':rpid'));
                    $qb->set_parameter('rpid', $node_id);
                    if (isset($config['root'])) {
                        $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
                        $qb->set_parameter('rid', $root_id);
                    }
                    $qb->get_query()->get_single_scalar_result();
                    // fix left, right and level values for the node's children
                    $this->listener->get_strategy($this->get_entity_manager(), $meta->get_name())->shift_range_rl($this->get_entity_manager(), $config['useObjectClass'], $left, $right, $shift, $root_id, $root_id, -1);
                    $this->listener->get_strategy($this->get_entity_manager(), $meta->get_name())->shift_rl($this->get_entity_manager(), $config['useObjectClass'], $right, -2, $root_id);
                }
                $this->remove_single($wrapped);
                $this->get_entity_manager()->get_connection()->commit();
            } catch (\Exception $e) {
                $this->get_entity_manager()->close();
                $this->get_entity_manager()->get_connection()->rollback();
                throw new RuntimeException('Transaction failed', $e->get_code(), $e);
            }
        } else {
            throw new InvalidArgumentException('Node is not related to this repository');
        }
    }
    /**
     * Reorders $node's child nodes,
     * according to the $sortByField and $direction specified
     *
     * @param object|null $node        node from which to start reordering the tree; null will reorder everything
     * @param string      $sortByField field name to sort by
     * @param string      $direction   sort direction : "ASC" or "DESC"
     * @param bool        $verify      true to verify tree first
     * @param bool        $recursive   true to also reorder further descendants, not just the direct children
     */
    public function reorder($node, $sort_by_field = null, $direction = 'ASC', $verify = true, $recursive = true): void
    {
        $meta = $this->get_class_metadata();
        if (null === $node || is_a($node, $meta->get_name())) {
            $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
            if ($verify && is_array($this->verify())) {
                return;
            }
            $nodes = $this->children($node, true, $sort_by_field, $direction);
            foreach ($nodes as $node) {
                $wrapped = new Entity_Wrapper($node, $this->get_entity_manager());
                $right = $wrapped->get_property_value($config['right']);
                $left = $wrapped->get_property_value($config['left']);
                $this->move_down($node, true);
                if ($recursive && $left != $right - 1) {
                    $this->reorder($node, $sort_by_field, $direction, false);
                }
            }
        } else {
            throw new InvalidArgumentException('Node is not related to this repository');
        }
    }
    /**
     * Reorders all nodes in the tree according to the $sortByField and $direction specified.
     *
     * @param string $sortByField field name to sort by
     * @param string $direction   sort direction : "ASC" or "DESC"
     * @param bool   $verify      true to verify tree first
     */
    public function reorder_all($sort_by_field = null, $direction = 'ASC', $verify = true): void
    {
        $this->reorder(null, $sort_by_field, $direction, $verify);
    }
    /**
     * Verifies that current tree is valid.
     * If any error is detected it will return an array
     * with a list of errors found on tree
     *
     * @phpstan-param array{treeRootNode?: object} $options
     *
     * options:
     * - treeRootNode: (object) Optional tree root node to verify, if not the whole forest (only available for forests, not for single trees).
     *
     * @return array<int, string>|bool true on success, error list on failure
     */
    public function verify()
    {
        $options = func_get_args()[0] ?? [];
        if (!\is_array($options)) {
            throw new \TypeError('Argument 1 MUST be an array.');
        }
        $default_options = ['treeRootNode' => null];
        $options += $default_options;
        if (!$this->child_count()) {
            return true;
            // tree is empty
        }
        $errors = [];
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        if (isset($config['root'])) {
            $trees = $this->get_root_nodes();
            foreach ($trees as $tree) {
                // if a root node is specified, verify only it
                if (null !== $options['treeRootNode'] && $options['treeRootNode'] !== $tree) {
                    continue;
                }
                $this->verify_tree($errors, $tree);
            }
        } else {
            $this->verify_tree($errors);
        }
        return [] !== $errors ? $errors : true;
    }
    /**
     * Tries to recover the tree, avoiding entity object hydration and using DQL
     *
     * NOTE: DQL UPDATE statements are ported directly into a Database UPDATE statement and therefore bypass any locking
     * scheme, events and do not increment the version column. Entities that are already loaded into the persistence
     * context will NOT be synced with the updated database state.
     * It is recommended to call EntityManager#clear() and retrieve new instances of any affected entity.
     *
     * @phpstan-param array{sortByField?: string, sortDirection?: string} $options
     *
     * options:
     * - sortByField:   (string) Optionally sort siblings by specified field while recovering. Defaults to null.
     * - sortDirection: (string) The order to sort siblings in, when sortByField is specified ('ASC', 'DESC'). Defaults to 'ASC'.
     *
     * @throws ORMException
     */
    public function recover_fast(array $options = []): void
    {
        $default_options = ['sortByField' => null, 'sortDirection' => 'ASC'];
        $options += $default_options;
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->name);
        $em = $this->get_entity_manager();
        $update_qb = $em->create_query_builder()->update($meta->get_name(), 'node')->set('node.' . $config['left'], ':left')->set('node.' . $config['right'], ':right')->where('node.id = :id');
        if (isset($config['level'])) {
            $update_qb->set('node.' . $config['level'], ':level');
        }
        $do_recover = function (array $root, int &$count, int $level) use ($meta, $em, $options, $update_qb, &$do_recover): void {
            $root_entity = $em->get_reference($meta->get_name(), $root['node_id']);
            $left = $count++;
            $children_query = $this->get_children_query($root_entity, true, $options['sortByField'], $options['sortDirection']);
            foreach ($children_query->get_scalar_result() as $child) {
                $do_recover($child, $count, $level + 1);
            }
            $right = $count++;
            $update_qb->set_parameter('left', $left)->set_parameter('right', $right)->set_parameter('id', $root['node_id'])->set_parameter('level', $level)->get_query()->execute();
        };
        // if it's a forest
        if (isset($config['root'])) {
            $root_nodes_query = $this->get_root_nodes_query($options['sortByField'], $options['sortDirection']);
            $roots = $root_nodes_query->get_scalar_result();
            foreach ($roots as $root) {
                // reset on every root node
                $count = 1;
                $level = $config['level_base'] ?? 0;
                $do_recover($root, $count, $level);
                $em->clear();
            }
        } else {
            $count = 1;
            $level = $config['level_base'] ?? 0;
            $children_query = $this->get_children_query(null, true, $options['sortByField'], $options['sortDirection']);
            foreach ($children_query->get_scalar_result() as $root) {
                $do_recover($root, $count, $level);
                $em->clear();
            }
        }
    }
    /**
     * NOTE: flush your entity manager after, unless the 'flush' option has been set to true
     *
     * Tries to recover the tree
     *
     * @phpstan-param array{
     *     flush?: bool,
     *     treeRootNode?: ?object,
     *     skipVerify?: bool,
     *     sortByField?: string,
     *     sortDirection?: string
     * } $options
     *
     * options:
     * - flush:         (bool)   Flush entity manager after each root node is recovered. Defaults to false.
     * - treeRootNode:  (object) Optional tree root node to recover, if not the whole forest (only available for forests, not for single trees). Defaults to null.
     * - skipVerify:    (bool)   Whether to skip verification and recover anyway. Defaults to false.
     * - sortByField:   (string) Optionally sort siblings by specified field while recovering. Defaults to null.
     * - sortDirection: (string) The order to sort siblings in, when sortByField is specified ('ASC', 'DESC'). Defaults to 'ASC'.
     */
    public function recover(): void
    {
        $options = func_get_args()[0] ?? [];
        if (!\is_array($options)) {
            throw new \TypeError('Argument 1 MUST be an array.');
        }
        $default_options = ['flush' => false, 'treeRootNode' => null, 'skipVerify' => false, 'sortByField' => null, 'sortDirection' => 'ASC'];
        $options += $default_options;
        if (!$options['skipVerify'] && true === $this->verify()) {
            return;
        }
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $em = $this->get_entity_manager();
        $do_recover = function ($root, &$count, &$lvl) use ($meta, $config, $em, $options, &$do_recover): void {
            $left = $count++;
            foreach ($this->get_children($root, true, $options['sortByField'], $options['sortDirection']) as $child) {
                $depth = $lvl + 1;
                $do_recover($child, $count, $depth);
            }
            $right = $count++;
            $meta->set_field_value($root, $config['left'], $left);
            $meta->set_field_value($root, $config['right'], $right);
            if (isset($config['level'])) {
                $meta->set_field_value($root, $config['level'], $lvl);
            }
            $em->persist($root);
        };
        // if it's a forest
        if (isset($config['root'])) {
            foreach ($this->get_root_nodes($options['sortByField'], $options['sortDirection']) as $root) {
                // if a root node is specified, recover only it
                if (null !== $options['treeRootNode'] && $options['treeRootNode'] !== $root) {
                    continue;
                }
                $count = 1;
                // reset on every root node
                $lvl = $config['level_base'] ?? 0;
                $do_recover($root, $count, $lvl);
                if ($options['flush']) {
                    $em->flush();
                }
            }
        } else {
            $count = 1;
            $lvl = $config['level_base'] ?? 0;
            foreach ($this->get_children(null, true, $options['sortByField'], $options['sortDirection']) as $root) {
                $do_recover($root, $count, $lvl);
                if ($options['flush']) {
                    $em->flush();
                }
            }
        }
    }
    public function get_nodes_hierarchy_query_builder($node = null, $direct = false, array $options = [], $include_node = false)
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        return $this->children_query_builder($node, $direct, isset($config['root']) ? [$config['root'], $config['left']] : $config['left'], 'ASC', $include_node);
    }
    public function get_nodes_hierarchy_query($node = null, $direct = false, array $options = [], $include_node = false)
    {
        return $this->get_nodes_hierarchy_query_builder($node, $direct, $options, $include_node)->get_query();
    }
    public function get_nodes_hierarchy($node = null, $direct = false, array $options = [], $include_node = false)
    {
        return $this->get_nodes_hierarchy_query($node, $direct, $options, $include_node)->get_array_result();
    }
    /**
     * Allows the following 'virtual' methods:
     * - persistAsFirstChild($node)
     * - persistAsFirstChildOf($node, $parent)
     * - persistAsLastChild($node)
     * - persistAsLastChildOf($node, $parent)
     * - persistAsNextSibling($node)
     * - persistAsNextSiblingOf($node, $sibling)
     * - persistAsPrevSibling($node)
     * - persistAsPrevSiblingOf($node, $sibling)
     * Inherited virtual methods:
     * - find*
     *
     * @param string $method
     * @param array  $args
     *
     * @phpstan-param list<mixed> $args
     *
     * @throws \BadMethodCallException  If the method called is an invalid find* or persistAs* method
     *                                  or no find* either persistAs* method at all and therefore an invalid method call
     * @throws InvalidArgumentException If arguments are invalid
     *
     * @return mixed TreeNestedRepository if persistAs* is called
     *
     * @see \Doctrine\ORM\EntityRepository
     */
    protected function do_call_with_compat($method, $args)
    {
        if ('persistAs' === substr($method, 0, 9)) {
            if (!isset($args[0])) {
                throw new InvalidArgumentException('Node to persist must be available as first argument.');
            }
            $node = $args[0];
            $wrapped = new Entity_Wrapper($node, $this->get_entity_manager());
            $meta = $this->get_class_metadata();
            $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
            $position = substr($method, 9);
            if ('Of' === substr($method, -2)) {
                if (!isset($args[1])) {
                    throw new InvalidArgumentException('If "Of" is specified you must provide parent or sibling as the second argument.');
                }
                $parent_or_sibling = $args[1];
                if (strstr($method, 'Sibling')) {
                    $wrapped_parent_or_sibling = new Entity_Wrapper($parent_or_sibling, $this->get_entity_manager());
                    $new_parent = $wrapped_parent_or_sibling->get_property_value($config['parent']);
                    if (null === $new_parent && isset($config['root'])) {
                        throw new UnexpectedValueException('Cannot persist sibling for a root node, tree operation is not possible');
                    }
                    if (!$node instanceof Node) {
                        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2547', 'Not implementing the "%s" interface from node "%s" is deprecated since gedmo/doctrine-extensions' . ' 3.13 and will throw a "%s" error in version 4.0.', Node::class, \get_class($node), \TypeError::class);
                    }
                    // @todo: In the next major release, remove the previous condition and uncomment the following one.
                    // if (!$node instanceof Node) {
                    //     throw new \TypeError(\sprintf(
                    //         'Node MUST implement "%s" interface.',
                    //         Node::class
                    //     ));
                    // }
                    // @todo: In the next major release, remove the `method_exists()` condition and left the `else` branch.
                    if (!method_exists($node, 'setSibling')) {
                        $node->sibling = $parent_or_sibling;
                    } else {
                        $node->set_sibling($parent_or_sibling);
                    }
                    $parent_or_sibling = $new_parent;
                }
                $wrapped->set_property_value($config['parent'], $parent_or_sibling);
                $position = substr($position, 0, -2);
            }
            $wrapped->set_property_value($config['left'], 0);
            // simulate changeset
            $oid = spl_object_id($node);
            $this->listener->get_strategy($this->get_entity_manager(), $meta->get_name())->set_node_position($oid, $position);
            $this->get_entity_manager()->persist($node);
            return $this;
        }
        return parent::__call($method, $args);
    }
    protected function validate(): bool
    {
        return Strategy::NESTED === $this->listener->get_strategy($this->get_entity_manager(), $this->get_class_metadata()->name)->get_name();
    }
    /**
     * Collect errors on given tree if
     * where are any
     *
     * @param array<int, string> $errors
     */
    private function verify_tree(array &$errors, ?object $root = null): void
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $identifier = $meta->get_single_identifier_field_name();
        if ($root && isset($config['root'])) {
            $root_id = $meta->get_field_value($root, $config['root']);
            if (is_object($root_id)) {
                $root_id = $meta->get_field_value($root_id, $identifier);
            }
        } else {
            $root_id = null;
        }
        $qb = $this->get_query_builder();
        $qb->select($qb->expr()->min('node.' . $config['left']))->from($config['useObjectClass'], 'node');
        if (isset($config['root'])) {
            $qb->where($qb->expr()->eq('node.' . $config['root'], ':rid'));
            $qb->set_parameter('rid', $root_id);
        }
        $min = (int) $qb->get_query()->get_single_scalar_result();
        $edge = $this->listener->get_strategy($this->get_entity_manager(), $meta->get_name())->max($this->get_entity_manager(), $config['useObjectClass'], $root_id);
        // check duplicate right and left values
        for ($i = $min; $i <= $edge; ++$i) {
            $qb = $this->get_query_builder();
            $qb->select($qb->expr()->count('node.' . $identifier))->from($config['useObjectClass'], 'node')->where($qb->expr()->or_x($qb->expr()->eq('node.' . $config['left'], $i), $qb->expr()->eq('node.' . $config['right'], $i)));
            if (isset($config['root'])) {
                $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
                $qb->set_parameter('rid', $root_id);
            }
            $count = (int) $qb->get_query()->get_single_scalar_result();
            if (1 !== $count) {
                if (0 === $count) {
                    $errors[] = "index [{$i}], missing" . ($root ? ' on tree root: ' . $root_id : '');
                } else {
                    $errors[] = "index [{$i}], duplicate" . ($root ? ' on tree root: ' . $root_id : '');
                }
            }
        }
        // check for missing parents
        $qb = $this->get_query_builder();
        $qb->select('node')->from($config['useObjectClass'], 'node')->left_join('node.' . $config['parent'], 'parent')->where($qb->expr()->is_not_null('node.' . $config['parent']))->and_where($qb->expr()->is_null('parent.' . $identifier));
        if (isset($config['root'])) {
            $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
            $qb->set_parameter('rid', $root_id);
        }
        $are_missing_parents = false;
        foreach ($qb->get_query()->to_iterable([], Query::HYDRATE_ARRAY) as $node) {
            $are_missing_parents = true;
            $errors[] = "node [{$node[$identifier]}] has missing parent" . ($root ? ' on tree root: ' . $root_id : '');
        }
        // loading broken relation can cause infinite loop
        if ($are_missing_parents) {
            return;
        }
        // check for nodes that have a right value lower than the left
        $qb = $this->get_query_builder();
        $qb->select('node')->from($config['useObjectClass'], 'node')->where($qb->expr()->lt('node.' . $config['right'], 'node.' . $config['left']));
        if (isset($config['root'])) {
            $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
            $qb->set_parameter('rid', $root_id);
        }
        $result = $qb->get_query()->set_max_results(1)->get_result(Query::HYDRATE_ARRAY);
        $node = [] !== $result ? array_shift($result) : [];
        if ([] !== $node) {
            $id = $node[$identifier];
            $errors[] = "node [{$id}], left is greater than right" . ($root ? ' on tree root: ' . $root_id : '');
        }
        $qb = $this->get_query_builder();
        $qb->select('node')->from($config['useObjectClass'], 'node');
        if (isset($config['root'])) {
            $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
            $qb->set_parameter('rid', $root_id);
        }
        foreach ($qb->get_query()->to_iterable() as $node) {
            $right = $meta->get_field_value($node, $config['right']);
            $left = $meta->get_field_value($node, $config['left']);
            $id = $meta->get_field_value($node, $identifier);
            $parent = $meta->get_field_value($node, $config['parent']);
            if (!$right || !$left) {
                $errors[] = "node [{$id}] has invalid left or right values";
            } elseif ($right == $left) {
                $errors[] = "node [{$id}] has identical left and right values";
            } elseif ($parent) {
                if ($parent instanceof Proxy && !$parent->__is_initialized()) {
                    $this->get_entity_manager()->refresh($parent);
                }
                $parent_right = $meta->get_field_value($parent, $config['right']);
                $parent_left = $meta->get_field_value($parent, $config['left']);
                $parent_id = $meta->get_field_value($parent, $identifier);
                if ($left < $parent_left) {
                    $errors[] = "node [{$id}] left is less than parent`s [{$parent_id}] left value";
                } elseif ($right > $parent_right) {
                    $errors[] = "node [{$id}] right is greater than parent`s [{$parent_id}] right value";
                }
                // check that level of node is exactly after its parent's level
                if (isset($config['level'])) {
                    $parent_level = $meta->get_field_value($parent, $config['level']);
                    $level = $meta->get_field_value($node, $config['level']);
                    if ($level !== $parent_level + 1) {
                        $errors[] = "node [{$id}] should be on the level right after its parent`s [{$parent_id}] level";
                    }
                }
            } else {
                // check that level of the root node is the base level defined
                if (isset($config['level'])) {
                    $base_level = $config['level_base'] ?? 0;
                    $level = $meta->get_field_value($node, $config['level']);
                    if ($level !== $base_level) {
                        $errors[] = "node [{$id}] should be on level {$base_level}, not {$level}";
                    }
                }
                // get number of parents of node, based on left and right values
                $qb = $this->get_query_builder();
                $qb->select($qb->expr()->count('node.' . $identifier))->from($config['useObjectClass'], 'node')->where($qb->expr()->lt('node.' . $config['left'], $left))->and_where($qb->expr()->gt('node.' . $config['right'], $right));
                if (isset($config['root'])) {
                    $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
                    $qb->set_parameter('rid', $root_id);
                }
                if ($count = (int) $qb->get_query()->get_single_scalar_result()) {
                    $errors[] = "node [{$id}] parent field is blank, but it has a parent";
                }
            }
        }
    }
    /**
     * Removes single node without touching children
     *
     * @param EntityWrapper<object> $wrapped
     *
     * @internal
     */
    private function remove_single(Entity_Wrapper $wrapped): void
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $pk = $meta->get_single_identifier_field_name();
        $node_id = $wrapped->get_identifier();
        // prevent from deleting whole branch
        $qb = $this->get_query_builder();
        $qb->update($config['useObjectClass'], 'node')->set('node.' . $config['left'], 0)->set('node.' . $config['right'], 0);
        $qb->and_where($qb->expr()->eq('node.' . $pk, ':id'));
        $qb->set_parameter('id', $node_id);
        $qb->get_query()->get_single_scalar_result();
        // remove the node from database
        $qb = $this->get_query_builder();
        $qb->delete($config['useObjectClass'], 'node');
        $qb->and_where($qb->expr()->eq('node.' . $pk, ':id'));
        $qb->set_parameter('id', $node_id);
        $qb->get_query()->get_single_scalar_result();
        // remove from identity map
        $this->get_entity_manager()->get_unit_of_work()->remove_from_identity_map($wrapped->get_object());
    }
}