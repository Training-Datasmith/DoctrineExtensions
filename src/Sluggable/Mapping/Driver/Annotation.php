<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sluggable\Mapping\Driver;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Annotation\Slug;
use Gedmo\Mapping\Annotation\Slug_Handler;
use Gedmo\Mapping\Annotation\Slug_Handler_Option;
use Gedmo\Mapping\Driver\Annotation_Driver_Interface;
use Gedmo\Sluggable\Handler\Slug_Handler_Interface;
/**
 * Mapping driver for the sluggable extension which reads extended metadata from annotations on a sluggable class.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @deprecated since gedmo/doctrine-extensions 3.16, will be removed in version 4.0.
 *
 * @internal
 */
class Annotation extends Attribute implements Annotation_Driver_Interface
{
    /**
     * @param ClassMetadata<object> $meta
     *
     * @return array<class-string<SlugHandlerInterface>, SlugHandler[]>
     */
    protected function get_slug_handlers(\ReflectionProperty $property, Slug $slug, Class_Metadata $meta): array
    {
        if (!is_array($slug->handlers) || [] === $slug->handlers) {
            return [];
        }
        $handlers = [];
        foreach ($slug->handlers as $handler) {
            if (!$handler instanceof Slug_Handler) {
                throw new Invalid_Mapping_Exception("SlugHandler: {$handler} should be instance of SlugHandler annotation in entity - {$meta->get_name()}");
            }
            if (!class_exists($handler->class)) {
                throw new Invalid_Mapping_Exception("SlugHandler class: {$handler->class} should be a valid class name in entity - {$meta->get_name()}");
            }
            /** @var class-string<SlugHandlerInterface> $class */
            $class = $handler->class;
            $handlers[$class] = [];
            foreach ($handler->options as $option) {
                if (!$option instanceof Slug_Handler_Option) {
                    throw new Invalid_Mapping_Exception("SlugHandlerOption: {$option} should be instance of SlugHandlerOption annotation in entity - {$meta->get_name()}");
                }
                if ('' === $option->name) {
                    throw new Invalid_Mapping_Exception("SlugHandlerOption name: {$option->name} should be valid name in entity - {$meta->get_name()}");
                }
                $handlers[$class][$option->name] = $option->value;
            }
            $class::validate($handlers[$class], $meta);
        }
        return $handlers;
    }
}