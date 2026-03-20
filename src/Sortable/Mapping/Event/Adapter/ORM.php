<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sortable\Mapping\Event\Adapter;

use Doctrine\ORM\Query_Builder;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Mapping\Event\Adapter\ORM as BaseAdapterORM;
use Gedmo\Sortable\Mapping\Event\Sortable_Adapter;
use Gedmo\Sortable\Sortable_Listener;
/**
 * Doctrine event adapter for ORM adapted
 * for sortable behavior
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @phpstan-import-type SortableConfiguration from SortableListener
 * @phpstan-import-type SortableRelocation from SortableListener
 */
final class ORM extends Base_Adapter_Orm implements Sortable_Adapter
{
    /**
     * @param array<string, mixed>    $config
     * @param ClassMetadata<object>   $meta
     * @param iterable<string, mixed> $groups
     *
     * @phpstan-param SortableConfiguration $config
     *
     * @return int|null
     */
    public function get_max_position(array $config, \Doctrine\Persistence\Mapping\Class_Metadata $meta, iterable $groups)
    {
        $em = $this->get_object_manager();
        $qb = $em->create_query_builder();
        $qb->select('MAX(n.' . $config['position'] . ')')->from($config['useObjectClass'], 'n');
        $this->add_group_where($qb, $meta, $groups);
        $query = $qb->get_query();
        $query->use_query_cache(false);
        $query->disable_result_cache();
        $query->set_max_results(1);
        return $query->get_single_scalar_result();
    }
    /**
     * @param array<string, mixed> $relocation
     * @param array<string, mixed> $delta
     * @param array<string, mixed> $config
     *
     * @phpstan-param SortableRelocation    $relocation
     * @phpstan-param SortableConfiguration $config
     */
    public function update_positions($relocation, array $delta, $config): void
    {
        $sign = $delta['delta'] < 0 ? '-' : '+';
        $abs_delta = abs($delta['delta']);
        $dql = "UPDATE {$relocation['name']} n";
        $dql .= " SET n.{$config['position']} = n.{$config['position']} {$sign} {$abs_delta}";
        $dql .= " WHERE n.{$config['position']} >= {$delta['start']}";
        // if not null, false or 0
        if ($delta['stop'] > 0) {
            $dql .= " AND n.{$config['position']} < {$delta['stop']}";
        }
        $i = -1;
        $params = [];
        foreach ($relocation['groups'] as $group => $value) {
            if (null === $value) {
                $dql .= " AND n.{$group} IS NULL";
            } else {
                $dql .= " AND n.{$group} = :val___" . ++$i;
                $params['val___' . $i] = $value;
            }
        }
        // add excludes
        if (!empty($delta['exclude'])) {
            $meta = $this->get_object_manager()->get_class_metadata($relocation['name']);
            if (1 === count($meta->get_identifier())) {
                // if we only have one identifier, we can use IN syntax, for better performance
                $excluded_ids = [];
                foreach ($delta['exclude'] as $entity) {
                    if ($id = $meta->get_field_value($entity, $meta->get_identifier()[0])) {
                        $excluded_ids[] = $id;
                    }
                }
                if (!empty($excluded_ids)) {
                    $params['excluded'] = $excluded_ids;
                    $dql .= " AND n.{$meta->get_identifier()[0]} NOT IN (:excluded)";
                }
            } elseif (count($meta->get_identifier()) > 1) {
                foreach ($delta['exclude'] as $entity) {
                    $j = 0;
                    $dql .= ' AND NOT (';
                    foreach ($meta->get_identifier_values($entity) as $id => $value) {
                        $dql .= ($j > 0 ? ' AND ' : '') . "n.{$id} = :val___" . ++$i;
                        $params['val___' . $i] = $value;
                        ++$j;
                    }
                    $dql .= ')';
                }
            }
        }
        $em = $this->get_object_manager();
        $q = $em->create_query($dql);
        $q->set_parameters($params);
        $q->get_single_scalar_result();
    }
    /**
     * @param ClassMetadata<object>   $metadata
     * @param iterable<string, mixed> $groups
     */
    private function add_group_where(Query_Builder $qb, Class_Metadata $metadata, iterable $groups): void
    {
        $i = 1;
        foreach ($groups as $group => $value) {
            if (null === $value) {
                $qb->and_where($qb->expr()->is_null('n.' . $group));
            } else {
                $qb->and_where('n.' . $group . ' = :group__' . $i);
                $qb->set_parameter('group__' . $i, $value, $metadata->get_type_of_field($group));
            }
            ++$i;
        }
    }
}