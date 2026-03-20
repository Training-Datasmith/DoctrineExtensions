<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo;

use Doctrine\Common\Annotations\Annotation_Reader;
use Doctrine\Common\Annotations\Psr_Cached_Reader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ODM\Mongo_Db\Mapping\Driver as DriverMongodbODM;
use Doctrine\ORM\Mapping\Driver as DriverORM;
use Doctrine\Persistence\Mapping\Driver\Mapping_Driver_Chain;
use Gedmo\Exception\RuntimeException;
use Symfony\Component\Cache\Adapter\Array_Adapter;
/**
 * Version class allows checking the required dependencies
 * and the current version of the Doctrine Extensions library.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
final class Doctrine_Extensions
{
    /**
     * Current version of extensions
     */
    public const VERSION = '3.22.0';
    /**
     * Hooks all extension metadata mapping drivers into
     * the given driver chain of drivers for the ORM.
     */
    public static function register_mapping_into_driver_chain_orm(Mapping_Driver_Chain $driver_chain, ?Reader $reader = null): void
    {
        $paths = [__DIR__ . '/Translatable/Entity', __DIR__ . '/Loggable/Entity', __DIR__ . '/Tree/Entity'];
        if (\PHP_VERSION_ID >= 80000) {
            $driver = new Driver_Orm\Attribute_Driver($paths);
        } else {
            $driver = new Driver_Orm\Annotation_Driver($reader ?? self::create_annotation_reader(), $paths);
        }
        $driver_chain->add_driver($driver, 'Gedmo');
    }
    /**
     * Hooks only superclass extension metadata mapping drivers into
     * the given driver chain of drivers for the ORM.
     */
    public static function register_abstract_mapping_into_driver_chain_orm(Mapping_Driver_Chain $driver_chain, ?Reader $reader = null): void
    {
        $paths = [__DIR__ . '/Translatable/Entity/MappedSuperclass', __DIR__ . '/Loggable/Entity/MappedSuperclass', __DIR__ . '/Tree/Entity/MappedSuperclass'];
        if (\PHP_VERSION_ID >= 80000) {
            $driver = new Driver_Orm\Attribute_Driver($paths);
        } else {
            $driver = new Driver_Orm\Annotation_Driver($reader ?? self::create_annotation_reader(), $paths);
        }
        $driver_chain->add_driver($driver, 'Gedmo');
    }
    /**
     * Hooks all extension metadata mapping drivers into
     * the given driver chain of drivers for the MongoDB ODM.
     */
    public static function register_mapping_into_driver_chain_mongodb_odm(Mapping_Driver_Chain $driver_chain, ?Reader $reader = null): void
    {
        $paths = [__DIR__ . '/Translatable/Document', __DIR__ . '/Loggable/Document'];
        if (\PHP_VERSION_ID >= 80000) {
            $driver = new Driver_Mongodb_Odm\Attribute_Driver($paths);
        } else {
            $driver = new Driver_Mongodb_Odm\Annotation_Driver($reader ?? self::create_annotation_reader(), $paths);
        }
        $driver_chain->add_driver($driver, 'Gedmo');
    }
    /**
     * Hooks only superclass extension metadata mapping drivers into
     * the given driver chain of drivers for the MongoDB ODM.
     */
    public static function register_abstract_mapping_into_driver_chain_mongodb_odm(Mapping_Driver_Chain $driver_chain, ?Reader $reader = null): void
    {
        $paths = [__DIR__ . '/Translatable/Document/MappedSuperclass', __DIR__ . '/Loggable/Document/MappedSuperclass'];
        if (\PHP_VERSION_ID >= 80000) {
            $driver = new Driver_Mongodb_Odm\Attribute_Driver($paths);
        } else {
            $driver = new Driver_Mongodb_Odm\Annotation_Driver($reader ?? self::create_annotation_reader(), $paths);
        }
        $driver_chain->add_driver($driver, 'Gedmo');
    }
    /**
     * Registers all extension annotations.
     *
     * @deprecated to be removed in 4.0, annotation classes are autoloaded instead
     */
    public static function register_annotations(): void
    {
        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2558', '"%s()" is deprecated since gedmo/doctrine-extensions 3.11 and will be removed in version 4.0.', __METHOD__);
        // Purposefully no-op'd, all supported versions of `doctrine/annotations` support autoloading
    }
    /**
     * @throws RuntimeException if running PHP 7 and the `doctrine/annotations` package is not installed
     */
    private static function create_annotation_reader(): Psr_Cached_Reader
    {
        if (!class_exists(Annotation_Reader::class)) {
            throw new RuntimeException(sprintf('The "%1$s" class requires the "doctrine/annotations" package to use annotations but it is not available. Try running "composer require doctrine/annotations" or upgrade to PHP 8 to use attributes.', self::class));
        }
        return new Psr_Cached_Reader(new Annotation_Reader(), new Array_Adapter());
    }
}