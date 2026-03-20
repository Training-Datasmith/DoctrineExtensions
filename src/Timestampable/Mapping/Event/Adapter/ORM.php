<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Timestampable\Mapping\Event\Adapter;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Mapping\Field_Mapping;
use Gedmo\Mapping\Event\Adapter\ORM as BaseAdapterORM;
use Gedmo\Mapping\Event\Clock_Aware_Adapter_Interface;
use Gedmo\Timestampable\Mapping\Event\Timestampable_Adapter;
use Psr\Clock\Clock_Interface;
/**
 * Doctrine event adapter for ORM adapted
 * for Timestampable behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
final class ORM extends Base_Adapter_Orm implements Timestampable_Adapter, Clock_Aware_Adapter_Interface
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
        $mapping = $meta->get_field_mapping($field);
        return $this->get_object_manager()->get_connection()->convert_to_php_value($this->get_raw_date_value($mapping), $mapping instanceof Field_Mapping ? $mapping->type : $mapping['type'] ?? Types::DATETIME_MUTABLE);
    }
    /**
     * Generates current timestamp for the specified mapping
     *
     * @param array<string, mixed>|FieldMapping $mapping
     *
     * @return \DateTimeInterface|int
     */
    private function get_raw_date_value(array $mapping)
    {
        $datetime = $this->clock instanceof Clock_Interface ? $this->clock->now() : new \DateTimeImmutable();
        $type = $mapping instanceof Field_Mapping ? $mapping->type : $mapping['type'] ?? '';
        if ('integer' === $type) {
            return (int) $datetime->format('U');
        }
        if (in_array($type, ['date_immutable', 'time_immutable', 'datetime_immutable', 'datetimetz_immutable'], true)) {
            return $datetime;
        }
        return \DateTime::create_from_immutable($datetime);
    }
}