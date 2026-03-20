<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Mapping\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Deprecations\Deprecation;
use Gedmo\Mapping\Annotation\Annotation as GedmoAnnotation;
/**
 * Group annotation for SoftDeleteable extension
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 *
 * @Annotation
 *
 * @NamedArgumentConstructor
 *
 * @Target("CLASS")
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Soft_Deleteable implements Gedmo_Annotation
{
    use Forward_Compatibility_Trait;
    public string $field_name = 'deletedAt';
    public bool $time_aware = false;
    public bool $hard_delete = true;
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [], string $field_name = 'deletedAt', bool $time_aware = false, bool $hard_delete = true)
    {
        if ([] !== $data) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2374', 'Passing an array as first argument to "%s()" is deprecated. Use named arguments instead.', __METHOD__);
            $args = func_get_args();
            $this->field_name = $this->get_attribute_value($data, 'fieldName', $args, 1, $field_name);
            $this->time_aware = $this->get_attribute_value($data, 'timeAware', $args, 2, $time_aware);
            $this->hard_delete = $this->get_attribute_value($data, 'hardDelete', $args, 3, $hard_delete);
            return;
        }
        $this->field_name = $field_name;
        $this->time_aware = $time_aware;
        $this->hard_delete = $hard_delete;
    }
}