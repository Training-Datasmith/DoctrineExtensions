<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sortable;

use Doctrine\Common\Comparable;
use Doctrine\Common\Event_Args;
use Doctrine\Deprecations\Deprecation;
use Doctrine\Persistence\Event\Lifecycle_Event_Args;
use Doctrine\Persistence\Event\Load_Class_Metadata_Event_Args;
use Doctrine\Persistence\Event\Manager_Event_Args;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Mapping\Mapped_Event_Subscriber;
use Gedmo\Sortable\Mapping\Event\Sortable_Adapter;
use Gedmo\Tool\Class_Utils;
use Proxy_Manager\Proxy\Ghost_Object_Interface;
/**
 * The SortableListener maintains a sort index on your entities
 * to enable arbitrary sorting.
 *
 * This behavior can impact the performance of your application
 * since it does some additional calculations on persisted objects.
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @phpstan-type SortableConfiguration = array{
 *   groups?: string[],
 *   position?: string,
 *   useObjectClass?: class-string,
 * }
 * @phpstan-type SortableRelocation = array{
 *   name?: class-string,
 *   groups?: mixed[],
 *   deltas?: array<array{
 *     delta: int,
 *     exclude: int[],
 *     start: int,
 *     stop: int,
 *   }>,
 * }
 *
 * @phpstan-extends MappedEventSubscriber<SortableConfiguration, SortableAdapter>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Sortable_Listener extends Mapped_Event_Subscriber
{
    /**
     * @var array<string, array<string, mixed>>
     *
     * @phpstan-var array<string, SortableRelocation>
     */
    private array $relocations = [];
    private bool $persistence_needed = false;
    /** @var array<string, int> */
    private array $max_positions = [];
    /**
     * Specifies the list of events to listen
     *
     * @return string[]
     */
    public function get_subscribed_events(): array
    {
        return ['onFlush', 'loadClassMetadata', 'prePersist', 'postPersist', 'preUpdate', 'postRemove', 'postFlush'];
    }
    /**
     * Maps additional metadata
     *
     * @param LoadClassMetadataEventArgs $args
     *
     * @phpstan-param LoadClassMetadataEventArgs<ClassMetadata<object>, ObjectManager> $args
     */
    public function load_class_metadata(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $this->load_metadata_for_object_class($ea->get_object_manager(), $args->get_class_metadata());
    }
    /**
     * Collect position updates on objects being updated during flush
     * if they require changing.
     *
     * Persisting of positions is done later during prePersist, preUpdate and postRemove
     * events, otherwise the queries won't be executed within the transaction.
     *
     * The synchronization of the objects in memory is done in postFlush. This
     * ensures that the positions have been successfully persisted to database.
     *
     * @param ManagerEventArgs $args
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $args
     */
    public function on_flush(Event_Args $args): void
    {
        $this->persistence_needed = true;
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $uow = $om->get_unit_of_work();
        // process all objects being deleted
        foreach ($ea->get_scheduled_object_deletions($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            if ($config = $this->get_configuration($om, $meta->get_name())) {
                $this->process_deletion($ea, $config, $meta, $object);
            }
        }
        $update_values = [];
        // process all objects being updated
        foreach ($ea->get_scheduled_object_updates($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            if ($config = $this->get_configuration($om, $meta->get_name())) {
                $position = $meta->get_field_value($object, $config['position']);
                $update_values[$position] = [$ea, $config, $meta, $object];
            }
        }
        krsort($update_values);
        foreach ($update_values as [$ea, $config, $meta, $object]) {
            $this->process_update($ea, $config, $meta, $object);
        }
        // process all objects being inserted
        foreach ($ea->get_scheduled_object_insertions($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            if ($config = $this->get_configuration($om, $meta->get_name())) {
                $this->process_insert($ea, $config, $meta, $object);
            }
        }
    }
    /**
     * Update maxPositions as needed
     *
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function pre_persist(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        if ($config = $this->get_configuration($om, $meta->get_name())) {
            // Get groups
            $groups = $this->get_groups($meta, $config, $object);
            // Get hash
            $hash = $this->get_hash($groups, $config);
            // Get max position
            if (!isset($this->max_positions[$hash])) {
                $this->max_positions[$hash] = $this->get_max_position($ea, $meta, $config, $object);
            }
        }
    }
    /**
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function post_persist(Event_Args $args): void
    {
        // persist position updates here, so that the update queries
        // are executed within transaction
        $this->persist_relocations($this->get_event_adapter($args));
    }
    /**
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function pre_update(Event_Args $args): void
    {
        // persist position updates here, so that the update queries
        // are executed within transaction
        $this->persist_relocations($this->get_event_adapter($args));
    }
    /**
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function post_remove(Event_Args $args): void
    {
        // persist position updates here, so that the update queries
        // are executed within transaction
        $this->persist_relocations($this->get_event_adapter($args));
    }
    /**
     * Sync objects in memory
     *
     * @param ManagerEventArgs $args
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $args
     */
    public function post_flush(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $em = $ea->get_object_manager();
        $updated_objects = [];
        foreach ($this->relocations as $hash => $relocation) {
            $config = $this->get_configuration($em, $relocation['name']);
            foreach ($relocation['deltas'] as $delta) {
                if ($delta['start'] > $this->max_positions[$hash]) {
                    continue;
                }
                if (0 == $delta['delta']) {
                    continue;
                }
                $meta = $em->get_class_metadata($relocation['name']);
                // now walk through the unit of work in memory objects and sync those
                $uow = $em->get_unit_of_work();
                foreach ($uow->get_identity_map() as $class_name => $objects) {
                    // for inheritance mapped classes, only root is always in the identity map
                    if ($class_name !== $ea->get_root_object_class($meta)) {
                        continue;
                    }
                    if (!$this->get_configuration($em, $class_name)) {
                        continue;
                    }
                    foreach ($objects as $object) {
                        if ($object instanceof Ghost_Object_Interface && !$object->is_proxy_initialized()) {
                            continue;
                        }
                        $change_set = $ea->get_object_change_set($uow, $object);
                        // if the entity's position is already changed, stop now
                        if (array_key_exists($config['position'], $change_set)) {
                            continue;
                        }
                        // if the entity's group has changed, we stop now
                        $groups = $this->get_groups($meta, $config, $object);
                        foreach (array_keys($groups) as $group) {
                            if (array_key_exists($group, $change_set)) {
                                continue 2;
                            }
                        }
                        $oid = spl_object_id($object);
                        $pos = $meta->get_field_value($object, $config['position']);
                        $matches = $pos >= $delta['start'];
                        $matches = $matches && ($delta['stop'] <= 0 || $pos < $delta['stop']);
                        $value = reset($relocation['groups']);
                        while ($matches && $group = key($relocation['groups'])) {
                            $gr = $meta->get_field_value($object, $group);
                            if (null === $value) {
                                $matches = null === $gr;
                            } elseif (is_object($gr) && is_object($value) && $gr !== $value) {
                                // Special case for equal objects but different instances.
                                // If the object implements Comparable interface we can use its compareTo method
                                // Otherwise we fallback to normal object comparison
                                if ($gr instanceof Comparable) {
                                    $matches = $gr->compare_to($value);
                                    // @todo: Remove "is_int" check and only support integer as the interface expects.
                                    if (is_int($matches)) {
                                        $matches = 0 === $matches;
                                    } else {
                                        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2542', 'Support for "%s" as return type from "%s::compareTo()" is deprecated since' . ' gedmo/doctrine-extensions 3.11 and will be removed in version 4.0. Return "integer" instead.', gettype($matches), Comparable::class);
                                    }
                                } else {
                                    $matches = $gr == $value;
                                }
                            } else {
                                $matches = $gr === $value;
                            }
                            $value = next($relocation['groups']);
                        }
                        if ($matches) {
                            // We cannot use `$this->setFieldValue()` here, because it will create a change set, that will
                            // prevent from other relocations being executed on this object.
                            // We just update the object value and will create the change set later.
                            if (!isset($updated_objects[$oid])) {
                                $updated_objects[$oid] = ['object' => $object, 'field' => $config['position'], 'oldValue' => $pos];
                            }
                            $updated_objects[$oid]['newValue'] = $pos + $delta['delta'];
                            $meta->set_field_value($object, $config['position'], $updated_objects[$oid]['newValue']);
                        }
                    }
                }
            }
            foreach ($updated_objects as $update_data) {
                $this->set_field_value($ea, $update_data['object'], $update_data['field'], $update_data['oldValue'], $update_data['newValue']);
            }
            // Clear relocations
            // unset only if relocations has been processed
            unset($this->relocations[$hash], $this->max_positions[$hash]);
        }
    }
    /**
     * Computes node positions and updates the sort field in memory and in the db
     *
     * @param array<string, mixed>  $config
     * @param ClassMetadata<object> $meta
     * @param object                $object
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return void
     */
    protected function process_insert(Sortable_Adapter $ea, array $config, $meta, $object)
    {
        $old = $meta->get_field_value($object, $config['position']);
        $new_position = $meta->get_field_value($object, $config['position']);
        if (null === $new_position) {
            $new_position = -1;
        }
        // Get groups
        $groups = $this->get_groups($meta, $config, $object);
        // Get hash
        $hash = $this->get_hash($groups, $config);
        // Get max position
        if (!isset($this->max_positions[$hash])) {
            $this->max_positions[$hash] = $this->get_max_position($ea, $meta, $config, $object);
        }
        // Compute position if it is negative
        if ($new_position < 0) {
            $new_position += $this->max_positions[$hash] + 2;
            // position == -1 => append at end of list
            if ($new_position < 0) {
                $new_position = 0;
            }
        }
        // Set position to max position if it is too big
        $new_position = min([$this->max_positions[$hash] + 1, $new_position]);
        // Compute relocations
        // New inserted entities should not be relocated by position update, so we exclude it.
        // Otherwise they could be relocated unintentionally.
        $relocation = [$hash, $config['useObjectClass'], $groups, $new_position, -1, +1, [$object]];
        // Apply existing relocations
        $apply_delta = 0;
        if (isset($this->relocations[$hash])) {
            foreach ($this->relocations[$hash]['deltas'] as $delta) {
                if ($delta['start'] <= $new_position && ($delta['stop'] > $new_position || $delta['stop'] < 0)) {
                    $apply_delta += $delta['delta'];
                }
            }
        }
        $new_position += $apply_delta;
        // Add relocations
        call_user_func_array([$this, 'addRelocation'], $relocation);
        // Set new position
        if ($old < 0 || null === $old) {
            $this->set_field_value($ea, $object, $config['position'], $old, $new_position);
        }
    }
    /**
     * Computes node positions and updates the sort field in memory and in the db
     *
     * @param array<string, mixed>  $config
     * @param ClassMetadata<object> $meta
     * @param object                $object
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return void
     */
    protected function process_update(Sortable_Adapter $ea, array $config, $meta, $object)
    {
        $em = $ea->get_object_manager();
        $uow = $em->get_unit_of_work();
        $changed = false;
        $group_has_changed = false;
        $change_set = $ea->get_object_change_set($uow, $object);
        // Get groups
        $groups = $this->get_groups($meta, $config, $object);
        // handle old groups
        $old_groups = $groups;
        foreach (array_keys($groups) as $group) {
            if (array_key_exists($group, $change_set)) {
                $changed = true;
                $old_groups[$group] = $change_set[$group][0];
            }
        }
        $old_position = 0;
        $new_position = 0;
        if ($changed) {
            $old_hash = $this->get_hash($old_groups, $config);
            $this->max_positions[$old_hash] = $this->get_max_position($ea, $meta, $config, $object, $old_groups);
            if (array_key_exists($config['position'], $change_set)) {
                $old_position = $change_set[$config['position']][0];
            } else {
                $old_position = $meta->get_field_value($object, $config['position']);
            }
            $this->add_relocation($old_hash, $config['useObjectClass'], $old_groups, $old_position + 1, $this->max_positions[$old_hash] + 1, -1);
            $group_has_changed = true;
        }
        // Get hash
        $hash = $this->get_hash($groups, $config);
        // Get max position
        if (!isset($this->max_positions[$hash])) {
            $this->max_positions[$hash] = $this->get_max_position($ea, $meta, $config, $object);
        }
        if (array_key_exists($config['position'], $change_set)) {
            if ($changed && -1 === $this->max_positions[$hash]) {
                // position has changed
                // the group of element has changed
                // and the target group has no children before
                $old_position = -1;
                $new_position = -1;
            } else {
                // position was manually updated
                $old_position = $change_set[$config['position']][0];
                $new_position = $change_set[$config['position']][1];
                $changed = $changed || $old_position != $new_position;
            }
        } elseif ($changed) {
            $new_position = $old_position;
        }
        if ($group_has_changed) {
            $old_position = -1;
        }
        if (!$changed) {
            return;
        }
        // Compute position if it is negative
        if ($new_position < 0) {
            if (-1 === $old_position) {
                $new_position += $this->max_positions[$hash] + 2;
                // position == -1 => append at end of list
            } else {
                $new_position += $this->max_positions[$hash] + 1;
                // position == -1 => append at end of list
            }
            if ($new_position < 0) {
                $new_position = 0;
            }
        } elseif ($new_position > $this->max_positions[$hash]) {
            if ($group_has_changed) {
                $new_position = $this->max_positions[$hash] + 1;
            } else {
                $new_position = $this->max_positions[$hash];
            }
        } else {
            $new_position = min([$this->max_positions[$hash], $new_position]);
        }
        // Compute relocations
        /*
        CASE 1: shift backwards
        |----0----|----1----|----2----|----3----|----4----|
        |--node1--|--node2--|--node3--|--node4--|--node5--|
        Update node4: setPosition(1)
        --> Update position + 1 where position in [1,3)
        |--node1--|--node4--|--node2--|--node3--|--node5--|
        CASE 2: shift forward
        |----0----|----1----|----2----|----3----|----4----|
        |--node1--|--node2--|--node3--|--node4--|--node5--|
        Update node2: setPosition(3)
        --> Update position - 1 where position in (1,3]
        |--node1--|--node3--|--node4--|--node2--|--node5--|
        */
        $relocation = null;
        if (-1 === $old_position) {
            // special case when group changes
            $relocation = [$hash, $config['useObjectClass'], $groups, $new_position, -1, +1];
        } elseif ($new_position < $old_position) {
            $relocation = [$hash, $config['useObjectClass'], $groups, $new_position, $old_position, +1];
        } elseif ($new_position > $old_position) {
            $relocation = [$hash, $config['useObjectClass'], $groups, $old_position + 1, $new_position + 1, -1];
        }
        if ($relocation) {
            // Add relocation
            call_user_func_array([$this, 'addRelocation'], $relocation);
        }
        // Set new position
        $this->set_field_value($ea, $object, $config['position'], $old_position, $new_position);
    }
    /**
     * Computes node positions and updates the sort field in memory and in the db
     *
     * @param array<string, mixed>  $config
     * @param ClassMetadata<object> $meta
     * @param object                $object
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return void
     */
    protected function process_deletion(Sortable_Adapter $ea, array $config, $meta, $object)
    {
        $position = $meta->get_field_value($object, $config['position']);
        // Get groups
        $groups = $this->get_groups($meta, $config, $object);
        // Get hash
        $hash = $this->get_hash($groups, $config);
        // Get max position
        if (!isset($this->max_positions[$hash])) {
            $this->max_positions[$hash] = $this->get_max_position($ea, $meta, $config, $object);
        }
        // Add relocation
        $this->add_relocation($hash, $config['useObjectClass'], $groups, $position, -1, -1);
    }
    /**
     * Persists relocations to database.
     *
     * @return void
     */
    protected function persist_relocations(Sortable_Adapter $ea)
    {
        if (!$this->persistence_needed) {
            return;
        }
        $em = $ea->get_object_manager();
        foreach ($this->relocations as $hash => $relocation) {
            $config = $this->get_configuration($em, $relocation['name']);
            foreach ($relocation['deltas'] as $delta) {
                if ($delta['start'] > $this->max_positions[$hash]) {
                    continue;
                }
                if (0 == $delta['delta']) {
                    continue;
                }
                $ea->update_positions($relocation, $delta, $config);
            }
        }
        $this->persistence_needed = false;
    }
    /**
     * @param array<string, mixed> $groups
     * @param array<string, mixed> $config
     *
     * @phpstan-param SortableConfiguration $config
     */
    protected function get_hash($groups, array $config): string
    {
        $data = $config['useObjectClass'];
        foreach ($groups as $group => $val) {
            if ($val instanceof \DateTime) {
                $val = $val->format('c');
            } elseif (is_object($val)) {
                $val = spl_object_id($val);
            }
            $data .= $group . $val;
        }
        return md5($data);
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     * @param object                $object
     * @param array<string, mixed>  $groups
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return int
     */
    protected function get_max_position(Sortable_Adapter $ea, $meta, $config, $object, array $groups = [])
    {
        $em = $ea->get_object_manager();
        $uow = $em->get_unit_of_work();
        $max_pos = null;
        // Get groups
        if ([] === $groups) {
            $groups = $this->get_groups($meta, $config, $object);
        }
        // Get hash
        $hash = $this->get_hash($groups, $config);
        // Check for cached max position
        if (isset($this->max_positions[$hash])) {
            return $this->max_positions[$hash];
        }
        // Check for groups that are associations. If the value is an object and is
        // scheduled for insert, it has no identifier yet and is obviously new
        // see issue #226
        foreach ($groups as $val) {
            if (is_object($val) && ($uow->is_scheduled_for_insert($val) || !$em->get_metadata_factory()->is_transient(Class_Utils::get_class($val)) && $uow::STATE_MANAGED !== $ea->get_object_state($uow, $val))) {
                return -1;
            }
        }
        $max_pos = $ea->get_max_position($config, $meta, $groups);
        if (null === $max_pos) {
            $max_pos = -1;
        }
        return (int) $max_pos;
    }
    /**
     * Add a relocation rule
     *
     * @param string                $hash    The hash of the sorting group
     * @param string                $class   The object class
     * @param array<string, object> $groups  The sorting groups
     * @param int                   $start   Inclusive index to start relocation from
     * @param int                   $stop    Exclusive index to stop relocation at
     * @param int                   $delta   The delta to add to relocated nodes
     * @param array<int, object>    $exclude Objects to be excluded from relocation
     *
     * @phpstan-param class-string $class
     *
     * @return void
     */
    protected function add_relocation($hash, $class, $groups, $start, $stop, $delta, array $exclude = [])
    {
        if (!array_key_exists($hash, $this->relocations)) {
            $this->relocations[$hash] = ['name' => $class, 'groups' => $groups, 'deltas' => []];
        }
        try {
            $new_delta = ['start' => $start, 'stop' => $stop, 'delta' => $delta, 'exclude' => $exclude];
            array_walk($this->relocations[$hash]['deltas'], static function (array &$val, $idx, array $needle): void {
                if ($val['start'] == $needle['start'] && $val['stop'] == $needle['stop']) {
                    $val['delta'] += $needle['delta'];
                    $val['exclude'] = array_merge($val['exclude'], $needle['exclude']);
                    throw new \Exception('Found delta. No need to add it again.');
                }
                // For every deletion relocation add newly created object to the list of excludes
                // otherwise position update queries will run for created objects as well.
                if (-1 == $val['delta'] && 1 == $needle['delta']) {
                    $val['exclude'] = array_merge($val['exclude'], $needle['exclude']);
                }
            }, $new_delta);
            $this->relocations[$hash]['deltas'][] = $new_delta;
        } catch (\Exception $e) {
        }
    }
    /**
     * @param ClassMetadata<object>                $meta
     * @param array<string, array<string, string>> $config
     * @param object                               $object
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return array<string, mixed>
     */
    protected function get_groups($meta, $config, $object): array
    {
        $groups = [];
        if (isset($config['groups'])) {
            foreach ($config['groups'] as $group) {
                $groups[$group] = $meta->get_field_value($object, $group);
            }
        }
        return $groups;
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
}