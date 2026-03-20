<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Strategy\ORM;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\Persistence\Proxy;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Mapping\Event\Adapter_Interface;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
use Gedmo\Tree\Node;
use Gedmo\Tree\Strategy;
use Gedmo\Tree\Tree_Listener;
/**
 * This strategy makes the tree act like a nested set.
 *
 * This behavior can impact the performance of your application
 * since nested set trees are slow on inserts and updates.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Nested implements Strategy
{
    /**
     * Previous sibling position
     */
    public const PREV_SIBLING = 'PrevSibling';
    /**
     * Next sibling position
     */
    public const NEXT_SIBLING = 'NextSibling';
    /**
     * First child position
     */
    public const FIRST_CHILD = 'FirstChild';
    /**
     * Last child position
     */
    public const LAST_CHILD = 'LastChild';
    public const ALLOWED_NODE_POSITIONS = [self::PREV_SIBLING, self::NEXT_SIBLING, self::FIRST_CHILD, self::LAST_CHILD];
    /**
     * TreeListener
     */
    protected \Gedmo\Tree\Tree_Listener $listener;
    /**
     * The max number of "right" field of the
     * tree in case few root nodes will be persisted
     * on one flush for node classes
     *
     * @var array<string, int>
     */
    private array $tree_edges = [];
    /**
     * Stores a list of node position strategies
     * for each node by object id
     *
     * @var array<int, string>
     *
     * @phpstan-var array<int, value-of<self::ALLOWED_NODE_POSITIONS>>
     */
    private array $node_positions = [];
    /**
     * Stores a list of delayed nodes for correct order of updates
     *
     * @var array<int, array<int, array<string, Node|object|string>>>
     *
     * @phpstan-var array<int, array<int, array{node: Node|object, position: value-of<self::ALLOWED_NODE_POSITIONS>}>>
     */
    private array $delayed_nodes = [];
    public function __construct(Tree_Listener $listener)
    {
        $this->listener = $listener;
    }
    public function get_name(): string
    {
        return Strategy::NESTED;
    }
    /**
     * Set node position strategy
     *
     * @param string $position
     *
     */
    public function set_node_position(int $oid, $position): void
    {
        if (!in_array($position, self::ALLOWED_NODE_POSITIONS, true)) {
            throw new InvalidArgumentException("Position: {$position} is not valid in nested set tree");
        }
        $this->node_positions[$oid] = $position;
    }
    public function process_scheduled_insertion($em, $node, Adapter_Interface $ea): void
    {
        /** @var ClassMetadata<object> $meta */
        $meta = $em->get_class_metadata(get_class($node));
        $config = $this->listener->get_configuration($em, $meta->get_name());
        $meta->set_field_value($node, $config['left'], 0);
        $meta->set_field_value($node, $config['right'], 0);
        if (isset($config['level'])) {
            $meta->set_field_value($node, $config['level'], 0);
        }
        if (isset($config['root']) && !$meta->has_association($config['root']) && !isset($config['rootIdentifierMethod'])) {
            $meta->set_field_value($node, $config['root'], 0);
        } elseif (isset($config['rootIdentifierMethod']) && null === $meta->get_field_value($node, $config['root'])) {
            $meta->set_field_value($node, $config['root'], 0);
        }
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
        if (isset($config['root'], $change_set[$config['root']])) {
            throw new UnexpectedValueException('Root cannot be changed manually, change parent instead');
        }
        $oid = spl_object_id($node);
        if (isset($change_set[$config['left']], $this->node_positions[$oid])) {
            $wrapped = Abstract_Wrapper::wrap($node, $em);
            $parent = $wrapped->get_property_value($config['parent']);
            // revert simulated changeset
            $ea->clear_object_change_set($uow, $node);
            $wrapped->set_property_value($config['left'], $change_set[$config['left']][0]);
            $uow->set_original_entity_property($oid, $config['left'], $change_set[$config['left']][0]);
            // set back all other changes
            foreach ($change_set as $field => $set) {
                if ($field !== $config['left']) {
                    if (is_array($set) && array_key_exists(0, $set) && array_key_exists(1, $set)) {
                        $uow->set_original_entity_property($oid, $field, $set[0]);
                        $wrapped->set_property_value($field, $set[1]);
                    } else {
                        $uow->set_original_entity_property($oid, $field, $set);
                        $wrapped->set_property_value($field, $set);
                    }
                }
            }
            $uow->recompute_single_entity_change_set($meta, $node);
            $this->update_node($em, $node, $parent);
        } elseif (isset($change_set[$config['parent']])) {
            $this->update_node($em, $node, $change_set[$config['parent']][1]);
        }
    }
    /**
     * @param EntityManagerInterface $em
     */
    public function process_post_persist($em, $node, Adapter_Interface $ea): void
    {
        $meta = $em->get_class_metadata(get_class($node));
        $config = $this->listener->get_configuration($em, $meta->get_name());
        $parent = $meta->get_field_value($node, $config['parent']);
        $this->update_node($em, $node, $parent, self::LAST_CHILD);
    }
    /**
     * @param EntityManagerInterface $em
     */
    public function process_scheduled_delete($em, $node): void
    {
        $meta = $em->get_class_metadata(get_class($node));
        $config = $this->listener->get_configuration($em, $meta->get_name());
        $uow = $em->get_unit_of_work();
        $wrapped = Abstract_Wrapper::wrap($node, $em);
        $left_value = $wrapped->get_property_value($config['left']);
        $right_value = $wrapped->get_property_value($config['right']);
        if (!$left_value || !$right_value) {
            return;
        }
        $root_id = isset($config['root']) ? $wrapped->get_property_value($config['root']) : null;
        $diff = $right_value - $left_value + 1;
        if ($diff > 2) {
            $qb = $em->create_query_builder();
            $qb->select('node')->from($config['useObjectClass'], 'node')->where($qb->expr()->between('node.' . $config['left'], '?1', '?2'))->set_parameter(1, $left_value)->set_parameter(2, $right_value);
            if (isset($config['root'])) {
                $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
                $qb->set_parameter('rid', $root_id);
            }
            $q = $qb->get_query();
            // get nodes for deletion
            foreach ($q->to_iterable() as $removal_node) {
                $uow->schedule_for_delete($removal_node);
            }
        }
        $this->shift_rl($em, $config['useObjectClass'], $right_value + 1, -$diff, $root_id);
    }
    public function on_flush_end($em, Adapter_Interface $ea): void
    {
        // reset values
        $this->tree_edges = [];
    }
    public function process_pre_remove($em, $node)
    {
    }
    public function process_pre_persist($em, $node)
    {
    }
    public function process_pre_update($em, $node)
    {
    }
    public function process_metadata_load($em, $meta)
    {
    }
    public function process_post_update($em, $entity, Adapter_Interface $ea)
    {
    }
    public function process_post_remove($em, $entity, Adapter_Interface $ea)
    {
    }
    /**
     * Update the $node with a different $parent destination
     *
     * @param Node|object $node     target node
     * @param Node|object $parent   destination node
     * @param string      $position
     *
     * @phpstan-param value-of<self::ALLOWED_NODE_POSITIONS> $position
     *
     * @throws UnexpectedValueException
     */
    public function update_node(Entity_Manager_Interface $em, $node, $parent, $position = self::FIRST_CHILD): void
    {
        $wrapped = Abstract_Wrapper::wrap($node, $em);
        /** @var ClassMetadata<object> $meta */
        $meta = $wrapped->get_metadata();
        $config = $this->listener->get_configuration($em, $meta->get_name());
        $root = isset($config['root']) ? $wrapped->get_property_value($config['root']) : null;
        $identifier_field = $meta->get_single_identifier_field_name();
        $node_id = $wrapped->get_identifier();
        $left = $wrapped->get_property_value($config['left']);
        $right = $wrapped->get_property_value($config['right']);
        $is_new_node = empty($left) && empty($right);
        if ($is_new_node) {
            $left = 1;
            $right = 2;
        }
        $oid = spl_object_id($node);
        if (isset($this->node_positions[$oid])) {
            $position = $this->node_positions[$oid];
        }
        $level = $config['level_base'] ?? 0;
        $tree_size = $right - $left + 1;
        $new_root = null;
        // @todo: In the next major release, remove all the conditions and use only the following assignment for `$sibling`.
        // $node->getSibling();
        if (method_exists($node, 'getSibling')) {
            $sibling = $node->get_sibling();
        } elseif (property_exists($node, 'sibling')) {
            $sibling = $node->sibling;
        } else {
            $sibling = null;
        }
        if ($parent) {
            // || (!$parent && isset($config['rootIdentifierMethod']))
            $wrapped_parent = Abstract_Wrapper::wrap($parent, $em);
            $parent_root = isset($config['root']) ? $wrapped_parent->get_property_value($config['root']) : null;
            $parent_oid = spl_object_id($parent);
            $parent_left = $wrapped_parent->get_property_value($config['left']);
            $parent_right = $wrapped_parent->get_property_value($config['right']);
            if (empty($parent_left) && empty($parent_right)) {
                // parent node is a new node, but wasn't processed yet (due to Doctrine commit order calculator redordering)
                // We delay processing of node to the moment parent node will be processed
                if (!isset($this->delayed_nodes[$parent_oid])) {
                    $this->delayed_nodes[$parent_oid] = [];
                }
                $this->delayed_nodes[$parent_oid][] = ['node' => $node, 'position' => $position];
                return;
            }
            if (!$is_new_node && $root === $parent_root && $parent_left >= $left && $parent_right <= $right) {
                throw new UnexpectedValueException("Cannot set child as parent to node: {$node_id}");
            }
            if (isset($config['level'])) {
                $level = $wrapped_parent->get_property_value($config['level']);
            }
            switch ($position) {
                case self::PREV_SIBLING:
                    if (null !== $sibling) {
                        $wrapped_sibling = Abstract_Wrapper::wrap($sibling, $em);
                        $start = $wrapped_sibling->get_property_value($config['left']);
                        ++$level;
                    } else {
                        $new_parent = $wrapped_parent->get_property_value($config['parent']);
                        if (null === $new_parent && (isset($config['root']) && $config['root'] == $config['parent'] || $is_new_node)) {
                            throw new UnexpectedValueException('Cannot persist sibling for a root node, tree operation is not possible');
                        }
                        if (null === $new_parent && (isset($config['root']) || $is_new_node)) {
                            // root is a different column from parent (pointing to another table?), do nothing
                        } else {
                            $wrapped->set_property_value($config['parent'], $new_parent);
                        }
                        $em->get_unit_of_work()->recompute_single_entity_change_set($meta, $node);
                        $start = $parent_left;
                    }
                    break;
                case self::NEXT_SIBLING:
                    if (null !== $sibling) {
                        $wrapped_sibling = Abstract_Wrapper::wrap($sibling, $em);
                        $start = $wrapped_sibling->get_property_value($config['right']) + 1;
                        ++$level;
                    } else {
                        $new_parent = $wrapped_parent->get_property_value($config['parent']);
                        if (null === $new_parent && (isset($config['root']) && $config['root'] == $config['parent'] || $is_new_node)) {
                            throw new UnexpectedValueException('Cannot persist sibling for a root node, tree operation is not possible');
                        }
                        if (null === $new_parent && (isset($config['root']) || $is_new_node)) {
                            // root is a different column from parent (pointing to another table?), do nothing
                        } else {
                            $wrapped->set_property_value($config['parent'], $new_parent);
                        }
                        $em->get_unit_of_work()->recompute_single_entity_change_set($meta, $node);
                        $start = $parent_right + 1;
                    }
                    break;
                case self::LAST_CHILD:
                    $start = $parent_right;
                    ++$level;
                    break;
                case self::FIRST_CHILD:
                default:
                    $start = $parent_left + 1;
                    ++$level;
                    break;
            }
            $this->shift_rl($em, $config['useObjectClass'], $start, $tree_size, $parent_root);
            if (!$is_new_node && $root === $parent_root && $left >= $start) {
                $left += $tree_size;
                $wrapped->set_property_value($config['left'], $left);
            }
            if (!$is_new_node && $root === $parent_root && $right >= $start) {
                $right += $tree_size;
                $wrapped->set_property_value($config['right'], $right);
            }
            $new_root = $parent_root;
        } elseif (!isset($config['root']) || $meta->is_single_valued_association($config['root']) && null !== $parent && $new_root = $meta->get_field_value($node, $config['root'])) {
            if (!isset($this->tree_edges[$meta->get_name()])) {
                $this->tree_edges[$meta->get_name()] = $this->max($em, $config['useObjectClass'], $new_root) + 1;
            }
            $level = 0;
            $parent_left = 0;
            $parent_right = $this->tree_edges[$meta->get_name()];
            $this->tree_edges[$meta->get_name()] += 2;
            switch ($position) {
                case self::PREV_SIBLING:
                    if (null !== $sibling) {
                        $wrapped_sibling = Abstract_Wrapper::wrap($sibling, $em);
                        $start = $wrapped_sibling->get_property_value($config['left']);
                    } else {
                        $wrapped->set_property_value($config['parent'], null);
                        $em->get_unit_of_work()->recompute_single_entity_change_set($meta, $node);
                        $start = $parent_left + 1;
                    }
                    break;
                case self::NEXT_SIBLING:
                    if (null !== $sibling) {
                        $wrapped_sibling = Abstract_Wrapper::wrap($sibling, $em);
                        $start = $wrapped_sibling->get_property_value($config['right']) + 1;
                    } else {
                        $wrapped->set_property_value($config['parent'], null);
                        $em->get_unit_of_work()->recompute_single_entity_change_set($meta, $node);
                        $start = $parent_right;
                    }
                    break;
                case self::LAST_CHILD:
                    $start = $parent_right;
                    break;
                case self::FIRST_CHILD:
                default:
                    $start = $parent_left + 1;
                    break;
            }
            $this->shift_rl($em, $config['useObjectClass'], $start, $tree_size);
            if (!$is_new_node && $left >= $start) {
                $left += $tree_size;
                $wrapped->set_property_value($config['left'], $left);
            }
            if (!$is_new_node && $right >= $start) {
                $right += $tree_size;
                $wrapped->set_property_value($config['right'], $right);
            }
        } else {
            $start = 1;
            if (isset($config['rootIdentifierMethod'])) {
                $method = $config['rootIdentifierMethod'];
                $new_root = $node->{$method}();
                $repo = $em->get_repository($config['useObjectClass']);
                $criteria = new Criteria();
                $criteria->and_where(Criteria::expr()->not_in($wrapped->get_metadata()->get_identifier()[0], [$wrapped->get_identifier()]));
                $criteria->and_where(Criteria::expr()->eq($config['root'], $node->{$method}()));
                $criteria->and_where(Criteria::expr()->is_null($config['parent']));
                $criteria->and_where(Criteria::expr()->eq($config['level'], 0));
                $criteria->order_by([$config['right'] => Criteria::ASC]);
                $roots = $repo->matching($criteria)->to_array();
                $last = array_pop($roots);
                $start = $last ? $meta->get_field_value($last, $config['right']) + 1 : 1;
            } elseif ($meta->is_single_valued_association($config['root'])) {
                $new_root = $node;
            } else {
                $new_root = $wrapped->get_identifier();
            }
        }
        $diff = $start - $left;
        if (!$is_new_node) {
            $level_diff = isset($config['level']) ? $level - $wrapped->get_property_value($config['level']) : null;
            $this->shift_range_rl($em, $config['useObjectClass'], $left, $right, $diff, $root, $new_root, $level_diff);
            $this->shift_rl($em, $config['useObjectClass'], $left, -$tree_size, $root);
        } else {
            $qb = $em->create_query_builder();
            $qb->update($config['useObjectClass'], 'node');
            if (isset($config['root'])) {
                $qb->set('node.' . $config['root'], ':rid');
                $qb->set_parameter('rid', $new_root);
                $wrapped->set_property_value($config['root'], $new_root);
                $em->get_unit_of_work()->set_original_entity_property($oid, $config['root'], $new_root);
            }
            if (isset($config['level'])) {
                $qb->set('node.' . $config['level'], $level);
                $wrapped->set_property_value($config['level'], $level);
                $em->get_unit_of_work()->set_original_entity_property($oid, $config['level'], $level);
            }
            if (isset($new_parent)) {
                $wrapped_new_parent = Abstract_Wrapper::wrap($new_parent, $em);
                $new_parent_id = $wrapped_new_parent->get_identifier();
                $qb->set('node.' . $config['parent'], ':pid');
                $qb->set_parameter('pid', $new_parent_id);
                $wrapped->set_property_value($config['parent'], $new_parent);
                $em->get_unit_of_work()->set_original_entity_property($oid, $config['parent'], $new_parent);
            }
            $qb->set('node.' . $config['left'], $left + $diff);
            $qb->set('node.' . $config['right'], $right + $diff);
            // node id cannot be null
            $qb->where($qb->expr()->eq('node.' . $identifier_field, ':id'));
            $qb->set_parameter('id', $node_id);
            $qb->get_query()->get_single_scalar_result();
            $wrapped->set_property_value($config['left'], $left + $diff);
            $wrapped->set_property_value($config['right'], $right + $diff);
            $em->get_unit_of_work()->set_original_entity_property($oid, $config['left'], $left + $diff);
            $em->get_unit_of_work()->set_original_entity_property($oid, $config['right'], $right + $diff);
        }
        if (isset($this->delayed_nodes[$oid])) {
            foreach ($this->delayed_nodes[$oid] as $node_data) {
                $this->update_node($em, $node_data['node'], $node, $node_data['position']);
            }
        }
    }
    /**
     * Get the edge of tree
     *
     * @param string $class
     * @param int    $rootId
     *
     * @phpstan-param class-string $class
     */
    public function max(Entity_Manager_Interface $em, $class, $root_id = 0): int
    {
        $meta = $em->get_class_metadata($class);
        $config = $this->listener->get_configuration($em, $meta->get_name());
        $qb = $em->create_query_builder();
        $qb->select($qb->expr()->max('node.' . $config['right']))->from($config['useObjectClass'], 'node');
        if (isset($config['root']) && $root_id) {
            $qb->where($qb->expr()->eq('node.' . $config['root'], ':rid'));
            $qb->set_parameter('rid', $root_id);
        }
        $query = $qb->get_query();
        $right = $query->get_single_scalar_result();
        return (int) $right;
    }
    /**
     * Shift tree left and right values by delta
     *
     * @param string     $class
     * @param int        $first
     * @param int        $delta
     * @param int|string $root
     *
     * @phpstan-param class-string $class
     */
    public function shift_rl(Entity_Manager_Interface $em, $class, $first, $delta, $root = null): void
    {
        $meta = $em->get_class_metadata($class);
        $config = $this->listener->get_configuration($em, $class);
        $sign = $delta >= 0 ? ' + ' : ' - ';
        $abs_delta = abs($delta);
        $qb = $em->create_query_builder();
        $qb->update($config['useObjectClass'], 'node')->set('node.' . $config['left'], "node.{$config['left']} {$sign} {$abs_delta}")->where($qb->expr()->gte('node.' . $config['left'], $first));
        if (isset($config['root'])) {
            $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
            $qb->set_parameter('rid', $root);
        }
        $qb->get_query()->get_single_scalar_result();
        $qb = $em->create_query_builder();
        $qb->update($config['useObjectClass'], 'node')->set('node.' . $config['right'], "node.{$config['right']} {$sign} {$abs_delta}")->where($qb->expr()->gte('node.' . $config['right'], $first));
        if (isset($config['root'])) {
            $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
            $qb->set_parameter('rid', $root);
        }
        $qb->get_query()->get_single_scalar_result();
        // update in memory nodes increases performance, saves some IO
        foreach ($em->get_unit_of_work()->get_identity_map() as $class_name => $nodes) {
            // for inheritance mapped classes, only root is always in the identity map
            if ($class_name !== $meta->root_entity_name) {
                continue;
            }
            foreach ($nodes as $node) {
                if ($node instanceof Proxy && !$node->__is_initialized()) {
                    continue;
                }
                assert(null !== $node);
                $node_meta = $em->get_class_metadata(get_class($node));
                /** @phpstan-ignore-next-line function.alreadyNarrowedType Property introduced in ORM 3.4 */
                if (property_exists($node_meta, 'propertyAccessors')) {
                    // ORM 3.4+
                    if (!array_key_exists($config['left'], $node_meta->get_property_accessors())) {
                        continue;
                    }
                } else if (!array_key_exists($config['left'], $node_meta->get_reflection_properties())) {
                    continue;
                }
                $oid = spl_object_id($node);
                $left = $meta->get_field_value($node, $config['left']);
                $current_root = isset($config['root']) ? $meta->get_field_value($node, $config['root']) : null;
                if ($current_root === $root && $left >= $first) {
                    $meta->set_field_value($node, $config['left'], $left + $delta);
                    $em->get_unit_of_work()->set_original_entity_property($oid, $config['left'], $left + $delta);
                }
                $right = $meta->get_field_value($node, $config['right']);
                if ($current_root === $root && $right >= $first) {
                    $meta->set_field_value($node, $config['right'], $right + $delta);
                    $em->get_unit_of_work()->set_original_entity_property($oid, $config['right'], $right + $delta);
                }
            }
        }
    }
    /**
     * Shift range of right and left values on tree
     * depending on tree level difference also
     *
     * @param string     $class
     * @param int        $first
     * @param int        $last
     * @param int        $delta
     * @param int|string $root
     * @param int|string $destRoot
     * @param int        $levelDelta
     *
     * @phpstan-param class-string $class
     */
    public function shift_range_rl(Entity_Manager_Interface $em, $class, $first, $last, $delta, $root = null, $dest_root = null, $level_delta = null): void
    {
        // @todo: Remove the following condition and assignment in the next major release and use 0 as default value for
        // the `$levelDelta` parameter.
        if (null === $level_delta && func_num_args() >= 8) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2495', 'Passing a type different than "int" as argument 8 to "%s()" is deprecated since gedmo/doctrine-extensions' . ' 3.9 and will throw a "%s" error in version 4.0.', __METHOD__, \TypeError::class);
        }
        $level_delta ??= 0;
        $meta = $em->get_class_metadata($class);
        $config = $this->listener->get_configuration($em, $class);
        $sign = $delta >= 0 ? ' + ' : ' - ';
        $abs_delta = abs($delta);
        $level_sign = $level_delta >= 0 ? ' + ' : ' - ';
        $abs_level_delta = abs($level_delta);
        $qb = $em->create_query_builder();
        $qb->update($config['useObjectClass'], 'node')->set('node.' . $config['left'], "node.{$config['left']} {$sign} {$abs_delta}")->set('node.' . $config['right'], "node.{$config['right']} {$sign} {$abs_delta}")->where($qb->expr()->gte('node.' . $config['left'], $first))->and_where($qb->expr()->lte('node.' . $config['right'], $last));
        if (isset($config['root'])) {
            $qb->set('node.' . $config['root'], ':drid');
            $qb->set_parameter('drid', $dest_root);
            $qb->and_where($qb->expr()->eq('node.' . $config['root'], ':rid'));
            $qb->set_parameter('rid', $root);
        }
        if (isset($config['level'])) {
            $qb->set('node.' . $config['level'], "node.{$config['level']} {$level_sign} {$abs_level_delta}");
        }
        $qb->get_query()->get_single_scalar_result();
        // update in memory nodes increases performance, saves some IO
        foreach ($em->get_unit_of_work()->get_identity_map() as $class_name => $nodes) {
            // for inheritance mapped classes, only root is always in the identity map
            if ($class_name !== $meta->root_entity_name) {
                continue;
            }
            foreach ($nodes as $node) {
                if ($node instanceof Proxy && !$node->__is_initialized()) {
                    continue;
                }
                assert(null !== $node);
                $node_meta = $em->get_class_metadata(get_class($node));
                /** @phpstan-ignore-next-line function.alreadyNarrowedType Property introduced in ORM 3.4 */
                if (property_exists($node_meta, 'propertyAccessors')) {
                    // ORM 3.4+
                    if (!array_key_exists($config['left'], $node_meta->get_property_accessors())) {
                        continue;
                    }
                } else if (!array_key_exists($config['left'], $node_meta->get_reflection_properties())) {
                    continue;
                }
                $left = $meta->get_field_value($node, $config['left']);
                $right = $meta->get_field_value($node, $config['right']);
                $current_root = isset($config['root']) ? $meta->get_field_value($node, $config['root']) : null;
                if ($current_root === $root && $left >= $first && $right <= $last) {
                    $oid = spl_object_id($node);
                    $uow = $em->get_unit_of_work();
                    $meta->set_field_value($node, $config['left'], $left + $delta);
                    $uow->set_original_entity_property($oid, $config['left'], $left + $delta);
                    $meta->set_field_value($node, $config['right'], $right + $delta);
                    $uow->set_original_entity_property($oid, $config['right'], $right + $delta);
                    if (isset($config['root'])) {
                        $meta->set_field_value($node, $config['root'], $dest_root);
                        $uow->set_original_entity_property($oid, $config['root'], $dest_root);
                    }
                    if (isset($config['level'])) {
                        $level = $meta->get_field_value($node, $config['level']);
                        $meta->set_field_value($node, $config['level'], $level + $level_delta);
                        $uow->set_original_entity_property($oid, $config['level'], $level + $level_delta);
                    }
                }
            }
        }
    }
}