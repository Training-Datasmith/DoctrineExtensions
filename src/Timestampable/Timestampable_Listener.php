<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Timestampable;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Abstract_Tracking_Listener;
use Gedmo\Timestampable\Mapping\Event\Timestampable_Adapter;
/**
 * The Timestampable listener handles the update of
 * dates on creation and update.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @phpstan-extends AbstractTrackingListener<array, TimestampableAdapter>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Timestampable_Listener extends Abstract_Tracking_Listener
{
    /**
     * @param ClassMetadata<object> $meta
     * @param string                $field
     * @param TimestampableAdapter  $eventAdapter
     *
     * @return mixed
     */
    protected function get_field_value($meta, $field, $event_adapter)
    {
        return $event_adapter->get_date_value($meta, $field);
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
}