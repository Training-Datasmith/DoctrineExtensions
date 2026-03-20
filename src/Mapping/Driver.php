<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Mapping;

use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata as OdmClassMetadata;
use Doctrine\ORM\Mapping\Class_Metadata as OrmClassMetadata;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Mapping\Driver\Mapping_Driver;
use Gedmo\Exception\Invalid_Mapping_Exception;
/**
 * The mapping driver interface defines the metadata extraction functions
 * common among all drivers used on these extensions.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
interface Driver
{
    /**
     * Read the extended metadata configuration for a single mapped class.
     *
     * @todo In the next major release stop receiving by reference the `$config` parameter and use `array` as return type declaration
     *
     * @param ClassMetadata<T>     $meta
     * @param array<string, mixed> $config
     *
     * @template T of object
     *
     * @phpstan-param ClassMetadata<T>&(OdmClassMetadata<T>|OrmClassMetadata<T>) $meta
     *
     * @throws InvalidMappingException if the mapping configuration is invalid
     *
     * @return void
     */
    public function read_extended_metadata($meta, array &$config);
    /**
     * Sets the original mapping driver.
     *
     * @param MappingDriver $driver
     *
     * @return void
     */
    public function set_original_driver($driver);
}