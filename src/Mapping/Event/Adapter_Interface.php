<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Mapping\Event;

use Doctrine\Common\Event_Args;
use Doctrine\ODM\Mongo_Db\Unit_Of_Work as MongoDBUnitOfWork;
use Doctrine\ORM\Unit_Of_Work as ORMUnitOfWork;
use Doctrine\Persistence\Event\Lifecycle_Event_Args;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
/**
 * Doctrine event adapter for Doctrine extensions.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @method LifecycleEventArgs<ObjectManager> createLifecycleEventArgsInstance(object $object, ObjectManager $manager) @deprecated
 * @method object                            getObject()
 */
interface Adapter_Interface
{
    /**
     * @deprecated since gedmo/doctrine-extensions 3.5, will be removed in version 4.0.
     *
     * Calls a method on the event args object.
     *
     * @param string            $method
     * @param array<int, mixed> $args
     *
     * @return mixed
     */
    public function __call($method, $args);
    /**
     * Set the event args object.
     *
     * @return void
     */
    public function set_event_args(Event_Args $args);
    /**
     * Get the name of the domain object.
     *
     * @return string
     */
    public function get_domain_object_name();
    /**
     * Get the name of the manager used by this adapter.
     *
     * @return string
     */
    public function get_manager_name();
    /**
     * Get the root object class, handles inheritance
     *
     * @param ClassMetadata<object> $meta
     *
     * @return string
     *
     * @phpstan-return class-string
     */
    public function get_root_object_class($meta);
    /**
     * Get the object manager.
     *
     * @return ObjectManager
     */
    public function get_object_manager();
    /**
     * Gets the state of an object from the unit of work.
     *
     * @param ORMUnitOfWork|MongoDBUnitOfWork $uow    The UnitOfWork as provided by the object manager
     * @param object                          $object
     *
     * @return int The object state as reported by the unit of work
     */
    public function get_object_state($uow, $object);
    /**
     * Gets the changeset for an object from the unit of work.
     *
     * @param ORMUnitOfWork|MongoDBUnitOfWork $uow    The UnitOfWork as provided by the object manager
     * @param object                          $object
     *
     * @return array<string, array<int, mixed>|object>
     *
     * @phpstan-return array<string, array{0: mixed, 1: mixed}|object>
     */
    public function get_object_change_set($uow, $object);
    /**
     * Get the single identifier field name.
     *
     * @param ClassMetadata<object> $meta
     *
     * @return string
     */
    public function get_single_identifier_field_name($meta);
    /**
     * Computes the changeset of an individual object, independently of the
     * computeChangeSets() routine that is used at the beginning of a unit
     * of work's commit.
     *
     * @param ORMUnitOfWork|MongoDBUnitOfWork $uow    The UnitOfWork as provided by the object manager
     * @param ClassMetadata<object>           $meta
     * @param object                          $object
     *
     * @return void
     */
    public function recompute_single_object_change_set($uow, $meta, $object);
    /**
     * Gets the currently scheduled object updates from the unit of work.
     *
     * @param ORMUnitOfWork|MongoDBUnitOfWork $uow The UnitOfWork as provided by the object manager
     *
     * @return array<int|string, object>
     */
    public function get_scheduled_object_updates($uow);
    /**
     * Gets the currently scheduled object insertions in the unit of work.
     *
     * @param ORMUnitOfWork|MongoDBUnitOfWork $uow The UnitOfWork as provided by the object manager
     *
     * @return array<int|string, object>
     */
    public function get_scheduled_object_insertions($uow);
    /**
     * Gets the currently scheduled object deletions in the unit of work.
     *
     * @param ORMUnitOfWork|MongoDBUnitOfWork $uow The UnitOfWork as provided by the object manager
     *
     * @return array<int|string, object>
     */
    public function get_scheduled_object_deletions($uow);
    /**
     * Sets a property value of the original data array of an object.
     *
     * @param ORMUnitOfWork|MongoDBUnitOfWork $uow
     * @param object                          $object
     * @param string                          $property
     * @param mixed                           $value
     *
     * @return void
     */
    public function set_original_object_property($uow, $object, $property, $value);
    /**
     * Clears the property changeset of the object with the given OID.
     *
     * @param ORMUnitOfWork|MongoDBUnitOfWork $uow
     * @param object                          $object
     *
     * @return void
     */
    public function clear_object_change_set($uow, $object);
}