<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Mapping;

use Doctrine\Common\Annotations\Annotation_Reader;
use Doctrine\Common\Annotations\Psr_Cached_Reader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Event_Args;
use Doctrine\Common\Event_Subscriber;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata as DocumentClassMetadata;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Mapping\Class_Metadata as EntityClassMetadata;
use Doctrine\ORM\Mapping\Class_Metadata_Info as LegacyEntityClassMetadata;
use Doctrine\Persistence\Mapping\Abstract_Class_Metadata_Factory;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Mapping\Driver\Attribute_Reader;
use Gedmo\Mapping\Event\Adapter_Interface;
use Gedmo\Mapping\Event\Clock_Aware_Adapter_Interface;
use Psr\Cache\Cache_Item_Pool_Interface;
use Psr\Clock\Clock_Interface;
use Symfony\Component\Cache\Adapter\Array_Adapter;
/**
 * This is extension of event subscriber class and is
 * used specifically for handling the extension metadata
 * mapping for extensions.
 *
 * It dries up some reusable code which is common for
 * all extensions who maps additional metadata through
 * extended drivers
 *
 * @phpstan-template TConfig of array
 * @phpstan-template TEventAdapter of AdapterInterface
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
abstract class Mapped_Event_Subscriber implements Event_Subscriber
{
    /**
     * Static List of cached object configurations
     * leaving it static for reasons to look into
     * other listener configuration
     *
     * @var array<string, array<string, array<string, mixed>>>
     *
     * @phpstan-var array<string, array<class-string, array<string, mixed>>>
     */
    protected static $configurations = [];
    /**
     * Listener name, etc: sluggable
     */
    protected string $name;
    /**
     * ExtensionMetadataFactory used to read the extension
     * metadata through the extension drivers
     *
     * @var array<int, ExtensionMetadataFactory>
     */
    private array $extension_metadata_factory = [];
    /**
     * List of event adapters used for this listener
     *
     * @var array<string, AdapterInterface>
     */
    private array $adapters = [];
    /**
     * Custom annotation reader
     *
     * @var Reader|AttributeReader|object|false|null
     */
    private $annotation_reader = false;
    /**
     * @var Reader|AttributeReader|false|null
     */
    private static $default_annotation_reader = false;
    /**
     * @var CacheItemPoolInterface|null
     */
    private $cache_item_pool;
    private ?Clock_Interface $clock = null;
    public function __construct()
    {
        $parts = explode('\\', $this->get_namespace());
        $this->name = end($parts);
    }
    /**
     * Get the configuration for specific object class
     * if cache driver is present it scans it also
     *
     * @param string $class
     *
     * @phpstan-param class-string $class
     *
     * @return array<string, mixed>
     *
     * @phpstan-return TConfig
     */
    public function get_configuration(Object_Manager $object_manager, $class)
    {
        if (isset(self::$configurations[$this->name][$class])) {
            return self::$configurations[$this->name][$class];
        }
        $config = [];
        $cache_item_pool = $this->get_cache_item_pool($object_manager);
        $cache_id = Extension_Metadata_Factory::get_cache_id($class, $this->get_namespace());
        $cache_item = $cache_item_pool->get_item($cache_id);
        if ($cache_item->is_hit()) {
            $config = $cache_item->get();
            self::$configurations[$this->name][$class] = $config;
        } else {
            // re-generate metadata on cache miss
            $this->load_metadata_for_object_class($object_manager, $object_manager->get_class_metadata($class));
            if (isset(self::$configurations[$this->name][$class])) {
                $config = self::$configurations[$this->name][$class];
            }
        }
        $object_class = $config['useObjectClass'] ?? $class;
        if ($object_class !== $class) {
            $this->get_configuration($object_manager, $object_class);
        }
        return $config;
    }
    /**
     * Get extended metadata mapping reader
     *
     * @return ExtensionMetadataFactory
     */
    public function get_extension_metadata_factory(Object_Manager $object_manager)
    {
        $oid = spl_object_id($object_manager);
        if (!isset($this->extension_metadata_factory[$oid])) {
            if (false === $this->annotation_reader) {
                // create default annotation/attribute reader for extensions
                $this->annotation_reader = $this->get_default_annotation_reader();
            }
            $this->extension_metadata_factory[$oid] = new Extension_Metadata_Factory($object_manager, $this->get_namespace(), $this->annotation_reader, $this->get_cache_item_pool($object_manager));
        }
        return $this->extension_metadata_factory[$oid];
    }
    /**
     * Set the annotation reader instance
     *
     * When originally implemented, `Doctrine\Common\Annotations\Reader` was not available,
     * therefore this method may accept any object implementing these methods from the interface:
     *
     *     getClassAnnotations([reflectionClass])
     *     getClassAnnotation([reflectionClass], [name])
     *     getPropertyAnnotations([reflectionProperty])
     *     getPropertyAnnotation([reflectionProperty], [name])
     *
     * @param Reader|AttributeReader|object $reader
     *
     *
     * @note Providing any object is deprecated, as of 4.0 an {@see AttributeReader} will be required
     */
    public function set_annotation_reader($reader): void
    {
        if ($reader instanceof Reader) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2772', 'Annotations support is deprecated, migrate your application to use attributes and pass an instance of %s to the %s() method instead.', Attribute_Reader::class, __METHOD__);
        } elseif (!$reader instanceof Attribute_Reader) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2558', 'Providing an annotation reader which does not implement %s or is not an instance of %s to %s() is deprecated.', Reader::class, Attribute_Reader::class, __METHOD__);
        }
        $this->annotation_reader = $reader;
    }
    final public function set_cache_item_pool(Cache_Item_Pool_Interface $cache_item_pool): void
    {
        $this->cache_item_pool = $cache_item_pool;
    }
    final public function set_clock(Clock_Interface $clock): void
    {
        $this->clock = $clock;
    }
    /**
     * Scans the objects for extended annotations
     * event subscribers must subscribe to loadClassMetadata event
     *
     * @param ClassMetadata<object> $metadata
     */
    public function load_metadata_for_object_class(Object_Manager $object_manager, $metadata): void
    {
        assert($metadata instanceof Document_Class_Metadata || $metadata instanceof Entity_Class_Metadata || $metadata instanceof Legacy_Entity_Class_Metadata);
        $factory = $this->get_extension_metadata_factory($object_manager);
        try {
            $config = $factory->get_extension_metadata($metadata);
        } catch (\Reflection_Exception $e) {
            // entity\document generator is running
            $config = [];
            // will not store a cached version, to remap later
        }
        if ([] !== $config) {
            self::$configurations[$this->name][$metadata->get_name()] = $config;
        }
    }
    /**
     * Get an event adapter to handle event specific
     * methods
     *
     * @throws InvalidArgumentException if event is not recognized
     *
     * @return AdapterInterface
     *
     * @phpstan-return TEventAdapter
     */
    protected function get_event_adapter(Event_Args $args)
    {
        $class = get_class($args);
        if (preg_match('@Doctrine\\\\([^\\\\]+)@', $class, $m) && in_array($m[1], ['ODM', 'ORM'], true)) {
            if (!isset($this->adapters[$m[1]])) {
                $adapter_class = $this->get_namespace() . '\Mapping\Event\Adapter\\' . $m[1];
                if (!\class_exists($adapter_class)) {
                    $adapter_class = 'Gedmo\Mapping\Event\Adapter\\' . $m[1];
                }
                $this->adapters[$m[1]] = new $adapter_class();
                if ($this->adapters[$m[1]] instanceof Clock_Aware_Adapter_Interface && $this->clock instanceof Clock_Interface) {
                    $this->adapters[$m[1]]->set_clock($this->clock);
                }
            }
            $this->adapters[$m[1]]->set_event_args($args);
            return $this->adapters[$m[1]];
        }
        throw new InvalidArgumentException('Event mapper does not support event arg class: ' . $class);
    }
    /**
     * Get the namespace of extension event subscriber.
     * used for cache id of extensions also to know where
     * to find Mapping drivers and event adapters
     *
     * @return string
     */
    abstract protected function get_namespace();
    /**
     * Sets the value for a mapped field
     *
     * @param object $object
     * @param string $field
     * @param mixed  $oldValue
     * @param mixed  $newValue
     *
     * @return void
     */
    protected function set_field_value(Adapter_Interface $adapter, $object, $field, $old_value, $new_value)
    {
        $manager = $adapter->get_object_manager();
        $meta = $manager->get_class_metadata(get_class($object));
        $uow = $manager->get_unit_of_work();
        $meta->set_field_value($object, $field, $new_value);
        $uow->property_changed($object, $field, $old_value, $new_value);
        $adapter->recompute_single_object_change_set($uow, $meta, $object);
    }
    /**
     * Get the default annotation or attribute reader for extensions, creating it if necessary.
     *
     * If a reader cannot be created due to missing requirements, no default will be set as the reader is only required for annotation or attribute metadata,
     * and the {@see ExtensionMetadataFactory} can handle raising an error if it tries to create a mapping driver that requires this reader.
     *
     * @return Reader|AttributeReader|null
     */
    private function get_default_annotation_reader()
    {
        if (false === self::$default_annotation_reader) {
            if (class_exists(Psr_Cached_Reader::class)) {
                self::$default_annotation_reader = new Psr_Cached_Reader(new Annotation_Reader(), new Array_Adapter());
            } elseif (\PHP_VERSION_ID >= 80000) {
                self::$default_annotation_reader = new Attribute_Reader();
            } else {
                self::$default_annotation_reader = null;
            }
        }
        return self::$default_annotation_reader;
    }
    private function get_cache_item_pool(Object_Manager $object_manager): Cache_Item_Pool_Interface
    {
        if (null !== $this->cache_item_pool) {
            return $this->cache_item_pool;
        }
        // TODO: The user should configure its own cache, we are using the one from Doctrine for BC. We should deprecate using
        // the one from Doctrine when the bundle offers an easy way to configure this cache, otherwise users using the bundle
        // will see lots of deprecations without an easy way to avoid them.
        if ($object_manager instanceof Entity_Manager_Interface || $object_manager instanceof Document_Manager) {
            $metadata_factory = $object_manager->get_metadata_factory();
            $get_cache = \Closure::bind(static fn(Abstract_Class_Metadata_Factory $metadata_factory): ?Cache_Item_Pool_Interface => $metadata_factory->get_cache(), null, \get_class($metadata_factory));
            $metadata_cache = $get_cache($metadata_factory);
            if (null !== $metadata_cache) {
                $this->cache_item_pool = $metadata_cache;
                return $this->cache_item_pool;
            }
        }
        $this->cache_item_pool = new Array_Adapter();
        return $this->cache_item_pool;
    }
}