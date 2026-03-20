<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tool\ORM\Hydration;

use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Internal\Hydration\Abstract_Hydrator;
/**
 * Helper method to retrieve the entity manager for ORM hydrator classes.
 *
 * This trait includes a compatibility layer for the renamed `Doctrine\ORM\Internal\Hydration\AbstractHydrator::$_em`
 * property between ORM 2.x and 3.x.
 *
 * @mixin AbstractHydrator
 *
 * @internal
 */
trait Entity_Manager_Retriever
{
    protected function get_entity_manager(): Entity_Manager_Interface
    {
        return property_exists($this, '_em') ? $this->_em : $this->em;
    }
}