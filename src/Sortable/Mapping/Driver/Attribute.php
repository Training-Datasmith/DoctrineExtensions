<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sortable\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Annotation\Sortable_Group;
use Gedmo\Mapping\Annotation\Sortable_Position;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
/**
 * Mapping driver for the sortable extension which reads extended metadata from attributes on a sortable class.
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object to mark a field as the one which will store the node position on a sortable object.
     */
    public const POSITION = Sortable_Position::class;
    /**
     * Mapping object to mark a field as part of a sorting group for a sortable object.
     */
    public const GROUP = Sortable_Group::class;
    /**
     * List of types which are valid for position fields
     *
     * @var string[]
     */
    protected $valid_types = ['int', 'integer', 'smallint', 'bigint'];
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
            // position
            if ($this->reader->get_property_annotation($property, self::POSITION)) {
                $field = $property->get_name();
                if (!$meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find 'position' - [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                if (!$this->is_valid_field($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Sortable position field - [{$field}] type is not valid and must be 'integer' in class - {$meta->get_name()}");
                }
                $config['position'] = $field;
            }
            // group
            if ($this->reader->get_property_annotation($property, self::GROUP)) {
                $field = $property->get_name();
                if (!$meta->has_field($field) && !$meta->has_association($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find 'group' - [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                $config['groups'] ??= [];
                $config['groups'][] = $field;
            }
        }
        if (!$meta->is_mapped_superclass && $config) {
            if (!isset($config['position'])) {
                throw new Invalid_Mapping_Exception("Missing property: 'position' in class - {$meta->get_name()}");
            }
        }
        return $config;
    }
}