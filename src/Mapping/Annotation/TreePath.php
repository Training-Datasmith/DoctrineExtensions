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
 * TreePath annotation for Tree behavioral extension
 *
 * @Annotation
 *
 * @NamedArgumentConstructor
 *
 * @Target("PROPERTY")
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author <rocco@roccosportal.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Tree_Path implements Gedmo_Annotation
{
    use Forward_Compatibility_Trait;
    public string $separator = ',';
    /** @var bool|null */
    public $append_id;
    public bool $starts_with_separator = false;
    public bool $ends_with_separator = true;
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [], string $separator = ',', ?bool $append_id = null, bool $starts_with_separator = false, bool $ends_with_separator = true)
    {
        if ([] !== $data) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2388', 'Passing an array as first argument to "%s()" is deprecated. Use named arguments instead.', __METHOD__);
            $args = func_get_args();
            $this->separator = $this->get_attribute_value($data, 'separator', $args, 1, $separator);
            $this->append_id = $this->get_attribute_value($data, 'appendId', $args, 2, $append_id);
            $this->starts_with_separator = $this->get_attribute_value($data, 'startsWithSeparator', $args, 3, $starts_with_separator);
            $this->ends_with_separator = $this->get_attribute_value($data, 'endsWithSeparator', $args, 4, $ends_with_separator);
            return;
        }
        $this->separator = $separator;
        $this->append_id = $append_id;
        $this->starts_with_separator = $starts_with_separator;
        $this->ends_with_separator = $ends_with_separator;
    }
}