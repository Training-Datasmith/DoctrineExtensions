<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Reference_Integrity\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Annotation\Reference_Integrity;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
use Gedmo\Reference_Integrity\Mapping\Validator;
/**
 * Mapping driver for the reference integrity extension which reads extended metadata from attributes on a class with referential integrity.
 *
 * @author Evert Harmeling <evert.harmeling@freshheads.com>
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object for the reference integrity extension.
     */
    public const REFERENCE_INTEGRITY = Reference_Integrity::class;
    /**
     * Unimplemented mapping object for actions within the reference integrity extension.
     *
     * @deprecated since gedmo/doctrine-extensions 3.16, will be removed in version 4.0; actions are defined as {@see Validator} class constants instead.
     */
    public const ACTION = 'Gedmo\Mapping\Annotation\ReferenceIntegrityAction';
    public function read_extended_metadata($meta, array &$config): array
    {
        $validator = new Validator();
        $refl_class = $this->get_meta_reflection_class($meta);
        foreach ($refl_class->get_properties() as $refl_property) {
            if ($reference_integrity = $this->reader->get_property_annotation($refl_property, self::REFERENCE_INTEGRITY)) {
                \assert($reference_integrity instanceof Reference_Integrity);
                $property = $refl_property->get_name();
                if (!$meta->has_field($property)) {
                    throw new Invalid_Mapping_Exception(sprintf('Unable to find reference integrity [%s] as mapped property in entity - %s', $property, $meta->get_name()));
                }
                $field_mapping = $meta->get_field_mapping($property);
                if (!isset($field_mapping['mappedBy'])) {
                    throw new Invalid_Mapping_Exception(sprintf("'mappedBy' should be set on '%s' in '%s'", $property, $meta->get_name()));
                }
                if (!in_array($reference_integrity->value, $validator->get_integrity_actions(), true)) {
                    throw new Invalid_Mapping_Exception(sprintf('Field - [%s] does not have a valid integrity option, [%s] in class - %s', $property, implode(', ', $validator->get_integrity_actions()), $meta->get_name()));
                }
                $config['referenceIntegrity'][$property] = $reference_integrity->value;
            }
        }
        return $config;
    }
}