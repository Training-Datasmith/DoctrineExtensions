<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable\Event;

use Doctrine\Persistence\Event\Lifecycle_Event_Args;
use Doctrine\Persistence\Object_Manager;
/**
 * @template TObjectManager of ObjectManager
 *
 * @template-extends LifecycleEventArgs<TObjectManager>
 */
final class Pre_Soft_Delete_Event_Args extends Lifecycle_Event_Args
{
}