<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Mapping\Event;

use Psr\Clock\Clock_Interface;
/**
 * Doctrine event adapter supporting a PSR-20 {@see ClockInterface}.
 */
interface Clock_Aware_Adapter_Interface
{
    public function set_clock(Clock_Interface $clock): void;
}