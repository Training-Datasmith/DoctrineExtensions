<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree;

use Doctrine\Common\Event_Args;
use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\Persistence\Event\Lifecycle_Event_Args;
use Doctrine\Persistence\Event\Load_Class_Metadata_Event_Args;
use Doctrine\Persistence\Event\Manager_Event_Args;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Mapping\Mapped_Event_Subscriber;
use Gedmo\Tree\Mapping\Event\Tree_Adapter;
/**
 * The tree listener handles the synchronization of
 * tree nodes. Can implement different
 * strategies on handling the tree.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @phpstan-type TreeConfiguration = array{
 *   activate_locking?: bool,
 *   closure?: class-string,
 *   left?: string,
 *   level?: string,
 *   lock_time?: string,
 *   locking_timeout?: int,
 *   parent?: string,
 *   path?: string,
 *   path_source?: string,
 *   path_separator?: string,
 *   path_append_id?: ?bool,
 *   path_starts_with_separator?: bool,
 *   path_ends_with_separator?: bool,
 *   path_hash?: string,
 *   right?: string,
 *   root?: string,
 *   rootIdentifierMethod?: string,
 *   strategy?: string,
 *   useObjectClass?: class-string,
 *   level_base?: int,
 * }
 *
 * @phpstan-extends MappedEventSubscriber<TreeConfiguration, TreeAdapter>
 */
class Tree_Listener extends Mapped_Event_Subscriber
{
    /**
     * Tree processing strategies for object classes
     *
     * @var array<string, string>
     *
     * @phpstan-var array<class-string, string>
     */
    private array $strategies = [];
    /**
     * List of strategy instances
     *
     * @var array<string, Strategy>
     *
     * @phpstan-var array<value-of<self::strategies>, Strategy>
     */
    private array $strategy_instances = [];
    /**
     * List of used classes on flush
     *
     * @var array<string, null>
     *
     * @phpstan-var array<class-string, null>
     */
    private array $used_classes_on_flush = [];
    /**
     * Specifies the list of events to listen
     *
     * @return string[]
     */
    public function get_subscribed_events(): array
    {
        return ['prePersist', 'preRemove', 'preUpdate', 'onFlush', 'loadClassMetadata', 'postPersist', 'postUpdate', 'postRemove'];
    }
    /**
     * Get the used strategy for tree processing
     *
     * @param string $class
     *
     * @return Strategy
     */
    public function get_strategy(Object_Manager $om, $class)
    {
        if (!isset($this->strategies[$class])) {
            $config = $this->get_configuration($om, $class);
            if ([] === $config) {
                throw new UnexpectedValueException("Tree object class: {$class} must have tree metadata at this point");
            }
            $manager_name = 'UnsupportedManager';
            if ($om instanceof Entity_Manager_Interface) {
                $manager_name = 'ORM';
            } elseif ($om instanceof Document_Manager) {
                $manager_name = 'ODM\MongoDB';
            }
            if (!isset($this->strategy_instances[$config['strategy']])) {
                $strategy_class = $this->get_namespace() . '\Strategy\\' . $manager_name . '\\' . ucfirst($config['strategy']);
                if (!class_exists($strategy_class)) {
                    throw new InvalidArgumentException($manager_name . " TreeListener does not support tree type: {$config['strategy']}");
                }
                $this->strategy_instances[$config['strategy']] = new $strategy_class($this);
            }
            $this->strategies[$class] = $config['strategy'];
        }
        return $this->strategy_instances[$this->strategies[$class]];
    }
    /**
     * Looks for Tree objects being updated
     * for further processing
     *
     * @param ManagerEventArgs $args
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $args
     */
    public function on_flush(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $uow = $om->get_unit_of_work();
        // check all scheduled updates for TreeNodes
        foreach ($ea->get_scheduled_object_insertions($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            if ($this->get_configuration($om, $meta->get_name())) {
                $this->used_classes_on_flush[$meta->get_name()] = null;
                $this->get_strategy($om, $meta->get_name())->process_scheduled_insertion($om, $object, $ea);
                $ea->recompute_single_object_change_set($uow, $meta, $object);
            }
        }
        foreach ($ea->get_scheduled_object_updates($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            if ($this->get_configuration($om, $meta->get_name())) {
                $this->used_classes_on_flush[$meta->get_name()] = null;
                $this->get_strategy($om, $meta->get_name())->process_scheduled_update($om, $object, $ea);
            }
        }
        foreach ($ea->get_scheduled_object_deletions($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            if ($this->get_configuration($om, $meta->get_name())) {
                $this->used_classes_on_flush[$meta->get_name()] = null;
                $this->get_strategy($om, $meta->get_name())->process_scheduled_delete($om, $object);
            }
        }
        foreach ($this->get_strategies_used_for_objects($this->used_classes_on_flush) as $strategy) {
            $strategy->on_flush_end($om, $ea);
        }
    }
    /**
     * Updates tree on Node removal
     *
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function pre_remove(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        if ($this->get_configuration($om, $meta->get_name())) {
            $this->get_strategy($om, $meta->get_name())->process_pre_remove($om, $object);
        }
    }
    /**
     * Checks for persisted Nodes
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
        if ($this->get_configuration($om, $meta->get_name())) {
            $this->get_strategy($om, $meta->get_name())->process_pre_persist($om, $object);
        }
    }
    /**
     * Checks for updated Nodes
     *
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function pre_update(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        if ($this->get_configuration($om, $meta->get_name())) {
            $this->get_strategy($om, $meta->get_name())->process_pre_update($om, $object);
        }
    }
    /**
     * Checks for pending Nodes to fully synchronize
     * the tree
     *
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function post_persist(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        if ($this->get_configuration($om, $meta->get_name())) {
            $this->get_strategy($om, $meta->get_name())->process_post_persist($om, $object, $ea);
        }
    }
    /**
     * Checks for pending Nodes to fully synchronize
     * the tree
     *
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function post_update(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        if ($this->get_configuration($om, $meta->get_name())) {
            $this->get_strategy($om, $meta->get_name())->process_post_update($om, $object, $ea);
        }
    }
    /**
     * Checks for pending Nodes to fully synchronize
     * the tree
     *
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function post_remove(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        if ($this->get_configuration($om, $meta->get_name())) {
            $this->get_strategy($om, $meta->get_name())->process_post_remove($om, $object, $ea);
        }
    }
    /**
     * Maps additional metadata
     *
     * @param LoadClassMetadataEventArgs $eventArgs
     *
     * @phpstan-param LoadClassMetadataEventArgs<ClassMetadata<object>, ObjectManager> $eventArgs
     */
    public function load_class_metadata(Event_Args $event_args): void
    {
        $om = $event_args->get_object_manager();
        $meta = $event_args->get_class_metadata();
        $this->load_metadata_for_object_class($om, $meta);
        if (isset(self::$configurations[$this->name][$meta->get_name()]) && self::$configurations[$this->name][$meta->get_name()]) {
            $this->get_strategy($om, $meta->get_name())->process_metadata_load($om, $meta);
        }
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
    /**
     * Get the list of strategy instances used for
     * given object classes
     *
     * @phpstan-param array<class-string, null> $classes
     *
     * @return array<string, Strategy>
     *
     * @phpstan-return array<value-of<self::strategies>, Strategy>
     */
    protected function get_strategies_used_for_objects(array $classes): array
    {
        $strategies = [];
        foreach ($classes as $name => $opt) {
            if (isset($this->strategies[$name]) && !isset($strategies[$this->strategies[$name]])) {
                $strategies[$this->strategies[$name]] = $this->strategy_instances[$this->strategies[$name]];
            }
        }
        return $strategies;
    }
}