<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\References\Mapping\Driver;

use Gedmo\Mapping\Annotation\Reference;
use Gedmo\Mapping\Annotation\Reference_Many;
use Gedmo\Mapping\Annotation\Reference_Many_Embed;
use Gedmo\Mapping\Annotation\Reference_One;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
/**
 * Mapping driver for the references extension which reads extended metadata from attributes on a class with references.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object declaring a field as having a reference to one object.
     */
    public const REFERENCE_ONE = Reference_One::class;
    /**
     * Mapping object declaring a field as having a reference to many objects.
     */
    public const REFERENCE_MANY = Reference_Many::class;
    /**
     * Mapping object declaring a field as having a reference to an embedded collection of many objects.
     */
    public const REFERENCE_MANY_EMBED = Reference_Many_Embed::class;
    /**
     * @var array<string, self::REFERENCE_ONE|self::REFERENCE_MANY|self::REFERENCE_MANY_EMBED>
     */
    private const ANNOTATIONS = ['referenceOne' => self::REFERENCE_ONE, 'referenceMany' => self::REFERENCE_MANY, 'referenceManyEmbed' => self::REFERENCE_MANY_EMBED];
    public function read_extended_metadata($meta, array &$config): array
    {
        $class = $meta->get_reflection_class();
        foreach (self::ANNOTATIONS as $key => $annotation) {
            $config[$key] = [];
            foreach ($class->get_properties() as $property) {
                if ($meta->is_mapped_superclass && !$property->is_private()) {
                    continue;
                }
                if ($meta->is_inherited_field($property->name)) {
                    continue;
                }
                if (isset($meta->association_mappings[$property->name]['inherited'])) {
                    continue;
                }
                if ($reference = $this->reader->get_property_annotation($property, $annotation)) {
                    \assert($reference instanceof Reference);
                    $config[$key][$property->get_name()] = ['field' => $property->get_name(), 'type' => $reference->type, 'class' => $reference->class, 'identifier' => $reference->identifier, 'mappedBy' => $reference->mapped_by, 'inversedBy' => $reference->inversed_by];
                }
            }
        }
        return $config;
    }
}