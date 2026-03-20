<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Ip_Traceable\Mapping\Event\Adapter;

use Gedmo\Ip_Traceable\Mapping\Event\Ip_Traceable_Adapter;
use Gedmo\Mapping\Event\Adapter\ORM as BaseAdapterORM;
/**
 * Doctrine event adapter for ORM adapted
 * for IpTraceable behavior
 *
 * @author Pierre-Charles Bertineau <pc.bertineau@alterphp.com>
 */
final class ORM extends Base_Adapter_Orm implements Ip_Traceable_Adapter
{
}