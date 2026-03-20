<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Mapping\Event\Adapter_Interface;
interface Strategy
{
    /**
     * NestedSet strategy
     */
    public const NESTED = 'nested';
    /**
     * Closure strategy
     */
    public const CLOSURE = 'closure';
    /**
     * Materialized Path strategy
     */
    public const MATERIALIZED_PATH = 'materializedPath';
    /**
     * Create a new strategy instance
     */
    public function __construct(Tree_Listener $listener);
    /**
     * Get the name of the strategy
     *
     * @return string
     */
    public function get_name();
    /**
     * Operations after metadata is loaded
     *
     * @param ObjectManager         $om
     * @param ClassMetadata<object> $meta
     *
     * @return void
     */
    public function process_metadata_load($om, $meta);
    /**
     * Operations on tree node insertion
     *
     * @param ObjectManager $om
     * @param object|Node   $object
     *
     * @return void
     */
    public function process_scheduled_insertion($om, $object, Adapter_Interface $ea);
    /**
     * Operations on tree node updates
     *
     * @param ObjectManager $om
     * @param object|Node   $object
     *
     * @return void
     */
    public function process_scheduled_update($om, $object, Adapter_Interface $ea);
    /**
     * Operations on tree node delete
     *
     * @param ObjectManager $om
     * @param object|Node   $object
     *
     * @return void
     */
    public function process_scheduled_delete($om, $object);
    /**
     * Operations on tree node removal
     *
     * @param ObjectManager $om
     * @param object|Node   $object
     *
     * @return void
     */
    public function process_pre_remove($om, $object);
    /**
     * Operations on tree node persist
     *
     * @param ObjectManager $om
     * @param object|Node   $object
     *
     * @return void
     */
    public function process_pre_persist($om, $object);
    /**
     * Operations on tree node update
     *
     * @param ObjectManager $om
     * @param object|Node   $object
     *
     * @return void
     */
    public function process_pre_update($om, $object);
    /**
     * Operations on tree node insertions
     *
     * @param ObjectManager $om
     * @param object|Node   $object
     *
     * @return void
     */
    public function process_post_persist($om, $object, Adapter_Interface $ea);
    /**
     * Operations on tree node updates
     *
     * @param ObjectManager $om
     * @param object|Node   $object
     *
     * @return void
     */
    public function process_post_update($om, $object, Adapter_Interface $ea);
    /**
     * Operations on tree node removals
     *
     * @param ObjectManager $om
     * @param object|Node   $object
     *
     * @return void
     */
    public function process_post_remove($om, $object, Adapter_Interface $ea);
    /**
     * Operations on the end of flush process
     *
     * @param ObjectManager $om
     *
     * @return void
     */
    public function on_flush_end($om, Adapter_Interface $ea);
}