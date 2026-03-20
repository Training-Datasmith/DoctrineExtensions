<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sortable\Mapping\Event\Adapter;

use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Gedmo\Mapping\Event\Adapter\ODM as BaseAdapterODM;
use Gedmo\Sortable\Mapping\Event\Sortable_Adapter;
use Gedmo\Sortable\Sortable_Listener;
use Gedmo\Tool\Class_Utils;
/**
 * Doctrine event adapter for ODM adapted
 * for sortable behavior
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @phpstan-import-type SortableConfiguration from SortableListener
 * @phpstan-import-type SortableRelocation from SortableListener
 */
final class ODM extends Base_Adapter_Odm implements Sortable_Adapter
{
    /**
     * @param array<string, mixed>    $config
     * @param ClassMetadata<object>   $meta
     * @param iterable<string, mixed> $groups
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return int
     */
    public function get_max_position(array $config, $meta, $groups)
    {
        $dm = $this->get_object_manager();
        $qb = $dm->create_query_builder($config['useObjectClass']);
        foreach ($groups as $group => $value) {
            if (is_object($value) && !$dm->get_metadata_factory()->is_transient(Class_Utils::get_class($value))) {
                $qb->field($group)->references($value);
            } else {
                $qb->field($group)->equals($value);
            }
        }
        $qb->sort($config['position'], 'desc');
        $document = $qb->get_query()->get_single_result();
        if ($document) {
            return $meta->get_field_value($document, $config['position']);
        }
        return -1;
    }
    /**
     * @param array<string, mixed> $relocation
     * @param array<string, mixed> $delta
     * @param array<string, mixed> $config
     *
     * @phpstan-param SortableRelocation    $relocation
     * @phpstan-param SortableConfiguration $config
     */
    public function update_positions($relocation, $delta, $config): void
    {
        $dm = $this->get_object_manager();
        $delta = array_map('intval', $delta);
        $qb = $dm->create_query_builder($config['useObjectClass']);
        $qb->update_many();
        $qb->field($config['position'])->inc($delta['delta']);
        $qb->field($config['position'])->gte($delta['start']);
        if ($delta['stop'] > 0) {
            $qb->field($config['position'])->lt($delta['stop']);
        }
        foreach ($relocation['groups'] as $group => $value) {
            if (is_object($value) && !$dm->get_metadata_factory()->is_transient(Class_Utils::get_class($value))) {
                $qb->field($group)->references($value);
            } else {
                $qb->field($group)->equals($value);
            }
        }
        $qb->get_query()->execute();
    }
}