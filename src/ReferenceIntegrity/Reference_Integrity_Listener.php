<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Reference_Integrity;

use Doctrine\Common\Event_Args;
use Doctrine\Persistence\Event\Lifecycle_Event_Args;
use Doctrine\Persistence\Event\Load_Class_Metadata_Event_Args;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Exception\Reference_Integrity_Strict_Exception;
use Gedmo\Mapping\Event\Adapter_Interface;
use Gedmo\Mapping\Mapped_Event_Subscriber;
use Gedmo\Reference_Integrity\Mapping\Validator;
/**
 * The ReferenceIntegrity listener handles the reference integrity on related documents
 *
 * @phpstan-extends MappedEventSubscriber<array, AdapterInterface>
 *
 * @author Evert Harmeling <evert.harmeling@freshheads.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Reference_Integrity_Listener extends Mapped_Event_Subscriber
{
    /**
     * @return string[]
     */
    public function get_subscribed_events(): array
    {
        return ['loadClassMetadata', 'preRemove'];
    }
    /**
     * Maps additional metadata for the Document
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
     * Looks for referenced objects being removed
     * to nullify the relation or throw an exception
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
        $class = get_class($object);
        $meta = $om->get_class_metadata($class);
        if ($config = $this->get_configuration($om, $meta->get_name())) {
            foreach ($config['referenceIntegrity'] as $property => $action) {
                $ref_doc = $meta->get_field_value($object, $property);
                $field_mapping = $meta->get_field_mapping($property);
                switch ($action) {
                    case Validator::NULLIFY:
                        if (!isset($field_mapping['mappedBy'])) {
                            throw new Invalid_Mapping_Exception(sprintf("Reference '%s' on '%s' should have 'mappedBy' option defined", $property, $meta->get_name()));
                        }
                        assert(class_exists($field_mapping->target_document ?? $field_mapping['targetDocument']));
                        $sub_meta = $om->get_class_metadata($field_mapping->target_document ?? $field_mapping['targetDocument']);
                        $mapped_by_field = $field_mapping->mapped_by ?? $field_mapping['mappedBy'];
                        if (!$sub_meta->has_field($mapped_by_field)) {
                            throw new Invalid_Mapping_Exception(sprintf('Unable to find reference integrity [%s] as mapped property in entity - %s', $mapped_by_field, $field_mapping->target_document ?? $field_mapping['targetDocument']));
                        }
                        if ($meta->is_collection_valued_reference($property)) {
                            foreach ($ref_doc as $ref_obj) {
                                $sub_meta->set_field_value($ref_obj, $mapped_by_field, null);
                                $om->persist($ref_obj);
                            }
                        } else {
                            $sub_meta->set_field_value($ref_doc, $mapped_by_field, null);
                            $om->persist($ref_doc);
                        }
                        break;
                    case Validator::PULL:
                        if (!isset($field_mapping['mappedBy'])) {
                            throw new Invalid_Mapping_Exception(sprintf("Reference '%s' on '%s' should have 'mappedBy' option defined", $property, $meta->get_name()));
                        }
                        assert(class_exists($field_mapping->target_document ?? $field_mapping['targetDocument']));
                        $sub_meta = $om->get_class_metadata($field_mapping->target_document ?? $field_mapping['targetDocument']);
                        $mapped_by_field = $field_mapping->mapped_by ?? $field_mapping['mappedBy'];
                        if (!$sub_meta->has_field($mapped_by_field)) {
                            throw new Invalid_Mapping_Exception(sprintf('Unable to find reference integrity [%s] as mapped property in entity - %s', $mapped_by_field, $field_mapping->target_document ?? $field_mapping['targetDocument']));
                        }
                        if (!$sub_meta->is_collection_valued_reference($mapped_by_field)) {
                            throw new Invalid_Mapping_Exception(sprintf('Reference integrity [%s] mapped property in entity - %s should be a Reference Many', $mapped_by_field, $field_mapping->target_document ?? $field_mapping['targetDocument']));
                        }
                        if ($meta->is_collection_valued_reference($property)) {
                            foreach ($ref_doc as $ref_obj) {
                                $collection = $sub_meta->get_field_value($ref_obj, $mapped_by_field);
                                $collection->remove_element($object);
                                $sub_meta->set_field_value($ref_obj, $mapped_by_field, $collection);
                                $om->persist($ref_obj);
                            }
                        } elseif (is_object($ref_doc)) {
                            $collection = $sub_meta->get_field_value($ref_doc, $mapped_by_field);
                            $collection->remove_element($object);
                            $sub_meta->set_field_value($ref_doc, $mapped_by_field, $collection);
                            $om->persist($ref_doc);
                        }
                        break;
                    case Validator::RESTRICT:
                        if ($meta->is_collection_valued_reference($property) && $ref_doc->count() > 0) {
                            throw new Reference_Integrity_Strict_Exception(sprintf("The reference integrity for the '%s' collection is restricted", $field_mapping->target_document ?? $field_mapping['targetDocument']));
                        }
                        if ($meta->is_single_valued_reference($property) && null !== $ref_doc) {
                            throw new Reference_Integrity_Strict_Exception(sprintf("The reference integrity for the '%s' document is restricted", $field_mapping->target_document ?? $field_mapping['targetDocument']));
                        }
                        break;
                }
            }
        }
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
}