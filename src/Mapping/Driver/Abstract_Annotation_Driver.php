<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Mapping\Driver;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Deprecations\Deprecation;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Mapping\Driver\Mapping_Driver;
/**
 * This is an abstract class to implement common functionality
 * for extension annotation mapping drivers.
 *
 * @author Derek J. Lambert <dlambert@dereklambert.com>
 */
abstract class Abstract_Annotation_Driver implements Attribute_Driver_Interface
{
    /**
     * Annotation reader instance
     *
     * @var Reader|AttributeReader|object
     *
     * @todo Remove the support for the `object` type in the next major release.
     */
    protected $reader;
    /**
     * Original driver if it is available
     *
     * @var MappingDriver
     */
    protected $_original_driver;
    /**
     * List of types which are valid for extension
     *
     * @var string[]
     */
    protected $valid_types = [];
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
        $this->reader = $reader;
    }
    /**
     * Passes in the mapping read by original driver
     *
     * @param MappingDriver $driver
     */
    public function set_original_driver($driver): void
    {
        $this->_original_driver = $driver;
    }
    /**
     * @param ClassMetadata<object> $meta
     *
     * @return \ReflectionClass<covariant object>
     */
    public function get_meta_reflection_class($meta)
    {
        return $meta->get_reflection_class();
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     *
     * @return void
     */
    public function validate_full_metadata(Class_Metadata $meta, array $config)
    {
    }
    /**
     * Checks if $field type is valid
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     *
     * @return bool
     */
    protected function is_valid_field($meta, $field)
    {
        $mapping = $meta->get_field_mapping($field);
        return $mapping && in_array($mapping->type ?? $mapping['type'], $this->valid_types, true);
    }
    /**
     * Try to find out related class name out of mapping
     *
     * @param ClassMetadata<object> $metadata the mapped class metadata
     * @param string                $name     the related object class name
     *
     * @phpstan-param class-string|string $name
     *
     * @return string related class name or empty string if does not exist
     *
     * @phpstan-return class-string|''
     */
    protected function get_related_class_name($metadata, string $name)
    {
        if (class_exists($name) || interface_exists($name)) {
            return $name;
        }
        $refl = $metadata->get_reflection_class();
        $ns = $refl->get_namespace_name();
        $class_name = $ns . '\\' . $name;
        return class_exists($class_name) ? $class_name : '';
    }
}