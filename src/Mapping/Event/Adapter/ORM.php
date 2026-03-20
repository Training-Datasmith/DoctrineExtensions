<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Mapping\Event\Adapter;

use Doctrine\Common\Event_Args;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Event\Lifecycle_Event_Args;
use Doctrine\ORM\Mapping\Class_Metadata;
use Gedmo\Exception\RuntimeException;
use Gedmo\Mapping\Event\Adapter_Interface;
/**
 * Doctrine event adapter for ORM specific
 * event arguments
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
class ORM implements Adapter_Interface
{
    private ?Event_Args $args = null;
    private ?Entity_Manager_Interface $em = null;
    public function __call($method, $args)
    {
        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2409', 'Using "%s()" method is deprecated since gedmo/doctrine-extensions 3.5 and will be removed in version 4.0.', __METHOD__);
        if (null === $this->args) {
            throw new RuntimeException('Event args must be set before calling its methods');
        }
        $method = str_replace('Object', $this->get_domain_object_name(), $method);
        return call_user_func_array([$this->args, $method], $args);
    }
    public function set_event_args(Event_Args $args): void
    {
        $this->args = $args;
    }
    public function get_domain_object_name(): string
    {
        return 'Entity';
    }
    public function get_manager_name(): string
    {
        return 'ORM';
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public function get_root_object_class($meta)
    {
        return $meta->root_entity_name;
    }
    /**
     * Set the entity manager
     */
    public function set_entity_manager(Entity_Manager_Interface $em): void
    {
        $this->em = $em;
    }
    /**
     * @return EntityManagerInterface
     */
    public function get_object_manager()
    {
        if (null !== $this->em) {
            return $this->em;
        }
        if (null === $this->args) {
            throw new \LogicException(sprintf('Event args must be set before calling "%s()".', __METHOD__));
        }
        // todo: for the next major release, uncomment the next line:
        // return $this->args->getObjectManager();
        // and remove anything past this
        if (\method_exists($this->args, 'getObjectManager')) {
            return $this->args->get_object_manager();
        }
        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2639', 'Calling "%s()" on event args of class "%s" that does not implement "getObjectManager()" is deprecated since gedmo/doctrine-extensions 3.14' . ' and will throw a "%s" error in version 4.0.', __METHOD__, get_class($this->args), \Error::class);
        return $this->args->get_entity_manager();
    }
    public function get_object(): object
    {
        if (null === $this->args) {
            throw new \LogicException(sprintf('Event args must be set before calling "%s()".', __METHOD__));
        }
        // todo: for the next major release, uncomment the next line:
        // return $this->args->getObject();
        // and remove anything past this
        if (\method_exists($this->args, 'getObject')) {
            return $this->args->get_object();
        }
        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2639', 'Calling "%s()" on event args of class "%s" that does not imeplement "getObject()" is deprecated since gedmo/doctrine-extensions 3.14' . ' and will throw a "%s" error in version 4.0.', __METHOD__, get_class($this->args), \Error::class);
        return $this->args->get_entity();
    }
    public function get_object_state($uow, $object)
    {
        return $uow->get_entity_state($object);
    }
    public function get_object_change_set($uow, $object)
    {
        return $uow->get_entity_change_set($object);
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public function get_single_identifier_field_name($meta)
    {
        return $meta->get_single_identifier_field_name();
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public function recompute_single_object_change_set($uow, $meta, $object): void
    {
        $uow->recompute_single_entity_change_set($meta, $object);
    }
    public function get_scheduled_object_updates($uow)
    {
        return $uow->get_scheduled_entity_updates();
    }
    public function get_scheduled_object_insertions($uow)
    {
        return $uow->get_scheduled_entity_insertions();
    }
    public function get_scheduled_object_deletions($uow)
    {
        return $uow->get_scheduled_entity_deletions();
    }
    public function set_original_object_property($uow, $object, $property, $value): void
    {
        $uow->set_original_entity_property(spl_object_id($object), $property, $value);
    }
    public function clear_object_change_set($uow, $object): void
    {
        $change_set =& $uow->get_entity_change_set($object);
        $change_set = [];
    }
    /**
     * @deprecated use custom lifecycle event classes instead
     *
     * Creates an ORM specific LifecycleEventArgs
     *
     * @param object                 $object
     * @param EntityManagerInterface $entityManager
     *
     * @return LifecycleEventArgs
     */
    public function create_lifecycle_event_args_instance($object, $entity_manager)
    {
        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2649', 'Using "%s()" method is deprecated since gedmo/doctrine-extensions 3.15 and will be removed in version 4.0.', __METHOD__);
        if (!class_exists(Lifecycle_Event_Args::class)) {
            throw new \RuntimeException(sprintf('Cannot call %s() when using doctrine/orm >=3.0, use a custom lifecycle event class instead.', __METHOD__));
        }
        return new Lifecycle_Event_Args($object, $entity_manager);
    }
}