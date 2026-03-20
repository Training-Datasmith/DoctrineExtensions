<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Mapping\Annotation;

/**
 * @todo Remove this trait when support for array based attributes is removed.
 *
 * @internal
 */
trait Forward_Compatibility_Trait
{
    /**
     * @param array<string, mixed> $data
     * @param array<int, mixed>    $args
     * @param mixed                $value
     *
     * @return mixed
     */
    private function get_attribute_value(array $data, string $attribute_name, array $args, int $argument_num, $value)
    {
        if (array_key_exists($argument_num, $args)) {
            return $args[$argument_num];
        }
        if (array_key_exists($attribute_name, $data)) {
            return $data[$attribute_name];
        }
        return $value;
    }
}