<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Blameable\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Annotation\Blameable;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
/**
 * Mapping driver for the blameable extension which reads extended metadata from attributes on a blameable class.
 *
 * @author David Buchmann <mail@davidbu.ch>
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object for the blameable extension.
     */
    public const BLAMEABLE = Blameable::class;
    /**
     * List of types which are valid for blame
     *
     * @var string[]
     */
    protected $valid_types = ['one', 'string', 'int', 'ulid', 'uuid', 'ascii_string'];
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
            if ($blameable = $this->reader->get_property_annotation($property, self::BLAMEABLE)) {
                \assert($blameable instanceof Blameable);
                $field = $property->get_name();
                if (!$meta->has_field($field) && !$meta->has_association($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find blameable [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                if ($meta->has_field($field)) {
                    if (!$this->is_valid_field($meta, $field)) {
                        throw new Invalid_Mapping_Exception("Field - [{$field}] type is not valid and must be 'string' or a one-to-many relation in class - {$meta->get_name()}");
                    }
                } else if (!$meta->is_single_valued_association($field)) {
                    throw new Invalid_Mapping_Exception("Association - [{$field}] is not valid, it must be a one-to-many relation or a string field - {$meta->get_name()}");
                }
                if (!in_array($blameable->on, ['update', 'create', 'change'], true)) {
                    throw new Invalid_Mapping_Exception("Field - [{$field}] trigger 'on' is not one of [update, create, change] in class - {$meta->get_name()}");
                }
                if ('change' === $blameable->on) {
                    if (!isset($blameable->field)) {
                        throw new Invalid_Mapping_Exception("Missing parameters on property - {$field}, field must be set on [change] trigger in class - {$meta->get_name()}");
                    }
                    if (is_array($blameable->field) && isset($blameable->value)) {
                        throw new Invalid_Mapping_Exception('Blameable extension does not support multiple value changeset detection yet.');
                    }
                    $field = ['field' => $field, 'trackedField' => $blameable->field, 'value' => $blameable->value];
                }
                // properties are unique and mapper checks that, no risk here
                $config[$blameable->on][] = $field;
            }
        }
        return $config;
    }
}