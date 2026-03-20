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
use Gedmo\Mapping\Annotation\Annotation;
/**
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 *
 * @deprecated since gedmo/doctrine-extensions 3.16, will be removed in version 4.0.
 *
 * @internal
 */
final class Attribute_Annotation_Reader implements Reader
{
    private Reader $annotation_reader;
    private Attribute_Reader $attribute_reader;
    public function __construct(Attribute_Reader $attribute_reader, Reader $annotation_reader)
    {
        $this->attribute_reader = $attribute_reader;
        $this->annotation_reader = $annotation_reader;
    }
    /**
     * @phpstan-param \ReflectionClass<object> $class
     *
     * @return Annotation[]
     */
    public function get_class_annotations(\ReflectionClass $class): array
    {
        $annotations = $this->attribute_reader->get_class_annotations($class);
        if ([] !== $annotations) {
            return $annotations;
        }
        return $this->annotation_reader->get_class_annotations($class);
    }
    /**
     *
     * @phpstan-param \ReflectionClass<object> $class
     * @phpstan-param class-string<T> $annotationName the name of the annotation
     *
     * @return T|null the Annotation or NULL, if the requested annotation does not exist
     * @template T
     */
    public function get_class_annotation(\ReflectionClass $class, string $annotation_name)
    {
        $annotation = $this->attribute_reader->get_class_annotation($class, $annotation_name);
        return $annotation ?? $this->annotation_reader->get_class_annotation($class, $annotation_name);
    }
    /**
     * @return Annotation[]
     */
    public function get_property_annotations(\ReflectionProperty $property): array
    {
        $property_annotations = $this->attribute_reader->get_property_annotations($property);
        if ([] !== $property_annotations) {
            return $property_annotations;
        }
        return $this->annotation_reader->get_property_annotations($property);
    }
    /**
     * @param class-string<T> $annotationName the name of the annotation
     *
     * @return T|null the Annotation or NULL, if the requested annotation does not exist
     *
     * @template T
     */
    public function get_property_annotation(\ReflectionProperty $property, string $annotation_name)
    {
        $annotation = $this->attribute_reader->get_property_annotation($property, $annotation_name);
        return $annotation ?? $this->annotation_reader->get_property_annotation($property, $annotation_name);
    }
    public function get_method_annotations(\ReflectionMethod $method): array
    {
        throw new \BadMethodCallException('Not implemented');
    }
    public function get_method_annotation(\ReflectionMethod $method, $annotation_name): void
    {
        throw new \BadMethodCallException('Not implemented');
    }
}