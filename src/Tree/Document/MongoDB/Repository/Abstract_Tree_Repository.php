<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Document\Mongo_Db\Repository;

use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Doctrine\ODM\Mongo_Db\Query\Builder;
use Doctrine\ODM\Mongo_Db\Query\Query;
use Doctrine\ODM\Mongo_Db\Repository\Document_Repository;
use Doctrine\ODM\Mongo_Db\Unit_Of_Work;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Tree\Repository_Interface;
use Gedmo\Tree\Repository_Utils;
use Gedmo\Tree\Repository_Utils_Interface;
use Gedmo\Tree\Tree_Listener;
/**
 * @template T of object
 *
 * @phpstan-extends DocumentRepository<T>
 *
 * @phpstan-implements RepositoryInterface<T>
 */
abstract class Abstract_Tree_Repository extends Document_Repository implements Repository_Interface
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
    public function __construct(Document_Manager $em, Unit_Of_Work $uow, Class_Metadata $class)
    {
        parent::__construct($em, $uow, $class);
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
            throw new Invalid_Mapping_Exception('This repository can be attached only to ODM MongoDB tree listener');
        }
        $this->listener = $tree_listener;
        if (!$this->validate()) {
            throw new Invalid_Mapping_Exception('This repository cannot be used for tree type: ' . $tree_listener->get_strategy($em, $class->get_name())->get_name());
        }
        $this->repo_utils = new Repository_Utils($this->dm, $this->get_class_metadata(), $this->listener, $this);
    }
    /**
     * Sets the RepositoryUtilsInterface instance
     *
     * @return $this
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
    public function children_hierarchy($node = null, $direct = false, array $options = [], $include_node = false)
    {
        return $this->repo_utils->children_hierarchy($node, $direct, $options, $include_node);
    }
    public function build_tree(array $nodes, array $options = [])
    {
        return $this->repo_utils->build_tree($nodes, $options);
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
    public function build_tree_array(array $nodes)
    {
        return $this->repo_utils->build_tree_array($nodes);
    }
    /**
     * Get all root nodes query builder
     *
     * @param string|null $sortByField Sort by field
     * @param string      $direction   Sort direction ("asc" or "desc")
     *
     * @return Builder
     */
    abstract public function get_root_nodes_query_builder($sort_by_field = null, $direction = 'asc');
    /**
     * Get all root nodes query
     *
     * @param string|null $sortByField Sort by field
     * @param string      $direction   Sort direction ("asc" or "desc")
     *
     * @return Query
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
     * @return Builder
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
     * @return Query
     */
    abstract public function get_nodes_hierarchy_query($node = null, $direct = false, array $options = [], $include_node = false);
    /**
     * Get list of children followed by given $node. This returns a QueryBuilder object
     *
     * @param object $node        if null, all tree nodes will be taken
     * @param bool   $direct      true to take only direct children
     * @param string $sortByField field name to sort by
     * @param string $direction   sort direction : "ASC" or "DESC"
     * @param bool   $includeNode Include the root node in results?
     *
     * @return Builder
     */
    abstract public function get_children_query_builder($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false);
    /**
     * Get list of children followed by given $node. This returns a Query
     *
     * @param object $node        if null, all tree nodes will be taken
     * @param bool   $direct      true to take only direct children
     * @param string $sortByField field name to sort by
     * @param string $direction   sort direction : "ASC" or "DESC"
     * @param bool   $includeNode Include the root node in results?
     *
     * @return Query
     */
    abstract public function get_children_query($node = null, $direct = false, $sort_by_field = null, $direction = 'ASC', $include_node = false);
    /**
     * Checks if current repository is right
     * for currently used tree strategy
     *
     * @return bool
     */
    abstract protected function validate();
}