<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable\Mapping\Event;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Mapping\Event\Adapter_Interface;
/**
 * Doctrine event adapter for the SoftDeleteable extension.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
interface Soft_Deleteable_Adapter extends Adapter_Interface
{
    /**
     * Get the date value.
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     *
     * @return int|\DateTimeInterface
     */
    public function get_date_value($meta, $field);
}