<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable\Mapping\Event;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Mapping\Event\Adapter_Interface;
/**
 * Doctrine event adapter for the Loggable extension.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
interface Loggable_Adapter extends Adapter_Interface
{
    /**
     * Get the default object class name used to store the log entries.
     *
     * @return string
     *
     * @phpstan-return class-string
     */
    public function get_default_log_entry_class();
    /**
     * Checks whether an identifier should be generated post insert.
     *
     * @param ClassMetadata<object> $meta
     *
     * @return bool
     */
    public function is_post_insert_generator($meta);
    /**
     * Get the new version number for an object.
     *
     * @param ClassMetadata<object> $meta
     * @param object                $object
     *
     * @return int
     */
    public function get_new_version($meta, $object);
}