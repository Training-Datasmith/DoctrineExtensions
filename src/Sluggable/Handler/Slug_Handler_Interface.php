<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sluggable\Handler;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Sluggable\Mapping\Event\Sluggable_Adapter;
use Gedmo\Sluggable\Sluggable_Listener;
/**
 * Interface defining a handler for the sluggable behavior.
 * Usage is intended only for internal access of the
 * Sluggable extension and should not be used elsewhere.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @phpstan-import-type SlugConfiguration from SluggableListener
 */
interface Slug_Handler_Interface
{
    /**
     * Create a new handler instance
     */
    public function __construct(Sluggable_Listener $sluggable);
    /**
     * Hook on slug handlers before the decision is made whether
     * the slug needs to be recalculated.
     *
     * @param array<string, mixed> $config
     * @param object               $object
     * @param string               $slug
     * @param bool                 $needToChangeSlug
     *
     * @phpstan-param SlugConfiguration $config
     *
     * @return void
     */
    public function on_change_decision(Sluggable_Adapter $ea, array &$config, $object, &$slug, &$need_to_change_slug);
    /**
     * Hook on slug handlers called after the slug is built.
     *
     * @param array<string, mixed> $config
     * @param object               $object
     * @param string               $slug
     *
     * @phpstan-param SlugConfiguration $config
     *
     * @return void
     */
    public function post_slug_build(Sluggable_Adapter $ea, array &$config, $object, &$slug);
    /**
     * Hook for slug handlers called after the slug is completed.
     *
     * @param array<string, mixed> $config
     * @param object               $object
     * @param string               $slug
     *
     * @phpstan-param SlugConfiguration $config
     *
     * @return void
     */
    public function on_slug_completion(Sluggable_Adapter $ea, array &$config, $object, &$slug);
    /**
     * @return bool Whether this handler has already urlized the slug
     */
    public function handles_urlization();
    /**
     * Validates the options for the handler.
     *
     * @param array<string, mixed>  $options
     * @param ClassMetadata<object> $meta
     *
     * @throws InvalidMappingException if the configuration is invalid
     *
     * @return void
     */
    public static function validate(array $options, Class_Metadata $meta);
}