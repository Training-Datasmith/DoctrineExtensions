<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable\Mapping;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
/**
 * This class is used to validate mapping information
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Validator
{
    /**
     * List of types which are valid for timestamp
     *
     * @var string[]
     */
    public static $valid_types = ['date', 'date_immutable', 'time', 'time_immutable', 'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'timestamp'];
    /**
     * @param ClassMetadata<object> $meta
     * @param mixed                 $field
     */
    public static function validate_field(Class_Metadata $meta, string $field): void
    {
        if ($meta->is_mapped_superclass) {
            return;
        }
        $field_mapping = $meta->get_field_mapping($field);
        if (!in_array($field_mapping->type ?? $field_mapping['type'], self::$valid_types, true)) {
            throw new Invalid_Mapping_Exception(sprintf('Field "%s" (type "%s") must be of one of the following types: "%s" in entity %s', $field, $field_mapping->type ?? $field_mapping['type'], implode(', ', self::$valid_types), $meta->get_name()));
        }
    }
}