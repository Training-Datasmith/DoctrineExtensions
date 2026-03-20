<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Hydrator\ORM;

use Doctrine\Common\Collections\Array_Collection;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Internal\Hydration\Object_Hydrator;
use Doctrine\ORM\Persistent_Collection;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Tool\ORM\Hydration\Entity_Manager_Retriever;
use Gedmo\Tool\ORM\Hydration\Hydrator_Compat;
use Gedmo\Tree\Tree_Listener;
/**
 * Automatically maps the parent and children properties of Tree nodes
 *
 * @author Ilija Tovilo <ilija.tovilo@me.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Tree_Object_Hydrator extends Object_Hydrator
{
    use Entity_Manager_Retriever;
    use Hydrator_Compat;
    private const NO_PARENT_ID = '__no-parent__';
    /**
     * @var array<string, mixed>
     */
    private $config = [];
    /**
     * @var string
     */
    private $id_field;
    /**
     * @var string
     */
    private $parent_field;
    /**
     * @var string
     */
    private $children_field;
    /**
     * @param object $object
     * @param string $property
     * @param mixed  $value
     */
    public function set_property_value($object, $property, $value): void
    {
        $meta = $this->get_entity_manager()->get_class_metadata(get_class($object));
        $meta->set_field_value($object, $property, $value);
    }
    /**
     * We hook into the `hydrateAllData` to map the children collection of the entity
     *
     * @return array<int, object>
     */
    protected function do_hydrate_all_data()
    {
        $data = parent::hydrate_all_data();
        if ([] === $data) {
            return $data;
        }
        $listener = $this->get_tree_listener($this->get_entity_manager());
        $entity_class = $this->get_entity_class_from_hydrated_data($data);
        $this->config = $listener->get_configuration($this->get_entity_manager(), $entity_class);
        $this->id_field = $this->get_id_field($entity_class);
        $this->parent_field = $this->get_parent_field();
        $this->children_field = $this->get_children_field($entity_class);
        $children_hashmap = $this->build_children_hashmap($data);
        $this->populate_children_array($data, $children_hashmap);
        // Only return root elements or elements who's parents haven't been fetched
        // The sub-nodes will be accessible via the `children` property
        return $this->get_root_nodes($data);
    }
    /**
     * Creates a hashmap to quickly find the children of a node
     *
     * ```
     * [parentId => [child1, child2, ...], ...]
     * ```
     *
     * @param array<int, object> $nodes
     *
     * @return array<int|string, array<int, object>>
     */
    protected function build_children_hashmap($nodes)
    {
        $r = [];
        foreach ($nodes as $node) {
            $parent_proxy = $this->get_property_value($node, $this->config['parent']);
            $parent_id = self::NO_PARENT_ID;
            if (null !== $parent_proxy) {
                $parent_id = $this->get_property_value($parent_proxy, $this->id_field);
            }
            $r[$parent_id][] = $node;
        }
        return $r;
    }
    /**
     * @param array<int, object>                    $nodes
     * @param array<int|string, array<int, object>> $childrenHashmap
     *
     * @return void
     */
    protected function populate_children_array($nodes, $children_hashmap)
    {
        foreach ($nodes as $node) {
            $node_id = $this->get_property_value($node, $this->id_field);
            $children_collection = $this->get_property_value($node, $this->children_field);
            if (null === $children_collection) {
                $children_collection = new Array_Collection();
                $this->set_property_value($node, $this->children_field, $children_collection);
            }
            // Initialize all the children collections in order to avoid "SELECT" queries.
            if ($children_collection instanceof Persistent_Collection && !$children_collection->is_initialized()) {
                $children_collection->set_initialized(true);
            }
            if (!isset($children_hashmap[$node_id])) {
                continue;
            }
            $children_collection->clear();
            foreach ($children_hashmap[$node_id] as $child) {
                $children_collection->add($child);
            }
        }
    }
    /**
     * @param array<int, object> $nodes
     *
     * @return array<int, object>
     */
    protected function get_root_nodes($nodes)
    {
        $id_hashmap = $this->build_id_hashmap($nodes);
        $root_nodes = [];
        foreach ($nodes as $node) {
            $parent_proxy = $this->get_property_value($node, $this->config['parent']);
            $parent_id = self::NO_PARENT_ID;
            if (null !== $parent_proxy) {
                $parent_id = $this->get_property_value($parent_proxy, $this->id_field);
            }
            if (self::NO_PARENT_ID === $parent_id || !array_key_exists($parent_id, $id_hashmap)) {
                $root_nodes[] = $node;
            }
        }
        return $root_nodes;
    }
    /**
     * Creates a hashmap of all nodes returned in the query
     *
     * ```
     * [node1.id => true, node2.id => true, ...]
     * ```
     *
     * @param array<int, object> $nodes
     *
     * @return array<mixed, true>
     */
    protected function build_id_hashmap(array $nodes)
    {
        $ids = [];
        foreach ($nodes as $node) {
            $id = $this->get_property_value($node, $this->id_field);
            $ids[$id] = true;
        }
        return $ids;
    }
    /**
     * @param string $entityClass
     *
     * @phpstan-param class-string $entityClass
     *
     * @return string
     */
    protected function get_id_field($entity_class)
    {
        $meta = $this->get_class_metadata($entity_class);
        return $meta->get_single_identifier_field_name();
    }
    /**
     * @return string
     */
    protected function get_parent_field()
    {
        if (!isset($this->config['parent'])) {
            throw new Invalid_Mapping_Exception('The `parent` property is required for the TreeHydrator to work');
        }
        return $this->config['parent'];
    }
    /**
     * @param string $entityClass
     *
     * @phpstan-param class-string $entityClass
     *
     * @return string
     */
    protected function get_children_field($entity_class)
    {
        $meta = $this->get_class_metadata($entity_class);
        foreach ($meta->get_reflection_properties() as $property) {
            // Skip properties that have no association
            if (!$meta->has_association($property->get_name())) {
                continue;
            }
            $association_mapping = $meta->get_association_mapping($property->get_name());
            // Make sure the association is mapped by the parent property
            if ($association_mapping['mappedBy'] !== $this->parent_field) {
                continue;
            }
            return $association_mapping['fieldName'];
        }
        throw new Invalid_Mapping_Exception('The children property could not found. It is identified through the `mappedBy` annotation to your parent property.');
    }
    /**
     * @return TreeListener
     */
    protected function get_tree_listener(Entity_Manager_Interface $em)
    {
        foreach ($em->get_event_manager()->get_all_listeners() as $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof Tree_Listener) {
                    return $listener;
                }
            }
        }
        throw new Invalid_Mapping_Exception('Tree listener was not found on your entity manager, it must be hooked into the event manager');
    }
    /**
     * @param array<int, object> $data
     *
     * @return string
     */
    protected function get_entity_class_from_hydrated_data($data)
    {
        $first_mapped_entity = array_values($data);
        $first_mapped_entity = $first_mapped_entity[0];
        return $this->get_entity_manager()->get_class_metadata(get_class($first_mapped_entity))->root_entity_name;
    }
    /**
     * @param object $object
     * @param string $property
     *
     * @return mixed
     */
    protected function get_property_value($object, $property)
    {
        $meta = $this->get_entity_manager()->get_class_metadata(get_class($object));
        return $meta->get_field_value($object, $property);
    }
}