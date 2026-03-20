<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable;

use Doctrine\Common\Event_Args;
use Doctrine\ORM\Mapping\Class_Metadata as ORMClassMetadata;
use Doctrine\Persistence\Event\Lifecycle_Event_Args;
use Doctrine\Persistence\Event\Load_Class_Metadata_Event_Args;
use Doctrine\Persistence\Event\Manager_Event_Args;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Loggable\Mapping\Event\Loggable_Adapter;
use Gedmo\Mapping\Mapped_Event_Subscriber;
use Gedmo\Tool\Actor_Provider_Interface;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
/**
 * Loggable listener
 *
 * @author Boussekeyt Jules <jules.boussekeyt@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @phpstan-type LoggableConfiguration = array{
 *   loggable?: bool,
 *   logEntryClass?: class-string<LogEntryInterface<T>>,
 *   useObjectClass?: class-string,
 *   versioned?: string[],
 * }
 *
 * @template T of Loggable|object
 *
 * @phpstan-extends MappedEventSubscriber<LoggableConfiguration, LoggableAdapter>
 */
class Loggable_Listener extends Mapped_Event_Subscriber
{
    /**
     * @deprecated use `LogEntryInterface::ACTION_CREATE` instead
     */
    public const ACTION_CREATE = Log_Entry_Interface::ACTION_CREATE;
    /**
     * @deprecated use `LogEntryInterface::ACTION_UPDATE` instead
     */
    public const ACTION_UPDATE = Log_Entry_Interface::ACTION_UPDATE;
    /**
     * @deprecated use `LogEntryInterface::ACTION_REMOVE` instead
     */
    public const ACTION_REMOVE = Log_Entry_Interface::ACTION_REMOVE;
    protected ?Actor_Provider_Interface $actor_provider = null;
    /**
     * Username for identification
     *
     * @var string
     */
    protected $username;
    /**
     * List of log entries which do not have the foreign
     * key generated yet - MySQL case. These entries
     * will be updated with new keys on postPersist event
     *
     * @var array<int, LogEntryInterface>
     *
     * @phpstan-var array<int, LogEntryInterface<T>>
     */
    protected $pending_log_entry_inserts = [];
    /**
     * For log of changed relations we use
     * its identifiers to avoid storing serialized Proxies.
     * These are pending relations in case it does not
     * have an identifier yet
     *
     * @var array<int, array<int, array<string, LogEntryInterface|string>>>
     *
     * @phpstan-var array<int, array<int, array{log: LogEntryInterface<T>, field: string}>>
     */
    protected $pending_related_objects = [];
    /**
     * Set an actor provider for the user value.
     */
    public function set_actor_provider(Actor_Provider_Interface $actor_provider): void
    {
        $this->actor_provider = $actor_provider;
    }
    /**
     * Set username for identification
     *
     * If an actor provider is also provided, it will take precedence over this value.
     *
     * @param mixed $username
     *
     * @throws InvalidArgumentException Invalid username
     */
    public function set_username($username): void
    {
        if (is_string($username)) {
            $this->username = $username;
        } elseif (is_object($username) && method_exists($username, 'getUserIdentifier')) {
            $this->username = (string) $username->get_user_identifier();
        } elseif (is_object($username) && method_exists($username, 'getUsername')) {
            $this->username = (string) $username->get_username();
        } elseif (is_object($username) && method_exists($username, '__toString')) {
            $this->username = $username->__toString();
        } else {
            throw new InvalidArgumentException('Username must be a string, or object should have method getUserIdentifier, getUsername or __toString');
        }
    }
    /**
     * @return string[]
     */
    public function get_subscribed_events(): array
    {
        return ['onFlush', 'loadClassMetadata', 'postPersist'];
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
    /**
     * Checks for inserted object to update its logEntry
     * foreign key
     *
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function post_persist(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $object = $ea->get_object();
        $om = $ea->get_object_manager();
        $oid = spl_object_id($object);
        $uow = $om->get_unit_of_work();
        if ($this->pending_log_entry_inserts && array_key_exists($oid, $this->pending_log_entry_inserts)) {
            $wrapped = Abstract_Wrapper::wrap($object, $om);
            $log_entry = $this->pending_log_entry_inserts[$oid];
            $log_entry_meta = $om->get_class_metadata(get_class($log_entry));
            $id = $wrapped->get_identifier(false, true);
            $log_entry_meta->set_field_value($log_entry, 'objectId', $id);
            $uow->schedule_extra_update($log_entry, ['objectId' => [null, $id]]);
            $ea->set_original_object_property($uow, $log_entry, 'objectId', $id);
            unset($this->pending_log_entry_inserts[$oid]);
        }
        if ($this->pending_related_objects && array_key_exists($oid, $this->pending_related_objects)) {
            $wrapped = Abstract_Wrapper::wrap($object, $om);
            $identifiers = $wrapped->get_identifier(false);
            foreach ($this->pending_related_objects[$oid] as $props) {
                $log_entry = $props['log'];
                $log_entry_meta = $om->get_class_metadata(get_class($log_entry));
                $old_data = $data = $log_entry->get_data();
                $data[$props['field']] = $identifiers;
                $log_entry->set_data($data);
                $uow->schedule_extra_update($log_entry, ['data' => [$old_data, $data]]);
                $ea->set_original_object_property($uow, $log_entry, 'data', $data);
            }
            unset($this->pending_related_objects[$oid]);
        }
    }
    /**
     * Looks for loggable objects being inserted or updated
     * for further processing
     *
     * @param ManagerEventArgs $eventArgs
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $eventArgs
     */
    public function on_flush(Event_Args $event_args): void
    {
        $ea = $this->get_event_adapter($event_args);
        $om = $ea->get_object_manager();
        $uow = $om->get_unit_of_work();
        foreach ($ea->get_scheduled_object_insertions($uow) as $object) {
            $this->create_log_entry(Log_Entry_Interface::ACTION_CREATE, $object, $ea);
        }
        foreach ($ea->get_scheduled_object_updates($uow) as $object) {
            $this->create_log_entry(Log_Entry_Interface::ACTION_UPDATE, $object, $ea);
        }
        foreach ($ea->get_scheduled_object_deletions($uow) as $object) {
            $this->create_log_entry(Log_Entry_Interface::ACTION_REMOVE, $object, $ea);
        }
    }
    /**
     * Get the LogEntry class
     *
     *
     * @phpstan-param class-string $class
     *
     * @return string
     * @phpstan-return class-string<LogEntryInterface<T>>
     */
    protected function get_log_entry_class(Loggable_Adapter $ea, string $class)
    {
        return self::$configurations[$this->name][$class]['logEntryClass'] ?? $ea->get_default_log_entry_class();
    }
    /**
     * Retrieve the username to use for the log entry.
     *
     * This method will try to fetch a username from the actor provider first, falling back to the {@see $this->username}
     * property if the provider is not set or does not provide a value.
     *
     * @throws UnexpectedValueException if the actor provider provides an unsupported username value
     */
    protected function get_username(): ?string
    {
        if ($this->actor_provider instanceof Actor_Provider_Interface) {
            $actor = $this->actor_provider->get_actor();
            if (is_string($actor) || null === $actor) {
                return $actor;
            }
            if (method_exists($actor, 'getUserIdentifier')) {
                return (string) $actor->get_user_identifier();
            }
            if (method_exists($actor, 'getUsername')) {
                return (string) $actor->get_username();
            }
            if (method_exists($actor, '__toString')) {
                return $actor->__toString();
            }
            throw new UnexpectedValueException(\sprintf('The loggable extension requires the actor provider to return a string or an object implementing the "getUserIdentifier()", "getUsername()", or "__toString()" methods. "%s" cannot be used as an actor.', get_class($actor)));
        }
        return $this->username;
    }
    /**
     * Handle any custom LogEntry functionality that needs to be performed
     * before persisting it
     *
     * @param LogEntryInterface $logEntry The LogEntry being persisted
     * @param object            $object   The object being Logged
     *
     * @phpstan-param LogEntryInterface<T> $logEntry
     * @phpstan-param T $object
     *
     * @return void
     */
    protected function pre_persist_log_entry($log_entry, $object)
    {
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
    /**
     * Returns an objects changeset data
     *
     * @param LoggableAdapter   $ea
     * @param object            $object
     * @param LogEntryInterface $logEntry
     *
     * @phpstan-param T $object
     * @phpstan-param LogEntryInterface<T> $logEntry
     *
     * @return array<string, mixed>
     */
    protected function get_object_change_set_data($ea, $object, $log_entry): array
    {
        $om = $ea->get_object_manager();
        $wrapped = Abstract_Wrapper::wrap($object, $om);
        $meta = $wrapped->get_metadata();
        $config = $this->get_configuration($om, $meta->get_name());
        $uow = $om->get_unit_of_work();
        $new_values = [];
        foreach ($ea->get_object_change_set($uow, $object) as $field => $changes) {
            if (empty($config['versioned'])) {
                continue;
            }
            if (!in_array($field, $config['versioned'], true)) {
                continue;
            }
            $value = $changes[1];
            if ($meta->is_single_valued_association($field) && $value) {
                if ($wrapped->is_embedded_association($field)) {
                    $value = $this->get_object_change_set_data($ea, $value, $log_entry);
                } else {
                    $oid = spl_object_id($value);
                    $wrapped_assoc = Abstract_Wrapper::wrap($value, $om);
                    $value = $wrapped_assoc->get_identifier(false);
                    if (!is_array($value) && !$value) {
                        $this->pending_related_objects[$oid][] = ['log' => $log_entry, 'field' => $field];
                    }
                }
            }
            $new_values[$field] = $value;
        }
        return $new_values;
    }
    /**
     * Create a new Log instance
     *
     * @param string $action
     * @param object $object
     *
     * @phpstan-param LogEntryInterface::ACTION_CREATE|LogEntryInterface::ACTION_UPDATE|LogEntryInterface::ACTION_REMOVE $action
     * @phpstan-param T $object
     *
     * @return LogEntryInterface|null
     *
     * @phpstan-return LogEntryInterface<T>|null
     */
    protected function create_log_entry($action, $object, Loggable_Adapter $ea)
    {
        $om = $ea->get_object_manager();
        $wrapped = Abstract_Wrapper::wrap($object, $om);
        $meta = $wrapped->get_metadata();
        // Filter embedded documents
        if (isset($meta->is_embedded_document) && $meta->is_embedded_document) {
            return null;
        }
        if ($config = $this->get_configuration($om, $meta->get_name())) {
            $log_entry_class = $this->get_log_entry_class($ea, $meta->get_name());
            $log_entry_meta = $om->get_class_metadata($log_entry_class);
            /** @var LogEntryInterface<T> $logEntry */
            $log_entry = $log_entry_meta->new_instance();
            $log_entry->set_action($action);
            $log_entry->set_username($this->get_username());
            $log_entry->set_object_class($meta->get_name());
            $log_entry->set_logged_at();
            // check for the availability of the primary key
            $uow = $om->get_unit_of_work();
            if (Log_Entry_Interface::ACTION_CREATE === $action && ($ea->is_post_insert_generator($meta) || $meta instanceof Orm_Class_Metadata && $meta->is_identifier_composite)) {
                $this->pending_log_entry_inserts[spl_object_id($object)] = $log_entry;
            } else {
                $log_entry->set_object_id($wrapped->get_identifier(false, true));
            }
            $new_values = [];
            if (Log_Entry_Interface::ACTION_REMOVE !== $action && isset($config['versioned'])) {
                $new_values = $this->get_object_change_set_data($ea, $object, $log_entry);
                $log_entry->set_data($new_values);
            }
            if (Log_Entry_Interface::ACTION_UPDATE === $action && [] === $new_values) {
                return null;
            }
            $version = 1;
            if (Log_Entry_Interface::ACTION_CREATE !== $action) {
                $version = $ea->get_new_version($log_entry_meta, $object);
                if (empty($version)) {
                    // was versioned later
                    $version = 1;
                }
            }
            $log_entry->set_version($version);
            $this->pre_persist_log_entry($log_entry, $object);
            $om->persist($log_entry);
            $uow->compute_change_set($log_entry_meta, $log_entry);
            return $log_entry;
        }
        return null;
    }
}