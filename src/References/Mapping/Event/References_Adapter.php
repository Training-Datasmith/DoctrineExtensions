<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\References\Mapping\Event;

use Doctrine\Persistence\Object_Manager;
use Gedmo\Mapping\Event\Adapter_Interface;
/**
 * Doctrine event adapter for the References extension.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
interface References_Adapter extends Adapter_Interface
{
    /**
     * Gets the identifier of the given object using the provided object manager.
     *
     * @param ObjectManager $om
     * @param object        $object
     * @param bool          $single
     *
     * @return array<int|string, mixed>|string|int|null array or single identifier
     */
    public function get_identifier($om, $object, $single = true);
    /**
     * Gets a single reference from the provided object manager for a class and identifier.
     *
     * @param ObjectManager                       $om
     * @param string                              $class
     * @param array<int|string, mixed>|string|int $identifier
     *
     * @phpstan-param class-string $class
     *
     * @return object|null
     */
    public function get_single_reference($om, $class, $identifier);
    /**
     * Extracts identifiers from an object or proxy using the provided object manager.
     *
     * @param ObjectManager $om
     * @param object        $object
     * @param bool          $single
     *
     * @return array<int|string, mixed>|string|int|null array or single identifier
     */
    public function extract_identifier($om, $object, $single = true);
}