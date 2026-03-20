<?php

declare (strict_types=1);
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
    private ?Driver $default_driver = null;
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
    public function add_driver(Driver $nested_driver, string $namespace): void
    {
        $this->_drivers[$namespace] = $nested_driver;
    }
    /**
     * Get the array of nested drivers.
     *
     * @return array<string, Driver>
     */
    public function get_drivers(): array
    {
        return $this->_drivers;
    }
    /**
     * Get the default driver.
     */
    public function get_default_driver(): ?\Gedmo\Mapping\Driver
    {
        return $this->default_driver;
    }
    /**
     * Set the default driver.
     */
    public function set_default_driver(Driver $driver): void
    {
        $this->default_driver = $driver;
    }
    public function read_extended_metadata($meta, array &$config)
    {
        foreach ($this->_drivers as $namespace => $driver) {
            if (0 === strpos($meta->get_name(), $namespace)) {
                $extended_metadata = $driver->read_extended_metadata($meta, $config);
                if (\is_array($extended_metadata)) {
                    $config = $extended_metadata;
                }
                // @todo: In the next major release remove the assignment to `$extendedMetadata`, the previous conditional
                // block, uncomment the following line and replace the following return statement.
                // return $driver->readExtendedMetadata($meta, $config);
                return $config;
            }
        }
        if (null !== $this->default_driver) {
            $extended_metadata = $this->default_driver->read_extended_metadata($meta, $config);
            if (\is_array($extended_metadata)) {
                $config = $extended_metadata;
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
    public function set_original_driver($driver): void
    {
        // not needed here
    }
}