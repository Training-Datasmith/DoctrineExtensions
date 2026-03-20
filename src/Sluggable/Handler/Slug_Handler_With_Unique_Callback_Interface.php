<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sluggable\Handler;

use Gedmo\Sluggable\Mapping\Event\Sluggable_Adapter;
use Gedmo\Sluggable\Sluggable_Listener;
/**
 * This adds the ability for a slug handler to change the slug just before its
 * uniqueness is ensured. It is also called if the unique options are _not_
 * set.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @phpstan-import-type SlugConfiguration from SluggableListener
 */
interface Slug_Handler_With_Unique_Callback_Interface extends Slug_Handler_Interface
{
    /**
     * Hook for slug handlers called before it is made unique.
     *
     * @param SlugConfiguration $config
     * @param object            $object
     * @param string            $slug
     *
     * @return void
     */
    public function before_making_unique(Sluggable_Adapter $ea, array &$config, $object, &$slug);
}