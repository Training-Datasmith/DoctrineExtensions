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
use Gedmo\Uploadable\Filename_Generator\Filename_Generator_Interface;
use Gedmo\Uploadable\Mapping\Validator;
/**
 * Uploadable annotation for Uploadable behavioral extension
 *
 * @Annotation
 *
 * @NamedArgumentConstructor
 *
 * @Target("CLASS")
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Uploadable implements Gedmo_Annotation
{
    use Forward_Compatibility_Trait;
    public bool $allow_overwrite = false;
    public bool $append_number = false;
    public string $path = '';
    public string $path_method = '';
    public string $callback = '';
    /**
     * @phpstan-var Validator::FILENAME_GENERATOR_*|class-string<FilenameGeneratorInterface>
     */
    public string $filename_generator = Validator::FILENAME_GENERATOR_NONE;
    public string $max_size = '0';
    /**
     * @var string A list of comma separate values of allowed types, like "text/plain,text/css"
     */
    public string $allowed_types = '';
    /**
     * @var string A list of comma separate values of disallowed types, like "video/jpeg,text/html"
     */
    public string $disallowed_types = '';
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [], bool $allow_overwrite = false, bool $append_number = false, string $path = '', string $path_method = '', string $callback = '', string $filename_generator = Validator::FILENAME_GENERATOR_NONE, string $max_size = '0', string $allowed_types = '', string $disallowed_types = '')
    {
        if ([] !== $data) {
            Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2386', 'Passing an array as first argument to "%s()" is deprecated. Use named arguments instead.', __METHOD__);
            $args = func_get_args();
            $this->allow_overwrite = $this->get_attribute_value($data, 'allowOverwrite', $args, 1, $allow_overwrite);
            $this->append_number = $this->get_attribute_value($data, 'appendNumber', $args, 2, $append_number);
            $this->path = $this->get_attribute_value($data, 'path', $args, 3, $path);
            $this->path_method = $this->get_attribute_value($data, 'pathMethod', $args, 4, $path_method);
            $this->callback = $this->get_attribute_value($data, 'callback', $args, 5, $callback);
            $this->filename_generator = $this->get_attribute_value($data, 'filenameGenerator', $args, 6, $filename_generator);
            $this->max_size = $this->get_attribute_value($data, 'maxSize', $args, 7, $max_size);
            $this->allowed_types = $this->get_attribute_value($data, 'allowedTypes', $args, 8, $allowed_types);
            $this->disallowed_types = $this->get_attribute_value($data, 'disallowedTypes', $args, 9, $disallowed_types);
            return;
        }
        $this->allow_overwrite = $allow_overwrite;
        $this->append_number = $append_number;
        $this->path = $path;
        $this->path_method = $path_method;
        $this->callback = $callback;
        $this->filename_generator = $filename_generator;
        $this->max_size = $max_size;
        $this->allowed_types = $allowed_types;
        $this->disallowed_types = $disallowed_types;
    }
}