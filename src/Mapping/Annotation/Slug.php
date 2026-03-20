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
 * Slug annotation for Sluggable behavioral extension
 *
 * @Annotation
 *
 * @NamedArgumentConstructor
 *
 * @Target("PROPERTY")
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Slug implements Gedmo_Annotation
{
    use Forward_Compatibility_Trait;
    /**
     * @var string[]
     *
     * @Required
     */
    public $fields = [];
    public bool $updatable = true;
    public string $style = 'default';
    // or "camel"
    public bool $unique = true;
    public bool $unique_over_translations = false;
    /** @var string|null */
    public $unique_base;
    public string $separator = '-';
    public string $prefix = '';
    public string $suffix = '';
    /**
     * @var SlugHandler[]
     *
     * @deprecated since gedmo/doctrine-extensions 3.18
     */
    public $handlers = [];
    public string $date_format = 'Y-m-d-H:i';
    /**
     * @param array<string, mixed> $data
     * @param string[]             $fields
     * @param SlugHandler[]        $handlers @deprecated since since gedmo/doctrine-extensions 3.18
     */
    public function __construct(array $data = [], array $fields = [], bool $updatable = true, string $style = 'default', bool $unique = true, ?string $unique_base = null, string $separator = '-', string $prefix = '', string $suffix = '', array $handlers = [], string $date_format = 'Y-m-d-H:i', bool $unique_over_translations = false)
    {
        if ([] !== $data) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2379', 'Passing an array as first argument to "%s()" is deprecated. Use named arguments instead.', __METHOD__);
            $args = func_get_args();
            $this->fields = $this->get_attribute_value($data, 'fields', $args, 1, $fields);
            $this->updatable = $this->get_attribute_value($data, 'updatable', $args, 2, $updatable);
            $this->style = $this->get_attribute_value($data, 'style', $args, 3, $style);
            $this->unique = $this->get_attribute_value($data, 'unique', $args, 4, $unique);
            $this->unique_base = $this->get_attribute_value($data, 'unique_base', $args, 5, $unique_base);
            $this->separator = $this->get_attribute_value($data, 'separator', $args, 6, $separator);
            $this->prefix = $this->get_attribute_value($data, 'prefix', $args, 7, $prefix);
            $this->suffix = $this->get_attribute_value($data, 'suffix', $args, 8, $suffix);
            $this->handlers = $this->get_attribute_value($data, 'handlers', $args, 9, $handlers);
            $this->date_format = $this->get_attribute_value($data, 'dateFormat', $args, 10, $date_format);
            $this->unique_over_translations = $this->get_attribute_value($data, 'uniqueOverTranslations', $args, 11, $unique_over_translations);
            return;
        }
        $this->fields = $fields;
        $this->updatable = $updatable;
        $this->style = $style;
        $this->unique = $unique;
        $this->unique_over_translations = $unique_over_translations;
        $this->unique_base = $unique_base;
        $this->separator = $separator;
        $this->prefix = $prefix;
        $this->suffix = $suffix;
        $this->handlers = $handlers;
        $this->date_format = $date_format;
    }
}