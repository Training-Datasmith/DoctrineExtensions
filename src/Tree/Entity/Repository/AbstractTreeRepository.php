<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Entity\Repository;

use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Entity_Repository;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query_Builder;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Tool\Wrapper\Entity_Wrapper;
use Gedmo\Tree\Repository_Interface;
use Gedmo\Tree\Repository_Utils;
use Gedmo\Tree\Repository_Utils_Interface;
use Gedmo\Tree\Tree_Listener;
/**
 * @template T of object
 *
 * @template-extends EntityRepository<T>
 *
 * @template-implements RepositoryInterface<T>
 */
abstract class Abstract_Tree_Repository extends Entity_Repository implements Repository_Interface
{
    /**
     * Tree listener on event manager
     */
    protected \Gedmo\Tree\Tree_Listener $listener;
    /**
     * Repository utils
     *
     * @var RepositoryUtilsInterface
     */
    protected $repo_utils;
    /** @param ClassMetadata<T> $class */
    public function __construct(Entity_Manager_Interface $em, Class_Metadata $class)
    {
        parent::__construct($em, $class);
        $tree_listener = null;
        foreach ($em->get_event_manager()->get_all_listeners() as $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof Tree_Listener) {
                    $tree_listener = $listener;
                    break 2;
                }
            }
        }
        if (null === $tree_listener) {
            throw new Invalid_Mapping_Exception('Tree listener was not found on your entity manager, it must be hooked into the event manager');
        }
        $this->listener = $tree_listener;
        if (!$this->validate()) {
            throw new Invalid_Mapping_Exception('This repository cannot be used for tree type: ' . $tree_listener->get_strategy($em, $class->get_name())->get_name());
        }
        $this->repo_utils = new Repository_Utils($this->get_entity_manager(), $this->get_class_metadata(), $this->listener, $this);
    }
    /**
     * Sets the RepositoryUtilsInterface instance
     *
     * @return static
     */
    public function set_repo_utils(Repository_Utils_Interface $repo_utils)
    {
        $this->repo_utils = $repo_utils;
        return $this;
    }
    /**
     * Returns the RepositoryUtilsInterface instance
     *
     * @return RepositoryUtilsInterface|null
     */
    public function get_repo_utils()
    {
        return $this->repo_utils;
    }
    public function child_count($node = null, $direct = false)
    {
        $meta = $this->get_class_metadata();
        if (is_object($node)) {
            if (!is_a($node, $meta->get_name())) {
                throw new InvalidArgumentException('Node is not related to this repository');
            }
            $wrapped = new Entity_Wrapper($node, $this->get_entity_manager());
            if (!$wrapped->has_valid_identifier()) {
                throw new InvalidArgumentException('Node is not managed by UnitOfWork');
            }
        }
        $qb = $this->get_children_query_builder($node, $direct);
        // We need to remove the ORDER BY DQL part since some vendors could throw an error
        // in count queries
        $dql_parts = $qb->get_dql_parts();
        // We need to check first if there's an ORDER BY DQL part, because resetDQLPart doesn't
        // check if its internal array has an "orderby" index
        if (isset($dql_parts['orderBy'])) {
            $qb->reset_dql_part('orderBy');
        }
        $aliases = $qb->get_root_aliases();
        $alias = $aliases[0];
        $qb->select('COUNT(' . $alias . ')');
        return (int) $qb->get_query()->get_single_scalar_result();
    }
    /**
     * @see RepositoryUtilsInterface::childrenHierarchy
     */
    public function children_hierarchy($node = null, $direct = false, array $options = [], $include_node = false)
    {
        return $this->repo_utils->children_hierarchy($node, $direct, $options, $include_node);
    }
    /**
     * @see RepositoryUtilsInterface::buildTree
     */
    public function build_tree(array $nodes, array $options = [])
    {
        return $this->repo_utils->build_tree($nodes, $options);
    }
    /**
     * @see RepositoryUtilsInterface::buildTreeArray
     */
    public function build_tree_array(array $nodes)
    {
        return $this->repo_utils->build_tree_array($nodes);
    }
    /**
     * @see RepositoryUtilsInterface::setChildrenIndex
     */
    public function set_children_index($children_index): void
    {
        $this->repo_utils->set_children_index($children_index);
    }
    /**
     * @see RepositoryUtilsInterface::getChildrenIndex
     */
    public function get_children_index()
    {
        return $this->repo_utils->get_children_index();
    }
    /**
     * Get all root nodes query builder
     *
     * @param string|string[]|null $sortByField Sort by field
     * @param string|string[]      $direction   Sort direction ("asc" or "desc")
     *
     * @return QueryBuilder QueryBuilder object
     */
    abstract public function get_root_nodes_query_builder($sort_by_field = null, $direction = 'asc');
    /**
     * Get all root nodes query
     *
     * @param string|string[]|null $sortByField Sort by field
     * @param string|string[]      $direction   Sort direction ("asc" or "desc")
     *
     * @return Query Query object
     */
    abstract public function get_root_nodes_query($sort_by_field = null, $direction = 'asc');
    /**
     * Returns a QueryBuilder configured to return an array of nodes suitable for buildTree method
     *
     * @param object               $node        Root node
     * @param bool                 $direct      Obtain direct children?
     * @param array<string, mixed> $options     Options
     * @param bool                 $includeNode Include node in results?
     *
     * @return QueryBuilder QueryBuilder object
     */
    abstract public function get_nodes_hierarchy_query_builder($node = null, $direct = false, array $options = [], $include_node = false);
    /**
     * Returns a Query configured to return an array of nodes suitable for buildTree method
     *
     * @param object               $node        Root node
     * @param bool                 $direct      Obtain direct children?
     * @param array<string, mixed> $options     Options
     * @param bool                 $includeNode Include node in results?
     *
     * @return Query Query object
     */
    abstract public function get_nodes_hierarchy_query($node = null, $direct = false, array $options = [], $include_node = false);
    /**
     * Get list of children followed by given $node. This returns a QueryBuilder object
     *
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
    abstract public function get_children_query_builder($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false);
    /**
     * Get list of children followed by given $node. This returns a Query
     *
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
    abstract public function get_children_query($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false);
    /**
     * @return QueryBuilder
     */
    protected function get_query_builder()
    {
        return $this->get_entity_manager()->create_query_builder();
    }
    /**
     * Checks if current repository is right
     * for currently used tree strategy
     *
     * @return bool
     */
    abstract protected function validate();
}