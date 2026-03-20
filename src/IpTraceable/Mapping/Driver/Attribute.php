<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Ip_Traceable\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Annotation\Ip_Traceable;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
/**
 * Mapping driver for the IP traceable extension which reads extended metadata from attributes on an IP traceable class.
 *
 * @author Pierre-Charles Bertineau <pc.bertineau@alterphp.com>
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object for the IP traceable extension.
     */
    public const IP_TRACEABLE = Ip_Traceable::class;
    /**
     * List of types which are valid for IP
     *
     * @var string[]
     */
    protected $valid_types = ['string', 'ascii_string'];
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
            if ($ip_traceable = $this->reader->get_property_annotation($property, self::IP_TRACEABLE)) {
                \assert($ip_traceable instanceof Ip_Traceable);
                $field = $property->get_name();
                if (!$meta->has_field($field)) {
                    throw new Invalid_Mapping_Exception("Unable to find ipTraceable [{$field}] as mapped property in entity - {$meta->get_name()}");
                }
                if (!$this->is_valid_field($meta, $field)) {
                    throw new Invalid_Mapping_Exception("Field - [{$field}] type is not valid and must be 'string' - {$meta->get_name()}");
                }
                if (!in_array($ip_traceable->on, ['update', 'create', 'change'], true)) {
                    throw new Invalid_Mapping_Exception("Field - [{$field}] trigger 'on' is not one of [update, create, change] in class - {$meta->get_name()}");
                }
                if ('change' === $ip_traceable->on) {
                    if (!isset($ip_traceable->field)) {
                        throw new Invalid_Mapping_Exception("Missing parameters on property - {$field}, field must be set on [change] trigger in class - {$meta->get_name()}");
                    }
                    if (is_array($ip_traceable->field) && isset($ip_traceable->value)) {
                        throw new Invalid_Mapping_Exception('IpTraceable extension does not support multiple value changeset detection yet.');
                    }
                    $field = ['field' => $field, 'trackedField' => $ip_traceable->field, 'value' => $ip_traceable->value];
                }
                // properties are unique and mapper checks that, no risk here
                $config[$ip_traceable->on][] = $field;
            }
        }
        return $config;
    }
}