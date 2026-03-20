<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable\Mapping\Event\Adapter;

use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Gedmo\Loggable\Document\Log_Entry;
use Gedmo\Loggable\Mapping\Event\Loggable_Adapter;
use Gedmo\Mapping\Event\Adapter\ODM as BaseAdapterODM;
/**
 * Doctrine event adapter for ODM adapted
 * for Loggable behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
final class ODM extends Base_Adapter_Odm implements Loggable_Adapter
{
    public function get_default_log_entry_class(): string
    {
        return Log_Entry::class;
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public function is_post_insert_generator($meta): bool
    {
        return false;
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public function get_new_version($meta, $object)
    {
        $dm = $this->get_object_manager();
        $object_meta = $dm->get_class_metadata(get_class($object));
        $identifier_field = $this->get_single_identifier_field_name($object_meta);
        $object_id = $object_meta->get_field_value($object, $identifier_field);
        $qb = $dm->create_query_builder($meta->get_name());
        $qb->select('version');
        $qb->field('objectId')->equals($object_id);
        $qb->field('objectClass')->equals($object_meta->get_name());
        $qb->sort('version', 'DESC');
        $qb->limit(1);
        $q = $qb->get_query();
        $q->set_hydrate(false);
        $result = $q->get_single_result();
        if ($result) {
            return $result['version'] + 1;
        }
        return $result;
    }
}