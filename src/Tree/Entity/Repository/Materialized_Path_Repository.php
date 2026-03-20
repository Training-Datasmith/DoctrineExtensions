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
use Gedmo\Tool\Wrapper\Entity_Wrapper;
use Gedmo\Tree\Strategy;
/**
 * The MaterializedPathRepository has some useful functions
 * to interact with MaterializedPath tree. Repository uses
 * the strategy used by listener
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @template T of object
 *
 * @template-extends AbstractTreeRepository<T>
 */
class Materialized_Path_Repository extends Abstract_Tree_Repository
{
    /**
     * Get tree query builder
     *
     * @param object $rootNode
     *
     * @return QueryBuilder
     */
    public function get_tree_query_builder($root_node = null)
    {
        return $this->get_children_query_builder($root_node, false, null, 'ASC', true);
    }
    /**
     * Get tree query
     *
     * @param object $rootNode
     *
     * @return Query
     */
    public function get_tree_query($root_node = null)
    {
        return $this->get_tree_query_builder($root_node)->get_query();
    }
    /**
     * Get tree
     *
     * @param object $rootNode
     *
     * @return array<int, object>
     */
    public function get_tree($root_node = null)
    {
        return $this->get_tree_query($root_node)->get_result();
    }
    public function get_root_nodes_query_builder($sort_by_field = null, $direction = 'asc')
    {
        return $this->get_children_query_builder(null, true, $sort_by_field, $direction);
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
     * @return QueryBuilder
     */
    public function get_path_query_builder($node)
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $alias = 'materialized_path_entity';
        $qb = $this->get_query_builder()->select($alias)->from($config['useObjectClass'], $alias);
        $node = new Entity_Wrapper($node, $this->get_entity_manager());
        $node_path = $node->get_property_value($config['path']);
        $paths = [];
        $node_path_length = strlen($node_path);
        $separator_match_offset = 0;
        while ($separator_match_offset < $node_path_length) {
            $separator_pos = strpos($node_path, $config['path_separator'], $separator_match_offset);
            if (false === $separator_pos || $separator_pos === $node_path_length - 1) {
                // last node, done
                $paths[] = $node_path;
                $separator_match_offset = $node_path_length;
            } elseif (0 === $separator_pos) {
                // path starts with separator, continue
                $separator_match_offset = 1;
            } else {
                // add node
                $paths[] = substr($node_path, 0, $config['path_ends_with_separator'] ? $separator_pos + 1 : $separator_pos);
                $separator_match_offset = $separator_pos + 1;
            }
        }
        $qb->where($qb->expr()->in($alias . '.' . $config['path'], $paths));
        $qb->order_by($alias . '.' . $config['level'], 'ASC');
        return $qb;
    }
    /**
     * Get the Tree path query by given $node
     *
     * @param object $node
     *
     * @return Query
     */
    public function get_path_query($node)
    {
        return $this->get_path_query_builder($node)->get_query();
    }
    /**
     * Get the Tree path of Nodes by given $node
     *
     * @param object $node
     *
     * @return array<int, object> list of Nodes in path
     */
    public function get_path($node)
    {
        return $this->get_path_query($node)->get_result();
    }
    public function get_children_query_builder($node = null, $direct = false, $sort_by_field = null, $direction = 'asc', $include_node = false)
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $separator = addcslashes($config['path_separator'], '%');
        $alias = 'materialized_path_entity';
        $path = $config['path'];
        $qb = $this->get_query_builder()->select($alias)->from($config['useObjectClass'], $alias);
        $expr = '';
        $include_node_expr = '';
        if (is_a($node, $meta->get_name())) {
            $node = new Entity_Wrapper($node, $this->get_entity_manager());
            $node_path = $node->get_property_value($path);
            $expr = $qb->expr()->andx()->add($qb->expr()->like($alias . '.' . $path, $qb->expr()->literal($node_path . ($config['path_ends_with_separator'] ? '' : $separator) . '%')));
            if ($include_node) {
                $include_node_expr = $qb->expr()->eq($alias . '.' . $path, $qb->expr()->literal($node_path));
            } else {
                $expr->add($qb->expr()->neq($alias . '.' . $path, $qb->expr()->literal($node_path)));
            }
            if ($direct) {
                $expr->add($qb->expr()->orx($qb->expr()->eq($alias . '.' . $config['level'], $qb->expr()->literal($node->get_property_value($config['level']))), $qb->expr()->eq($alias . '.' . $config['level'], $qb->expr()->literal($node->get_property_value($config['level']) + 1))));
            }
        } elseif ($direct) {
            $expr = $qb->expr()->not($qb->expr()->like($alias . '.' . $path, $qb->expr()->literal(($config['path_starts_with_separator'] ? $separator : '') . '%' . $separator . '%' . ($config['path_ends_with_separator'] ? $separator : ''))));
        }
        if ($expr) {
            $qb->where('(' . $expr . ')');
        }
        if ($include_node_expr) {
            $qb->or_where('(' . $include_node_expr . ')');
        }
        $order_by_field = null === $sort_by_field ? $alias . '.' . $config['path'] : $alias . '.' . $sort_by_field;
        $order_by_dir = 'asc' === strtolower($direction) ? 'asc' : 'desc';
        $qb->order_by($order_by_field, $order_by_dir);
        return $qb;
    }
    public function get_children_query($node = null, $direct = false, $sort_by_field = null, $direction = 'asc', $include_node = false)
    {
        return $this->get_children_query_builder($node, $direct, $sort_by_field, $direction, $include_node)->get_query();
    }
    public function get_children($node = null, $direct = false, $sort_by_field = null, $direction = 'asc', $include_node = false)
    {
        return $this->get_children_query($node, $direct, $sort_by_field, $direction, $include_node)->get_result();
    }
    public function get_nodes_hierarchy_query_builder($node = null, $direct = false, array $options = [], $include_node = false)
    {
        $sort_by = ['field' => null, 'dir' => 'asc'];
        if (isset($options['childSort'])) {
            $sort_by = array_merge($sort_by, $options['childSort']);
        }
        return $this->get_children_query_builder($node, $direct, $sort_by['field'], $sort_by['dir'], $include_node);
    }
    public function get_nodes_hierarchy_query($node = null, $direct = false, array $options = [], $include_node = false)
    {
        return $this->get_nodes_hierarchy_query_builder($node, $direct, $options, $include_node)->get_query();
    }
    public function get_nodes_hierarchy($node = null, $direct = false, array $options = [], $include_node = false)
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->get_entity_manager(), $meta->get_name());
        $path = $config['path'];
        $nodes = $this->get_nodes_hierarchy_query($node, $direct, $options, $include_node)->get_array_result();
        usort($nodes, static fn(array $a, array $b): int => strcmp($a[$path], $b[$path]));
        return $nodes;
    }
    protected function validate(): bool
    {
        return Strategy::MATERIALIZED_PATH === $this->listener->get_strategy($this->get_entity_manager(), $this->get_class_metadata()->name)->get_name();
    }
}