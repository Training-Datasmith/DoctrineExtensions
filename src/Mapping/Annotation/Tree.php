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
 * Tree annotation for Tree behavioral extension
 *
 * @Annotation
 *
 * @NamedArgumentConstructor
 *
 * @Target("CLASS")
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Tree implements Gedmo_Annotation
{
    use Forward_Compatibility_Trait;
    /**
     * @phpstan-var 'closure'|'materializedPath'|'nested'
     */
    public string $type = 'nested';
    public bool $activate_locking = false;
    /**
     * @phpstan-var positive-int
     */
    public int $locking_timeout = 3;
    /**
     * @var string|null
     *
     * @deprecated to be removed in 4.0, unused, configure the property on the TreeRoot annotation instead
     */
    public $identifier_method;
    /**
     * @param array<string, mixed> $data
     *
     * @phpstan-param 'closure'|'materializedPath'|'nested'|null $type
     */
    public function __construct(array $data = [], ?string $type = null, bool $activate_locking = false, int $locking_timeout = 3, ?string $identifier_method = null)
    {
        if ([] !== $data) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2388', 'Passing an array as first argument to "%s()" is deprecated. Use named arguments instead.', __METHOD__);
            $args = func_get_args();
            $this->type = $this->get_attribute_value($data, 'type', $args, 1, $type);
            $this->activate_locking = $this->get_attribute_value($data, 'activateLocking', $args, 2, $activate_locking);
            $this->locking_timeout = $this->get_attribute_value($data, 'lockingTimeout', $args, 3, $locking_timeout);
            $this->identifier_method = $this->get_attribute_value($data, 'identifierMethod', $args, 4, $identifier_method);
            return;
        }
        $this->type = $type;
        $this->activate_locking = $activate_locking;
        $this->locking_timeout = $locking_timeout;
        $this->identifier_method = $identifier_method;
    }
}