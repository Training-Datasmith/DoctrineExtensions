<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo;

use Doctrine\Common\Event_Args;
use Doctrine\DBAL\Types\Type;
use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ODM\Mongo_Db\Types\Type as TypeODM;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Unit_Of_Work;
use Doctrine\Persistence\Event\Lifecycle_Event_Args;
use Doctrine\Persistence\Event\Load_Class_Metadata_Event_Args;
use Doctrine\Persistence\Event\Manager_Event_Args;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Notify_Property_Changed;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Mapping\Event\Adapter_Interface;
use Gedmo\Mapping\Mapped_Event_Subscriber;
/**
 * The AbstractTrackingListener provides generic functions for all listeners.
 *
 * @template TConfig of array
 * @template TEventAdapter of AdapterInterface
 *
 * @template-extends MappedEventSubscriber<TConfig, TEventAdapter>
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
abstract class Abstract_Tracking_Listener extends Mapped_Event_Subscriber
{
    /**
     * Specifies the list of events to listen on.
     *
     * @return string[]
     */
    public function get_subscribed_events()
    {
        return ['prePersist', 'onFlush', 'loadClassMetadata'];
    }
    /**
     * Maps additional metadata for the object.
     *
     * @param LoadClassMetadataEventArgs $eventArgs
     *
     * @phpstan-param LoadClassMetadataEventArgs<ClassMetadata<object>, ObjectManager> $eventArgs
     */
    public function load_class_metadata(Event_Args $event_args): void
    {
        $this->load_metadata_for_object_class($event_args->get_object_manager(), $event_args->get_class_metadata());
    }
    /**
     * Processes object updates when the manager is flushed.
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
        // check all scheduled updates
        $all = array_merge($ea->get_scheduled_object_insertions($uow), $ea->get_scheduled_object_updates($uow));
        foreach ($all as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            if (!$config = $this->get_configuration($om, $meta->get_name())) {
                continue;
            }
            $change_set = $ea->get_object_change_set($uow, $object);
            $need_changes = false;
            if ($uow->is_scheduled_for_insert($object) && isset($config['create'])) {
                foreach ($config['create'] as $field) {
                    // Field can not exist in change set, i.e. when persisting an embedded object without a parent
                    $new = array_key_exists($field, $change_set) ? $change_set[$field][1] : false;
                    if (null === $new) {
                        // let manual values
                        $need_changes = true;
                        $this->update_field($object, $ea, $meta, $field);
                    }
                }
            }
            if (isset($config['update'])) {
                foreach ($config['update'] as $field) {
                    $is_insert_and_null = $uow->is_scheduled_for_insert($object) && array_key_exists($field, $change_set) && null === $change_set[$field][1];
                    if (!isset($change_set[$field]) || $is_insert_and_null) {
                        // let manual values
                        $need_changes = true;
                        $this->update_field($object, $ea, $meta, $field);
                    }
                }
            }
            if (!$uow->is_scheduled_for_insert($object) && isset($config['change'])) {
                foreach ($config['change'] as $options) {
                    if (isset($change_set[$options['field']])) {
                        continue;
                        // value was set manually
                    }
                    if (!is_array($options['trackedField'])) {
                        $single_field = true;
                        $tracked_fields = [$options['trackedField']];
                    } else {
                        $single_field = false;
                        $tracked_fields = $options['trackedField'];
                    }
                    foreach ($tracked_fields as $tracked_field) {
                        $tracked_child = null;
                        $tracked = null;
                        $parts = explode('.', $tracked_field);
                        if (isset($parts[1])) {
                            $tracked = $parts[0];
                            $tracked_child = $parts[1];
                        }
                        if (!isset($tracked) || array_key_exists($tracked_field, $change_set)) {
                            $tracked = $tracked_field;
                            $tracked_child = null;
                        }
                        if (isset($change_set[$tracked])) {
                            $changes = $change_set[$tracked];
                            if (isset($tracked_child)) {
                                $changing_object = $changes[1];
                                if (!is_object($changing_object)) {
                                    throw new UnexpectedValueException("Field - [{$tracked}] is expected to be object in class - {$meta->get_name()}");
                                }
                                $object_meta = $om->get_class_metadata(get_class($changing_object));
                                $om->initialize_object($changing_object);
                                $value = $object_meta->get_field_value($changing_object, $tracked_child);
                            } else {
                                $value = $changes[1];
                            }
                            $configured_values = $this->get_php_values($options['value'], $meta->get_type_of_field($tracked), $om);
                            if (null === $configured_values || $single_field && in_array($value, $configured_values, true)) {
                                $need_changes = true;
                                $this->update_field($object, $ea, $meta, $options['field']);
                            }
                        }
                    }
                }
            }
            if ($need_changes) {
                $ea->recompute_single_object_change_set($uow, $meta, $object);
            }
        }
    }
    /**
     * Processes updates when an object is persisted in the manager.
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
            if (isset($config['update'])) {
                foreach ($config['update'] as $field) {
                    if (null === $meta->get_field_value($object, $field)) {
                        // let manual values
                        $this->update_field($object, $ea, $meta, $field);
                    }
                }
            }
            if (isset($config['create'])) {
                foreach ($config['create'] as $field) {
                    if (null === $meta->get_field_value($object, $field)) {
                        // let manual values
                        $this->update_field($object, $ea, $meta, $field);
                    }
                }
            }
        }
    }
    /**
     * Get the value for an updated field.
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     * @param TEventAdapter         $eventAdapter
     *
     * @return mixed
     */
    abstract protected function get_field_value($meta, $field, $event_adapter);
    /**
     * Updates a field.
     *
     * @param object                $object
     * @param TEventAdapter         $eventAdapter
     * @param ClassMetadata<object> $meta
     * @param string                $field
     *
     * @return void
     */
    protected function update_field($object, $event_adapter, $meta, $field)
    {
        $old_value = $meta->get_field_value($object, $field);
        $new_value = $this->get_field_value($meta, $field, $event_adapter);
        // if field value is reference, persist object
        if ($meta->has_association($field) && is_object($new_value) && !$event_adapter->get_object_manager()->contains($new_value)) {
            $uow = $event_adapter->get_object_manager()->get_unit_of_work();
            // Check to persist only when the object isn't already managed, always persists for MongoDB
            if (!$uow instanceof Unit_Of_Work || Unit_Of_Work::STATE_MANAGED !== $uow->get_entity_state($new_value)) {
                $event_adapter->get_object_manager()->persist($new_value);
            }
        }
        $meta->set_field_value($object, $field, $new_value);
        if ($object instanceof Notify_Property_Changed) {
            $uow = $event_adapter->get_object_manager()->get_unit_of_work();
            $uow->property_changed($object, $field, $old_value, $new_value);
        }
    }
    /**
     * @param mixed $values
     *
     * @return mixed[]|null
     */
    private function get_php_values($values, ?string $type, Object_Manager $om): ?array
    {
        if (null === $values) {
            return null;
        }
        if (!is_array($values)) {
            $values = [$values];
        }
        if (null !== $type) {
            foreach ($values as $i => $value) {
                if ($om instanceof Document_Manager) {
                    if (Type_Odm::has_type($type)) {
                        $values[$i] = Type_Odm::get_type($type)->convert_to_php_value($value);
                    } else {
                        $values[$i] = $value;
                    }
                } elseif ($om instanceof Entity_Manager_Interface) {
                    if (Type::has_type($type)) {
                        $values[$i] = $om->get_connection()->convert_to_php_value($value, $type);
                    } else {
                        $values[$i] = $value;
                    }
                }
            }
        }
        return $values;
    }
}