<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Annotation\Soft_Deleteable;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
use Gedmo\Soft_Deleteable\Mapping\Validator;
/**
 * Mapping driver for the soft-deletable extension which reads extended metadata from attributes on a soft-deletable class.
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object for the soft-deletable extension.
     */
    public const SOFT_DELETEABLE = Soft_Deleteable::class;
    public function read_extended_metadata($meta, array &$config): array
    {
        $class = $this->get_meta_reflection_class($meta);
        // class annotations
        if (null !== $class && $annot = $this->reader->get_class_annotation($class, self::SOFT_DELETEABLE)) {
            \assert($annot instanceof Soft_Deleteable);
            $config['softDeleteable'] = true;
            Validator::validate_field($meta, $annot->field_name);
            $config['fieldName'] = $annot->field_name;
            $config['timeAware'] = false;
            if (isset($annot->time_aware)) {
                if (!is_bool($annot->time_aware)) {
                    throw new Invalid_Mapping_Exception('timeAware must be boolean. ' . gettype($annot->time_aware) . ' provided.');
                }
                $config['timeAware'] = $annot->time_aware;
            }
            $config['hardDelete'] = true;
            if (isset($annot->hard_delete)) {
                if (!is_bool($annot->hard_delete)) {
                    throw new Invalid_Mapping_Exception('hardDelete must be boolean. ' . gettype($annot->hard_delete) . ' provided.');
                }
                $config['hardDelete'] = $annot->hard_delete;
            }
        }
        $this->validate_full_metadata($meta, $config);
        return $config;
    }
}