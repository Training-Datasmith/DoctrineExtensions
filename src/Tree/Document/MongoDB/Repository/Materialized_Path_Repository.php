<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Document\Mongo_Db\Repository;

use Doctrine\ODM\Mongo_Db\Iterator\Iterator;
use Doctrine\ODM\Mongo_Db\Query\Builder;
use Doctrine\ODM\Mongo_Db\Query\Query;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Tool\Wrapper\Mongo_Document_Wrapper;
use Gedmo\Tree\Strategy;
use Mongo_Db\BSON\Regex;
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
     * @param object|null $rootNode
     *
     * @return Builder
     */
    public function get_tree_query_builder($root_node = null)
    {
        return $this->get_children_query_builder($root_node, false, null, 'asc', true);
    }
    /**
     * Get tree query
     *
     * @param object|null $rootNode
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
     * @param object|null $rootNode
     *
     * @phpstan-return Iterator<object>
     */
    public function get_tree($root_node = null): Iterator
    {
        return $this->get_tree_query($root_node)->getIterator();
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
        return $this->get_root_nodes_query($sort_by_field, $direction)->getIterator();
    }
    public function child_count($node = null, $direct = false): int
    {
        $meta = $this->get_class_metadata();
        if (is_object($node)) {
            if (!is_a($node, $meta->get_name())) {
                throw new InvalidArgumentException('Node is not related to this repository');
            }
            $wrapped = new Mongo_Document_Wrapper($node, $this->dm);
            if (!$wrapped->has_valid_identifier()) {
                throw new InvalidArgumentException('Node is not managed by UnitOfWork');
            }
        }
        $qb = $this->get_children_query_builder($node, $direct);
        $qb->count();
        return (int) $qb->get_query()->execute();
    }
    public function get_children_query_builder($node = null, $direct = false, $sort_by_field = null, $direction = 'asc', $include_node = false)
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->dm, $meta->get_name());
        $separator = preg_quote($config['path_separator']);
        $qb = $this->dm->create_query_builder()->find($meta->get_name());
        $regex = false;
        if (is_a($node, $meta->get_name())) {
            $node = new Mongo_Document_Wrapper($node, $this->dm);
            $node_path = preg_quote($node->get_property_value($config['path']));
            if ($direct) {
                $regex = sprintf('^%s([^%s]+%s)' . ($include_node ? '?' : '') . '$', $node_path, $separator, $separator);
            } else {
                $regex = sprintf('^%s(.+)' . ($include_node ? '?' : ''), $node_path);
            }
        } elseif ($direct) {
            $regex = sprintf('^([^%s]+)' . ($include_node ? '?' : '') . '%s$', $separator, $separator);
        }
        if ($regex) {
            $qb->field($config['path'])->equals(new Regex($regex));
        }
        $qb->sort($sort_by_field ?? $config['path'], 'asc' === strtolower($direction) ? 'asc' : 'desc');
        return $qb;
    }
    /**
     * G{@inheritdoc}
     */
    public function get_children_query($node = null, $direct = false, $sort_by_field = null, $direction = 'asc', $include_node = false)
    {
        return $this->get_children_query_builder($node, $direct, $sort_by_field, $direction, $include_node)->get_query();
    }
    public function get_children($node = null, $direct = false, $sort_by_field = null, $direction = 'asc', $include_node = false)
    {
        return $this->get_children_query($node, $direct, $sort_by_field, $direction, $include_node)->getIterator();
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
        $query = $this->get_nodes_hierarchy_query($node, $direct, $options, $include_node);
        $query->set_hydrate(false);
        return $query->to_array();
    }
    protected function validate(): bool
    {
        return Strategy::MATERIALIZED_PATH === $this->listener->get_strategy($this->dm, $this->get_class_metadata()->name)->get_name();
    }
}