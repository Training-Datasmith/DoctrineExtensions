<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Mapping\Driver;

use Gedmo\Mapping\Annotation\Annotation;
/**
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
final class Attribute_Reader
{
    /** @var array<string,bool> */
    private array $is_repeatable_attribute = [];
    /**
     * @phpstan-param \ReflectionClass<object> $class
     *
     * @return array<Annotation|Annotation[]>
     */
    public function get_class_annotations(\ReflectionClass $class): array
    {
        return $this->convert_to_attribute_instances($class->get_attributes());
    }
    /**
     * @phpstan-param \ReflectionClass<object> $class
     * @phpstan-param class-string $annotationName
     *
     * @return Annotation|Annotation[]|null
     */
    public function get_class_annotation(\ReflectionClass $class, string $annotation_name)
    {
        return $this->get_class_annotations($class)[$annotation_name] ?? null;
    }
    /**
     * @return array<Annotation|Annotation[]>
     */
    public function get_property_annotations(\ReflectionProperty $property): array
    {
        return $this->convert_to_attribute_instances($property->get_attributes());
    }
    /**
     * @phpstan-param class-string $annotationName
     *
     * @return Annotation|Annotation[]|null
     */
    public function get_property_annotation(\ReflectionProperty $property, string $annotation_name)
    {
        return $this->get_property_annotations($property)[$annotation_name] ?? null;
    }
    /**
     * @param iterable<\ReflectionAttribute> $attributes
     *
     * @phpstan-param iterable<\ReflectionAttribute<object>> $attributes
     *
     * @return array<string, Annotation|Annotation[]>
     */
    private function convert_to_attribute_instances(iterable $attributes): array
    {
        $instances = [];
        foreach ($attributes as $attribute) {
            $attribute_name = $attribute->get_name();
            assert(is_string($attribute_name));
            // Make sure we only get Gedmo Annotations
            if (!is_subclass_of($attribute_name, Annotation::class)) {
                continue;
            }
            $instance = $attribute->new_instance();
            assert($instance instanceof Annotation);
            if ($this->is_repeatable($attribute_name)) {
                if (!isset($instances[$attribute_name])) {
                    $instances[$attribute_name] = [];
                }
                $instances[$attribute_name][] = $instance;
            } else {
                $instances[$attribute_name] = $instance;
            }
        }
        return $instances;
    }
    private function is_repeatable(string $attribute_class_name): bool
    {
        if (isset($this->is_repeatable_attribute[$attribute_class_name])) {
            return $this->is_repeatable_attribute[$attribute_class_name];
        }
        $reflection_class = new \ReflectionClass($attribute_class_name);
        $attribute = $reflection_class->get_attributes()[0]->new_instance();
        return $this->is_repeatable_attribute[$attribute_class_name] = ($attribute->flags & \Attribute::IS_REPEATABLE) > 0;
    }
}