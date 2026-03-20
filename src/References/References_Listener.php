<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\References;

use Doctrine\Common\Collections\Array_Collection;
use Doctrine\Common\Event_Args;
use Doctrine\Persistence\Event\Lifecycle_Event_Args;
use Doctrine\Persistence\Event\Load_Class_Metadata_Event_Args;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Mapping\Mapped_Event_Subscriber;
use Gedmo\References\Mapping\Event\References_Adapter;
/**
 * Listener for loading and persisting cross database references.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 *
 * @phpstan-type ReferenceConfiguration = array{
 *   field?: string,
 *   type?: string,
 *   class?: class-string,
 *   identifier?: string,
 *   mappedBy?: string,
 *   inversedBy?: string,
 * }
 * @phpstan-type ReferencesConfiguration = array{
 *   referenceMany?: array<string, ReferenceConfiguration>,
 *   referenceManyEmbed?: array<string, ReferenceConfiguration>,
 *   referenceOne?: array<string, ReferenceConfiguration>,
 *   useObjectClass?: class-string,
 * }
 *
 * @phpstan-extends MappedEventSubscriber<ReferencesConfiguration, ReferencesAdapter>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class References_Listener extends Mapped_Event_Subscriber
{
    /**
     * @var array<string, ObjectManager>
     */
    private array $managers;
    /**
     * @param array<string, ObjectManager> $managers
     */
    public function __construct(array $managers = [])
    {
        parent::__construct();
        $this->managers = $managers;
    }
    /**
     * @param LoadClassMetadataEventArgs $eventArgs
     *
     * @phpstan-param LoadClassMetadataEventArgs<ClassMetadata<object>, ObjectManager> $eventArgs
     */
    public function load_class_metadata(Event_Args $event_args): void
    {
        $this->load_metadata_for_object_class($event_args->get_object_manager(), $event_args->get_class_metadata());
    }
    /**
     * @param LifecycleEventArgs $eventArgs
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $eventArgs
     */
    public function post_load(Event_Args $event_args): void
    {
        $ea = $this->get_event_adapter($event_args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        $config = $this->get_configuration($om, $meta->get_name());
        if (isset($config['referenceOne'])) {
            foreach ($config['referenceOne'] as $mapping) {
                $property = $meta->refl_class->get_property($mapping['field']);
                if (PHP_VERSION_ID < 80100) {
                    $property->set_accessible(true);
                }
                if (isset($mapping['identifier'])) {
                    $referenced_object_id = $meta->get_field_value($object, $mapping['identifier']);
                    if (null !== $referenced_object_id) {
                        $property->set_value($object, $ea->get_single_reference($this->get_manager($mapping['type']), $mapping['class'], $referenced_object_id));
                    }
                }
            }
        }
        if (isset($config['referenceMany'])) {
            foreach ($config['referenceMany'] as $mapping) {
                $property = $meta->refl_class->get_property($mapping['field']);
                if (PHP_VERSION_ID < 80100) {
                    $property->set_accessible(true);
                }
                if (isset($mapping['mappedBy'])) {
                    $id = $ea->extract_identifier($om, $object);
                    $manager = $this->get_manager($mapping['type']);
                    $class = $mapping['class'];
                    $ref_meta = $manager->get_class_metadata($class);
                    $ref_config = $this->get_configuration($manager, $ref_meta->get_name());
                    if (isset($ref_config['referenceOne'][$mapping['mappedBy']])) {
                        $ref_mapping = $ref_config['referenceOne'][$mapping['mappedBy']];
                        $identifier = $ref_mapping['identifier'];
                        $property->set_value($object, new Lazy_Collection(static fn(): \Doctrine\Common\Collections\Array_Collection => new Array_Collection($manager->get_repository($class)->find_by([$identifier => $id]))));
                    }
                }
            }
        }
        $this->update_many_embed_references($event_args);
    }
    /**
     * @param LifecycleEventArgs $eventArgs
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $eventArgs
     */
    public function pre_persist(Event_Args $event_args): void
    {
        $this->update_references($event_args);
    }
    /**
     * @param LifecycleEventArgs $eventArgs
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $eventArgs
     */
    public function pre_update(Event_Args $event_args): void
    {
        $this->update_references($event_args);
    }
    /**
     * @return string[]
     */
    public function get_subscribed_events(): array
    {
        return ['postLoad', 'loadClassMetadata', 'prePersist', 'preUpdate'];
    }
    /**
     * @param ObjectManager $manager
     *
     */
    public function register_manager(string $type, $manager): void
    {
        $this->managers[$type] = $manager;
    }
    /**
     * @return ObjectManager
     */
    public function get_manager(string $type)
    {
        return $this->managers[$type];
    }
    /**
     * @param LifecycleEventArgs $eventArgs
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $eventArgs
     */
    public function update_many_embed_references(Event_Args $event_args): void
    {
        $ea = $this->get_event_adapter($event_args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        $config = $this->get_configuration($om, $meta->get_name());
        if (isset($config['referenceManyEmbed'])) {
            foreach ($config['referenceManyEmbed'] as $mapping) {
                $property = $meta->refl_class->get_property($mapping['field']);
                if (PHP_VERSION_ID < 80100) {
                    $property->set_accessible(true);
                }
                $id = $ea->extract_identifier($om, $object);
                $manager = $this->get_manager('document');
                $class = $mapping['class'];
                $ref_meta = $manager->get_class_metadata($class);
                // Trigger the loading of the configuration to validate the mapping
                $this->get_configuration($manager, $ref_meta->get_name());
                $identifier = $mapping['identifier'];
                $property->set_value($object, new Lazy_Collection(static fn(): \Doctrine\Common\Collections\Array_Collection => new Array_Collection($manager->get_repository($class)->find_by([$identifier => $id]))));
            }
        }
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
    /**
     * @param LifecycleEventArgs $eventArgs
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $eventArgs
     */
    private function update_references(Event_Args $event_args): void
    {
        $ea = $this->get_event_adapter($event_args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        $config = $this->get_configuration($om, $meta->get_name());
        if (isset($config['referenceOne'])) {
            foreach ($config['referenceOne'] as $mapping) {
                if (isset($mapping['identifier'])) {
                    $property = $meta->refl_class->get_property($mapping['field']);
                    if (PHP_VERSION_ID < 80100) {
                        $property->set_accessible(true);
                    }
                    $referenced_object = $property->get_value($object);
                    if (is_object($referenced_object)) {
                        $manager = $this->get_manager($mapping['type']);
                        $identifier = $ea->get_identifier($manager, $referenced_object);
                        $meta->set_field_value($object, $mapping['identifier'], $identifier);
                    }
                }
            }
        }
        $this->update_many_embed_references($event_args);
    }
}