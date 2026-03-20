<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Mapping;

use Doctrine\Bundle\Doctrine_Bundle\Mapping\Mapping_Driver as DoctrineBundleMappingDriver;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata as DocumentClassMetadata;
use Doctrine\ORM\Mapping\Class_Metadata as EntityClassMetadata;
use Doctrine\ORM\Mapping\Class_Metadata_Info as LegacyEntityClassMetadata;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Mapping\Driver\Default_File_Locator;
use Doctrine\Persistence\Mapping\Driver\Mapping_Driver;
use Doctrine\Persistence\Mapping\Driver\Mapping_Driver_Chain;
use Doctrine\Persistence\Mapping\Driver\Symfony_File_Locator;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\RuntimeException;
use Gedmo\Mapping\Driver\Annotation_Driver_Interface;
use Gedmo\Mapping\Driver\Attribute_Annotation_Reader;
use Gedmo\Mapping\Driver\Attribute_Driver_Interface;
use Gedmo\Mapping\Driver\Attribute_Reader;
use Gedmo\Mapping\Driver\Chain;
use Gedmo\Mapping\Driver\File as FileDriver;
use Psr\Cache\Cache_Item_Pool_Interface;
/**
 * The extension metadata factory is responsible for extension driver
 * initialization and fully reading the extension metadata
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Extension_Metadata_Factory
{
    /**
     * Extension driver
     *
     * @var Driver
     */
    protected $driver;
    /**
     * Object manager, entity or document
     *
     * @var ObjectManager
     */
    protected $object_manager;
    /**
     * Extension namespace
     */
    protected string $extension_namespace;
    /**
     * Metadata annotation reader
     */
    protected ?object $annotation_reader;
    private ?Cache_Item_Pool_Interface $cache_item_pool = null;
    /**
     * @note Providing any object as the third argument is deprecated, as of 4.0 an {@see AttributeReader} will be required
     */
    public function __construct(Object_Manager $object_manager, string $extension_namespace, ?object $annotation_reader = null, ?Cache_Item_Pool_Interface $cache_item_pool = null)
    {
        if (null !== $annotation_reader) {
            if ($annotation_reader instanceof Reader) {
                Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2772', 'Annotations support is deprecated, migrate your application to use attributes and pass an instance of %s to the %s constructor instead.', Attribute_Reader::class, static::class);
            } elseif (!$annotation_reader instanceof Attribute_Reader) {
                Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2258', 'Providing an annotation reader which does not implement %s or is not an instance of %s to %s is deprecated.', Reader::class, Attribute_Reader::class, static::class);
            }
        }
        $this->object_manager = $object_manager;
        $this->annotation_reader = $annotation_reader;
        $this->extension_namespace = $extension_namespace;
        $om_driver = $object_manager->get_configuration()->get_metadata_driver_impl();
        $this->driver = $this->get_driver($om_driver);
        $this->cache_item_pool = $cache_item_pool;
    }
    /**
     * Reads extension metadata
     *
     * @param ClassMetadata<object>&(DocumentClassMetadata<object>|EntityClassMetadata<object>|LegacyEntityClassMetadata<object>) $meta
     *
     * @return array<string, mixed> the metadata configuration
     */
    public function get_extension_metadata($meta): array
    {
        if ($meta->is_mapped_superclass) {
            return [];
            // ignore mappedSuperclasses for now
        }
        $config = [];
        $cmf = $this->object_manager->get_metadata_factory();
        $use_object_name = $meta->get_name();
        // collect metadata from inherited classes
        if (null !== $meta->refl_class) {
            foreach (array_reverse(class_parents($meta->get_name())) as $parent_class) {
                // read only inherited mapped classes
                if ($cmf->has_metadata_for($parent_class) || !$cmf->is_transient($parent_class)) {
                    assert(class_exists($parent_class));
                    $class = $this->object_manager->get_class_metadata($parent_class);
                    assert($class instanceof Document_Class_Metadata || $class instanceof Entity_Class_Metadata || $class instanceof Legacy_Entity_Class_Metadata);
                    $extended_metadata = $this->driver->read_extended_metadata($class, $config);
                    if (\is_array($extended_metadata)) {
                        $config = $extended_metadata;
                    }
                    // @todo: In the next major release remove the assignment to `$extendedMetadata`, the previous conditional
                    // block and uncomment the following line.
                    // $config = $this->driver->readExtendedMetadata($class, $config);
                    $is_base_inheritance_level = !$class->is_inheritance_type_none() && [] === $class->parent_classes && [] !== $config;
                    if ($is_base_inheritance_level) {
                        $use_object_name = $class->get_name();
                    }
                }
            }
            $extended_metadata = $this->driver->read_extended_metadata($meta, $config);
            if (\is_array($extended_metadata)) {
                $config = $extended_metadata;
            }
            // @todo: In the next major release remove the assignment to `$extendedMetadata`, the previous conditional
            // block and uncomment the following line.
            // $config = $this->driver->readExtendedMetadata($meta, $config);
        }
        if ([] !== $config) {
            $config['useObjectClass'] = $use_object_name;
        }
        $this->store_configuration($meta->get_name(), $config);
        return $config;
    }
    /**
     * Get the cache id
     *
     * @param string $className
     * @param string $extensionNamespace
     */
    public static function get_cache_id($class_name, $extension_namespace): string
    {
        return str_replace('\\', '_', $class_name) . '__' . strtoupper(str_replace('\\', '_', $extension_namespace)) . '_CLASSMETADATA';
    }
    /**
     * Get the extended driver instance which will
     * read the metadata required by extension
     *
     * @param MappingDriver $omDriver
     *
     * @throws RuntimeException if driver was not found in extension
     *
     * @return Driver
     */
    protected function get_driver($om_driver): object
    {
        if ($om_driver instanceof Doctrine_Bundle_Mapping_Driver) {
            $om_driver = $om_driver->get_driver();
        }
        $driver = null;
        $class_name = get_class($om_driver);
        $driver_name = substr($class_name, strrpos($class_name, '\\') + 1);
        if ($om_driver instanceof Mapping_Driver_Chain || 'DriverChain' === $driver_name) {
            $driver = new Chain();
            foreach ($om_driver->get_drivers() as $namespace => $nested_om_driver) {
                $driver->add_driver($this->get_driver($nested_om_driver), $namespace);
            }
            if (null !== $om_driver->get_default_driver()) {
                $driver->set_default_driver($this->get_driver($om_driver->get_default_driver()));
            }
        } else {
            $driver_name = substr($driver_name, 0, strpos($driver_name, 'Driver'));
            $is_simplified = false;
            if ('Simplified' === substr($driver_name, 0, 10)) {
                // support for simplified file drivers
                $driver_name = substr($driver_name, 10);
                $is_simplified = true;
            }
            // create driver instance
            $driver_class_name = $this->extension_namespace . '\Mapping\Driver\\' . $driver_name;
            if (!class_exists($driver_class_name)) {
                $original_driver_class_name = $driver_class_name;
                // try to fall back to either an annotation or attribute driver depending on the available dependencies
                if (interface_exists(Reader::class)) {
                    $driver_class_name = $this->extension_namespace . '\Mapping\Driver\Annotation';
                } elseif (\PHP_VERSION_ID >= 80000) {
                    $driver_class_name = $this->extension_namespace . '\Mapping\Driver\Attribute';
                }
                if (!class_exists($driver_class_name)) {
                    if ($original_driver_class_name !== $driver_class_name) {
                        throw new RuntimeException("Failed to create mapping driver: ({$original_driver_class_name}), the extension driver nor a fallback annotation or attribute driver could be found.");
                    }
                    throw new RuntimeException("Failed to fallback to annotation driver: ({$driver_class_name}), extension driver was not found.");
                }
            }
            $driver = new $driver_class_name();
            $driver->set_original_driver($om_driver);
            if ($driver instanceof File_Driver) {
                if ($om_driver instanceof Mapping_Driver) {
                    $driver->set_locator($om_driver->get_locator());
                    // BC for Doctrine 2.2
                } elseif ($is_simplified) {
                    $driver->set_locator(new Symfony_File_Locator($om_driver->get_namespace_prefixes(), $om_driver->get_file_extension()));
                } else {
                    $driver->set_locator(new Default_File_Locator($om_driver->get_paths(), $om_driver->get_file_extension()));
                }
            }
            if ($driver instanceof Attribute_Driver_Interface) {
                if (null === $this->annotation_reader) {
                    throw new RuntimeException("Cannot use metadata driver ({$driver_class_name}), an annotation or attribute reader was not provided.");
                }
                if ($driver instanceof Annotation_Driver_Interface) {
                    $driver->set_annotation_reader($this->annotation_reader);
                } else if ($this->annotation_reader instanceof Attribute_Reader) {
                    $driver->set_annotation_reader($this->annotation_reader);
                } else {
                    $driver->set_annotation_reader(new Attribute_Annotation_Reader(new Attribute_Reader(), $this->annotation_reader));
                }
            }
        }
        return $driver;
    }
    /**
     * @param array<string, mixed> $config
     */
    private function store_configuration(string $class_name, array $config): void
    {
        if (null === $this->cache_item_pool) {
            return;
        }
        // Cache the result, even if it's empty, to prevent re-parsing non-existent annotations.
        $cache_id = self::get_cache_id($class_name, $this->extension_namespace);
        $item = $this->cache_item_pool->get_item($cache_id);
        $this->cache_item_pool->save($item->set($config));
    }
}