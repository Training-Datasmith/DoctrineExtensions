<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Strategy\ODM\Mongo_Db;

use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Mapping\Event\Adapter_Interface;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
use Gedmo\Tree\Strategy\Abstract_Materialized_Path;
use Mongo_Db\BSON\Regex;
use Mongo_Db\BSON\Utc_Date_Time;
/**
 * This strategy makes tree using materialized path strategy
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
class Materialized_Path extends Abstract_Materialized_Path
{
    /**
     * @param DocumentManager       $om
     * @param ClassMetadata<object> $meta
     */
    public function remove_node($om, $meta, $config, $node): void
    {
        $uow = $om->get_unit_of_work();
        $wrapped = Abstract_Wrapper::wrap($node, $om);
        // Remove node's children
        $results = $om->create_query_builder()->find($meta->get_name())->field($config['path'])->equals(new Regex('^' . preg_quote($wrapped->get_property_value($config['path'])) . '.?+'))->get_query()->getIterator();
        foreach ($results as $node) {
            $uow->schedule_for_delete($node);
        }
    }
    /**
     * @param DocumentManager       $om
     * @param ClassMetadata<object> $meta
     */
    public function get_children($om, $meta, $config, $original_path)
    {
        return $om->create_query_builder()->find($meta->get_name())->field($config['path'])->equals(new Regex('^' . preg_quote($original_path) . '.+'))->sort($config['path'], 'asc')->get_query()->getIterator();
    }
    /**
     * @param DocumentManager $om
     */
    protected function lock_trees(Object_Manager $om, Adapter_Interface $ea)
    {
        $uow = $om->get_unit_of_work();
        foreach ($this->roots_of_trees_which_needs_locking as $root) {
            $meta = $om->get_class_metadata(get_class($root));
            $config = $this->listener->get_configuration($om, $meta->get_name());
            $lock_time_value = new Utc_Date_Time();
            $meta->set_field_value($root, $config['lock_time'], $lock_time_value);
            $ea->recompute_single_object_change_set($uow, $meta, $root);
        }
    }
    /**
     * @param DocumentManager $om
     */
    protected function release_tree_locks(Object_Manager $om, Adapter_Interface $ea)
    {
        $uow = $om->get_unit_of_work();
        foreach ($this->roots_of_trees_which_needs_locking as $oid => $root) {
            $meta = $om->get_class_metadata(get_class($root));
            $config = $this->listener->get_configuration($om, $meta->get_name());
            $lock_time_value = null;
            $meta->set_field_value($root, $config['lock_time'], $lock_time_value);
            $ea->recompute_single_object_change_set($uow, $meta, $root);
            unset($this->roots_of_trees_which_needs_locking[$oid]);
        }
    }
}