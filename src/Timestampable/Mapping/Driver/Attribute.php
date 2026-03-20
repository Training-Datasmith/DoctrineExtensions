<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Timestampable\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Annotation\Timestampable;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
/**
 * Mapping driver for the timestampable extension which reads extended metadata from attributes on a timestampable class.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Kevin Mian Kraiker <kevin.mian@gmail.com>
 *
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object for the timestampable extension.
     */
    public const TIMESTAMPABLE = Timestampable::class;
    /**
     * List of types which are valid for timestamp
     *
     * @var string[]
     */
    protected $valid_types = ['date', 'date_immutable', 'time', 'time_immutable', 'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'timestamp', 'vardatetime', 'integer'];
    public function read_extended_metadata($meta, array &$config): array
    {
        $class = $this->get_meta_reflection_class($meta);
        // property annotations
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
            if ($timestampable = $this->reader->get_property_annotation($property, self::TIMESTAMPABLE)) {
                \assert($timestampable instanceof Timestampable);
                $field = $property->get_name();
                if (!$meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find timestampable [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                if (!$this->is_valid_field($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Field - [{$field}] type is not valid and must be 'date', 'datetime' or 'time' in class - {$meta->get_name()}");
                }
                if (!in_array($timestampable->on, ['update', 'create', 'change'], true)) {
                    throw new Invalid_Mapping_Exception("Field - [{$field}] trigger 'on' is not one of [update, create, change] in class - {$meta->get_name()}");
                }
                if ('change' === $timestampable->on) {
                    if (!isset($timestampable->field)) {
                        throw new Invalid_Mapping_Exception("Missing parameters on property - {$field}, field must be set on [change] trigger in class - {$meta->get_name()}");
                    }
                    if (is_array($timestampable->field) && isset($timestampable->value)) {
                        throw new Invalid_Mapping_Exception('Timestampable extension does not support multiple value changeset detection yet.');
                    }
                    $field = ['field' => $field, 'trackedField' => $timestampable->field, 'value' => $timestampable->value];
                }
                // properties are unique and mapper checks that, no risk here
                $config[$timestampable->on][] = $field;
            }
        }
        return $config;
    }
}