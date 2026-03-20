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
use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ODM\Mongo_Db\Event\Lifecycle_Event_Args;
use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Doctrine\ODM\Mongo_Db\Unit_Of_Work;
use Gedmo\Exception\RuntimeException;
use Gedmo\Mapping\Event\Adapter_Interface;
/**
 * Doctrine event adapter for ODM specific
 * event arguments
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
class ODM implements Adapter_Interface
{
    private ?Event_Args $args = null;
    private ?Document_Manager $dm = null;
    private static ?bool $use_int_id = null;
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
        return 'Document';
    }
    public function get_manager_name(): string
    {
        return 'ODM';
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public function get_root_object_class($meta)
    {
        return $meta->root_document_name;
    }
    /**
     * Set the document manager
     */
    public function set_document_manager(Document_Manager $dm): void
    {
        $this->dm = $dm;
    }
    /**
     * @return DocumentManager
     */
    public function get_object_manager()
    {
        if (null !== $this->dm) {
            return $this->dm;
        }
        if (null === $this->args) {
            throw new \LogicException(sprintf('Event args must be set before calling "%s()".', __METHOD__));
        }
        return $this->args->get_document_manager();
    }
    public function get_object(): object
    {
        if (null === $this->args) {
            throw new \LogicException(sprintf('Event args must be set before calling "%s()".', __METHOD__));
        }
        return $this->args->get_document();
    }
    public function get_object_state($uow, $object)
    {
        return $uow->get_document_state($object);
    }
    public function get_object_change_set($uow, $object)
    {
        return $uow->get_document_change_set($object);
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public function get_single_identifier_field_name($meta)
    {
        return $meta->get_identifier()[0];
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public function recompute_single_object_change_set($uow, $meta, $object): void
    {
        $uow->recompute_single_document_change_set($meta, $object);
    }
    public function get_scheduled_object_updates($uow): array
    {
        $updates = $uow->get_scheduled_document_updates();
        $upserts = $uow->get_scheduled_document_upserts();
        return array_merge($updates, $upserts);
    }
    public function get_scheduled_object_insertions($uow)
    {
        return $uow->get_scheduled_document_insertions();
    }
    public function get_scheduled_object_deletions($uow)
    {
        return $uow->get_scheduled_document_deletions();
    }
    public function set_original_object_property($uow, $object, $property, $value): void
    {
        $uow->set_original_document_property($this->get_oid($uow, $object), $property, $value);
    }
    public function clear_object_change_set($uow, $object): void
    {
        $uow->clear_document_change_set($this->get_oid($uow, $object));
    }
    /**
     * @deprecated to be removed in 4.0, use custom lifecycle event classes instead.
     *
     * Creates a ODM specific LifecycleEventArgs.
     *
     * @param object          $document
     * @param DocumentManager $documentManager
     *
     * @return LifecycleEventArgs
     */
    public function create_lifecycle_event_args_instance($document, $document_manager)
    {
        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2649', 'Using "%s()" method is deprecated since gedmo/doctrine-extensions 3.15 and will be removed in version 4.0.', __METHOD__);
        return new Lifecycle_Event_Args($document, $document_manager);
    }
    /**
     * @return int|string dependent on the version of `doctrine/mongodb-odm` installed
     */
    private function get_oid(Unit_Of_Work $uow, object $object)
    {
        if (null === self::$use_int_id) {
            $refl = new \ReflectionClass($uow);
            $method = $refl->get_method('setOriginalDocumentProperty');
            $oid_arg = $method->get_parameters()[0];
            /** @phpstan-ignore-next-line method.NotFound All supported versions of `doctrine/mongodb-odm` have the first param typehinted */
            self::$use_int_id = 'int' === $oid_arg->get_type()->get_name();
        }
        return true === self::$use_int_id ? spl_object_id($object) : spl_object_hash($object);
    }
}