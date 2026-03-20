<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable\Mapping\Event\Adapter;

use Doctrine\ORM\Mapping\Class_Metadata;
use Gedmo\Loggable\Entity\Log_Entry;
use Gedmo\Loggable\Mapping\Event\Loggable_Adapter;
use Gedmo\Mapping\Event\Adapter\ORM as BaseAdapterORM;
use Gedmo\Tool\Wrapper\Entity_Wrapper;
/**
 * Doctrine event adapter for ORM adapted
 * for Loggable behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
final class ORM extends Base_Adapter_Orm implements Loggable_Adapter
{
    public function get_default_log_entry_class(): string
    {
        return Log_Entry::class;
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public function is_post_insert_generator($meta)
    {
        return $meta->id_generator->is_post_insert_generator();
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public function get_new_version($meta, $object)
    {
        $em = $this->get_object_manager();
        $object_meta = $em->get_class_metadata(get_class($object));
        $wrapper = new Entity_Wrapper($object, $em);
        $object_id = $wrapper->get_identifier(false, true);
        $dql = "SELECT MAX(log.version) FROM {$meta->get_name()} log";
        $dql .= ' WHERE log.objectId = :objectId';
        $dql .= ' AND log.objectClass = :objectClass';
        $q = $em->create_query($dql);
        $q->set_parameters(['objectId' => $object_id, 'objectClass' => $object_meta->get_name()]);
        return $q->get_single_scalar_result() + 1;
    }
}