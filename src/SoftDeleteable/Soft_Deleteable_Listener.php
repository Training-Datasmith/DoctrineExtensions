<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable;

use Doctrine\Common\Event_Args;
use Doctrine\Common\Event_Manager;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ODM\Mongo_Db\Unit_Of_Work as MongoDBUnitOfWork;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Event\Lifecycle_Event_Args;
use Doctrine\Persistence\Event\Load_Class_Metadata_Event_Args;
use Doctrine\Persistence\Event\Manager_Event_Args;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Mapping\Mapped_Event_Subscriber;
use Gedmo\Soft_Deleteable\Event\Post_Soft_Delete_Event_Args;
use Gedmo\Soft_Deleteable\Event\Pre_Soft_Delete_Event_Args;
use Gedmo\Soft_Deleteable\Mapping\Event\Soft_Deleteable_Adapter;
/**
 * SoftDeleteable listener
 *
 * @phpstan-extends MappedEventSubscriber<array, SoftDeleteableAdapter>
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Soft_Deleteable_Listener extends Mapped_Event_Subscriber
{
    /**
     * Pre soft-delete event
     *
     * @var string
     */
    public const PRE_SOFT_DELETE = 'preSoftDelete';
    /**
     * Post soft-delete event
     *
     * @var string
     */
    public const POST_SOFT_DELETE = 'postSoftDelete';
    /**
     * Whether the postFlush event should be handled.
     */
    private bool $handle_post_flush_event;
    /**
     * Objects soft-deleted on flush.
     *
     * @var array<object>
     */
    private array $soft_deleted_objects = [];
    public function __construct(bool $handle_post_flush_event = false)
    {
        parent::__construct();
        $this->handle_post_flush_event = $handle_post_flush_event;
    }
    /**
     * @return string[]
     */
    public function get_subscribed_events(): array
    {
        return ['loadClassMetadata', 'onFlush', 'postFlush'];
    }
    /**
     * If it's a SoftDeleteable object, update the "deletedAt" field
     * and skip the removal of the object
     *
     * @param ManagerEventArgs $args
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $args
     */
    public function on_flush(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        /** @var EntityManagerInterface|DocumentManager $om */
        $om = $ea->get_object_manager();
        $uow = $om->get_unit_of_work();
        $evm = $om->get_event_manager();
        // getScheduledDocumentDeletions
        foreach ($ea->get_scheduled_object_deletions($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            $config = $this->get_configuration($om, $meta->get_name());
            if (isset($config['softDeleteable']) && $config['softDeleteable']) {
                $old_value = $meta->get_field_value($object, $config['fieldName']);
                $date = $ea->get_date_value($meta, $config['fieldName']);
                if (isset($config['hardDelete']) && $config['hardDelete'] && $old_value instanceof \DateTimeInterface && $old_value <= $date) {
                    continue;
                    // want to hard delete
                }
                if ($evm->has_listeners(self::PRE_SOFT_DELETE)) {
                    // @todo: in the next major remove check and only instantiate the event
                    $pre_soft_delete_event_args = $this->has_to_dispatch_new_event($om, $evm, self::PRE_SOFT_DELETE, Pre_Soft_Delete_Event_Args::class) ? new Pre_Soft_Delete_Event_Args($object, $om) : $ea->create_lifecycle_event_args_instance($object, $om);
                    $evm->dispatch_event(self::PRE_SOFT_DELETE, $pre_soft_delete_event_args);
                }
                $meta->set_field_value($object, $config['fieldName'], $date);
                $om->persist($object);
                $uow->property_changed($object, $config['fieldName'], $old_value, $date);
                if ($uow instanceof Mongo_Db_Unit_Of_Work) {
                    $ea->recompute_single_object_change_set($uow, $meta, $object);
                } else {
                    $uow->schedule_extra_update($object, [$config['fieldName'] => [$old_value, $date]]);
                }
                if ($evm->has_listeners(self::POST_SOFT_DELETE)) {
                    // @todo: in the next major remove check and only instantiate the event
                    $post_soft_delete_event_args = $this->has_to_dispatch_new_event($om, $evm, self::POST_SOFT_DELETE, Post_Soft_Delete_Event_Args::class) ? new Post_Soft_Delete_Event_Args($object, $om) : $ea->create_lifecycle_event_args_instance($object, $om);
                    $evm->dispatch_event(self::POST_SOFT_DELETE, $post_soft_delete_event_args);
                }
                if ($this->handle_post_flush_event) {
                    $this->soft_deleted_objects[] = $object;
                }
            }
        }
    }
    /**
     * Detach soft-deleted objects from object manager.
     */
    public function post_flush(Event_Args $args): void
    {
        if (!$this->handle_post_flush_event) {
            return;
        }
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        foreach ($this->soft_deleted_objects as $index => $object) {
            $om->detach($object);
            unset($this->soft_deleted_objects[$index]);
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
        $this->load_metadata_for_object_class($event_args->get_object_manager(), $event_args->get_class_metadata());
    }
    public function set_handle_post_flush_event(bool $handle_post_flush_event): void
    {
        $this->handle_post_flush_event = $handle_post_flush_event;
    }
    public function should_handle_post_flush_event(): bool
    {
        return $this->handle_post_flush_event;
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
    /** @param class-string $eventClass */
    private function has_to_dispatch_new_event(Object_Manager $object_manager, Event_Manager $event_manager, string $event_name, string $event_class): bool
    {
        if ($object_manager instanceof Entity_Manager_Interface && !class_exists(Lifecycle_Event_Args::class)) {
            return true;
        }
        foreach ($event_manager->get_listeners($event_name) as $listener) {
            $refl_method = new \ReflectionMethod($listener, $event_name);
            $parameters = $refl_method->get_parameters();
            if (1 !== count($parameters) || !$parameters[0]->has_type() || !$parameters[0]->get_type() instanceof \ReflectionNamedType || $event_class !== $parameters[0]->get_type()->get_name()) {
                Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2649', 'Type-hinting to something different than "%s" in "%s::%s()" is deprecated.', $event_class, get_class($listener), $refl_method->get_name());
                return false;
            }
        }
        return true;
    }
}