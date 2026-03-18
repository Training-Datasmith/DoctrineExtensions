<?php

declare(strict_types=1);

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Mapping\Driver;

use Gedmo\Mapping\Driver;

/**
 * The chain mapping driver enables chained
 * extension mapping driver support
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Chain implements Driver
{
    /**
     * The default driver
     */
    private ?Driver $defaultDriver = null;

    /**
     * List of drivers nested
     *
     * @var array<string, Driver>
     */
    private array $_drivers = [];

    /**
     * Add a nested driver.
     *
     *
     */
    public function addDriver(Driver $nestedDriver, string $namespace): void
    {
        $this->_drivers[$namespace] = $nestedDriver;
    }

    /**
     * Get the array of nested drivers.
     *
     * @return array<string, Driver>
     */
    public function getDrivers(): array
    {
        return $this->_drivers;
    }

    /**
     * Get the default driver.
     */
    public function getDefaultDriver(): ?\Gedmo\Mapping\Driver
    {
        return $this->defaultDriver;
    }

    /**
     * Set the default driver.
     */
    public function setDefaultDriver(Driver $driver): void
    {
        $this->defaultDriver = $driver;
    }

    public function readExtendedMetadata($meta, array &$config)
    {
        foreach ($this->_drivers as $namespace => $driver) {
            if (0 === strpos($meta->getName(), $namespace)) {
                $extendedMetadata = $driver->readExtendedMetadata($meta, $config);

                if (\is_array($extendedMetadata)) {
                    $config = $extendedMetadata;
                }

                // @todo: In the next major release remove the assignment to `$extendedMetadata`, the previous conditional
                // block, uncomment the following line and replace the following return statement.
                // return $driver->readExtendedMetadata($meta, $config);
                return $config;
            }
        }

        if (null !== $this->defaultDriver) {
            $extendedMetadata = $this->defaultDriver->readExtendedMetadata($meta, $config);

            if (\is_array($extendedMetadata)) {
                $config = $extendedMetadata;
            }

            // @todo: In the next major release remove the assignment to `$extendedMetadata`, the previous conditional
            // block, uncomment the following line and replace the following return statement.
            // return $this->defaultDriver->readExtendedMetadata($meta, $config);
            return $config;
        }

        // commenting it for customized mapping support, debugging of such cases might get harder
        // throw new \Gedmo\Exception\UnexpectedValueException('Class ' . $meta->getName() . ' is not a valid entity or mapped super class.');
    }

    /**
     * Passes in the mapping read by original driver
     */
    public function setOriginalDriver($driver): void
    {
        // not needed here
    }
}
