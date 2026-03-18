<?php

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Mapping\Driver;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\FileDriver;
use Doctrine\Persistence\Mapping\Driver\FileLocator;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Gedmo\Mapping\Driver;

/**
 * The mapping FileDriver abstract class, defines the
 * metadata extraction function common among
 * all drivers used on these extensions by file based
 * drivers.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
abstract class File implements Driver
{
    /**
     * @var FileLocator
     */
    protected $locator;

    /**
     * File extension, must be set in child class
     *
     * @var string
     */
    protected $_extension;

    /**
     * original driver if it is available
     *
     * @var MappingDriver
     */
    protected $_originalDriver;

    /**
     * @deprecated since gedmo/doctrine-extensions 3.3, will be removed in version 4.0.
     *
     * @var string[]
     */
    protected $_paths = [];

    public function setLocator(FileLocator $locator): void
    {
        $this->locator = $locator;
    }

    /**
     * Set the paths for file lookup
     *
     * @deprecated since gedmo/doctrine-extensions 3.3, will be removed in version 4.0.
     *
     * @param string[] $paths
     */
    public function setPaths($paths): void
    {
        $this->_paths = (array) $paths;
    }

    /**
     * Set the file extension
     *
     * @param string $extension
     */
    public function setExtension($extension): void
    {
        $this->_extension = $extension;
    }

    /**
     * Passes in the mapping read by original driver
     *
     * @param MappingDriver $driver
     */
    public function setOriginalDriver($driver): void
    {
        $this->_originalDriver = $driver;
    }

    /**
     * Loads a mapping file with the given name and returns a map
     * from class/entity names to their corresponding elements.
     *
     * @param string $file the mapping file to load
     *
     * @return array<string, array<string, mixed>|object|null>
     *
     * @phpstan-return array<class-string, array<string, mixed>|object|null>
     */
    abstract protected function _loadMappingFile($file);

    /**
     * Tries to get a mapping for a given class
     *
     *
     * @phpstan-param class-string $className
     * @return array<string, mixed>|object|null
     */
    protected function _getMapping(string $className)
    {
        // try loading mapping from original driver first
        $mapping = null;
        if (null !== $this->_originalDriver) {
            if ($this->_originalDriver instanceof FileDriver) {
                $mapping = $this->_originalDriver->getElement($className);
            }
        }

        // if no mapping found try to load mapping file again
        if (null === $mapping) {
            $yaml = $this->_loadMappingFile($this->locator->findMappingFile($className));
            $mapping = $yaml[$className];
        }

        return $mapping;
    }

    /**
     * Try to find out related class name out of mapping
     *
     * @param ClassMetadata<object> $metadata the mapped class metadata
     * @param string                $name     the related object class name
     *
     * @phpstan-param class-string|string $name
     *
     * @return string related class name or empty string if does not exist
     *
     * @phpstan-return class-string|''
     */
    protected function getRelatedClassName($metadata, string $name)
    {
        if (class_exists($name) || interface_exists($name)) {
            return $name;
        }
        $refl = $metadata->getReflectionClass();
        $ns = $refl->getNamespaceName();
        $className = $ns.'\\'.$name;

        return class_exists($className) ? $className : '';
    }
}
