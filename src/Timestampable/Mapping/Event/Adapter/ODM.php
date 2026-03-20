<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Timestampable\Mapping\Event\Adapter;

use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Gedmo\Mapping\Event\Adapter\ODM as BaseAdapterODM;
use Gedmo\Mapping\Event\Clock_Aware_Adapter_Interface;
use Gedmo\Timestampable\Mapping\Event\Timestampable_Adapter;
use Psr\Clock\Clock_Interface;
/**
 * Doctrine event adapter for ODM adapted
 * for Timestampable behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
final class ODM extends Base_Adapter_Odm implements Timestampable_Adapter, Clock_Aware_Adapter_Interface
{
    private ?Clock_Interface $clock = null;
    public function set_clock(Clock_Interface $clock): void
    {
        $this->clock = $clock;
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public function get_date_value($meta, $field)
    {
        $datetime = $this->clock instanceof Clock_Interface ? $this->clock->now() : new \DateTimeImmutable();
        $mapping = $meta->get_field_mapping($field);
        $type = $mapping['type'] ?? null;
        if ('timestamp' === $type) {
            return (int) $datetime->format('U');
        }
        if (in_array($type, ['date_immutable', 'time_immutable', 'datetime_immutable', 'datetimetz_immutable'], true)) {
            return $datetime;
        }
        return \DateTime::create_from_immutable($datetime);
    }
}