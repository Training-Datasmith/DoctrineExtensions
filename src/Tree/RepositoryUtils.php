<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree;

use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Tool\Wrapper\Entity_Wrapper;
use Gedmo\Tool\Wrapper\Mongo_Document_Wrapper;
/**
 * @final since gedmo/doctrine-extensions 3.11
 *
 * @template T of object
 */
class Repository_Utils implements Repository_Utils_Interface
{
    /** @var ClassMetadata<T> */
    protected $meta;
    /** @var TreeListener */
    protected $listener;
    /** @var ObjectManager&(DocumentManager|EntityManagerInterface) */
    protected \Doctrine\Persistence\Object_Manager $om;
    /** @var RepositoryInterface<T> */
    protected $repo;
    /**
     * This index is used to hold the children of a node
     * when using any of the buildTree related methods.
     *
     * @var string
     */
    protected $children_index = '__children';
    /**
     * @param ObjectManager&(DocumentManager|EntityManagerInterface) $om
     * @param ClassMetadata<T>                                       $meta
     * @param TreeListener                                           $listener
     * @param RepositoryInterface<T>                                 $repo
     */
    public function __construct(Object_Manager $om, Class_Metadata $meta, $listener, $repo)
    {
        $this->om = $om;
        $this->meta = $meta;
        $this->listener = $listener;
        $this->repo = $repo;
    }
    /**
     * @return ClassMetadata<T>
     */
    public function get_class_metadata()
    {
        return $this->meta;
    }
    public function children_hierarchy($node = null, $direct = false, array $options = [], $include_node = false)
    {
        $meta = $this->get_class_metadata();
        if (null !== $node) {
            if (is_a($node, $meta->get_name())) {
                $wrapper_class = $this->om instanceof Entity_Manager_Interface ? Entity_Wrapper::class : Mongo_Document_Wrapper::class;
                $wrapped = new $wrapper_class($node, $this->om);
                if (!$wrapped->has_valid_identifier()) {
                    throw new InvalidArgumentException('Node is not managed by UnitOfWork');
                }
            }
        } else {
            $include_node = true;
        }
        // Gets the array of $node results. It must be ordered by depth
        $nodes = $this->repo->get_nodes_hierarchy($node, $direct, $options, $include_node);
        return $this->repo->build_tree($nodes, $options);
    }
    public function build_tree(array $nodes, array $options = [])
    {
        $meta = $this->get_class_metadata();
        $nested_tree = $this->repo->build_tree_array($nodes);
        $default = ['decorate' => false, 'rootOpen' => '<ul>', 'rootClose' => '</ul>', 'childOpen' => '<li>', 'childClose' => '</li>', 'nodeDecorator' => static function (array $node) use ($meta) {
            // override and change it, guessing which field to use
            if ($meta->has_field('title')) {
                $field = 'title';
            } elseif ($meta->has_field('name')) {
                $field = 'name';
            } else {
                throw new InvalidArgumentException('Cannot find any representation field');
            }
            return $node[$field];
        }];
        $options = array_merge($default, $options);
        // If you don't want any html output it will return the nested array
        if (!$options['decorate']) {
            return $nested_tree;
        }
        if ([] === $nested_tree) {
            return '';
        }
        $children_index = $this->children_index;
        $build = static function ($tree) use (&$build, &$options, $children_index): string {
            $output = is_string($options['rootOpen']) ? $options['rootOpen'] : $options['rootOpen']($tree);
            foreach ($tree as $node) {
                $output .= is_string($options['childOpen']) ? $options['childOpen'] : $options['childOpen']($node);
                $output .= $options['nodeDecorator']($node);
                if ([] !== $node[$children_index]) {
                    $output .= $build($node[$children_index]);
                }
                $output .= is_string($options['childClose']) ? $options['childClose'] : $options['childClose']($node);
            }
            return $output . (is_string($options['rootClose']) ? $options['rootClose'] : $options['rootClose']($tree));
        };
        return $build($nested_tree);
    }
    /**
     * @return mixed[]
     */
    public function build_tree_array(array $nodes): array
    {
        $meta = $this->get_class_metadata();
        $config = $this->listener->get_configuration($this->om, $meta->get_name());
        $nested_tree = [];
        $l = 0;
        if ([] !== $nodes) {
            // Node Stack. Used to help building the hierarchy
            $stack = [];
            foreach ($nodes as $child) {
                $item = $child;
                $item[$this->children_index] = [];
                // Number of stack items
                $l = count($stack);
                // Check if we're dealing with different levels
                while ($l > 0 && $stack[$l - 1][$config['level']] >= $item[$config['level']]) {
                    array_pop($stack);
                    --$l;
                }
                // Stack is empty (we are inspecting the root)
                if (0 == $l) {
                    // Assigning the root child
                    $i = count($nested_tree);
                    $nested_tree[$i] = $item;
                    $stack[] =& $nested_tree[$i];
                } else {
                    // Add child to parent
                    $i = count($stack[$l - 1][$this->children_index]);
                    $stack[$l - 1][$this->children_index][$i] = $item;
                    $stack[] =& $stack[$l - 1][$this->children_index][$i];
                }
            }
        }
        return $nested_tree;
    }
    public function set_children_index($children_index): void
    {
        $this->children_index = $children_index;
    }
    public function get_children_index()
    {
        return $this->children_index;
    }
}