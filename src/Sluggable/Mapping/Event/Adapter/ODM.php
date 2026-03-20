<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sluggable\Mapping\Event\Adapter;

use Gedmo\Mapping\Event\Adapter\ODM as BaseAdapterODM;
use Gedmo\Sluggable\Mapping\Event\Sluggable_Adapter;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
use Mongo_Db\BSON\Regex;
/**
 * Doctrine event adapter for ODM adapted
 * for sluggable behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
final class ODM extends Base_Adapter_Odm implements Sluggable_Adapter
{
    public function get_similar_slugs($object, $meta, array $config, $slug)
    {
        $dm = $this->get_object_manager();
        $wrapped = Abstract_Wrapper::wrap($object, $dm);
        $qb = $dm->create_query_builder($config['useObjectClass']);
        if (($identifier = $wrapped->get_identifier()) && !$meta->is_identifier($config['slug'])) {
            $qb->field($meta->get_identifier()[0])->not_equal($identifier);
        }
        $qb->field($config['slug'])->equals(new Regex('^' . preg_quote($slug, '/')));
        // use the unique_base to restrict the uniqueness check
        if ($config['unique'] && isset($config['unique_base'])) {
            if (is_object($ubase = $wrapped->get_property_value($config['unique_base']))) {
                $qb->field($config['unique_base'] . '.$id')->equals(new \Mongo_Id($ubase->get_id()));
            } elseif ($ubase) {
                $qb->where('/^' . preg_quote($ubase, '/') . '/.test(this.' . $config['unique_base'] . ')');
            } else {
                $qb->field($config['unique_base'])->equals(null);
            }
        }
        $q = $qb->get_query();
        $q->set_hydrate(false);
        return $q->getIterator()->to_array();
    }
    /**
     * This query can cause some data integrity failures since it does not
     * execute automatically
     *
     * {@inheritdoc}
     */
    public function replace_relative($object, array $config, $target, $replacement): int
    {
        $dm = $this->get_object_manager();
        $meta = $dm->get_class_metadata($config['useObjectClass']);
        $q = $dm->create_query_builder($config['useObjectClass'])->where("function() {\n                return this.{$config['slug']}.indexOf('{$target}') === 0;\n            }")->get_query();
        $q->set_hydrate(false);
        $result = $q->getIterator();
        $count = 0;
        foreach ($result as $target_object) {
            ++$count;
            $slug = preg_replace("@^{$target}@smi", $replacement . $config['pathSeparator'], $target_object[$config['slug']]);
            $dm->create_query_builder()->update_many($config['useObjectClass'])->field($config['slug'])->set($slug)->field($meta->get_identifier()[0])->equals($target_object['_id'])->get_query()->execute();
        }
        return $count;
    }
    /**
     * This query can cause some data integrity failures since it does not
     * execute atomically
     *
     * {@inheritdoc}
     */
    public function replace_inverse_relative($object, array $config, $target, $replacement): int
    {
        $dm = $this->get_object_manager();
        $wrapped = Abstract_Wrapper::wrap($object, $dm);
        $meta = $dm->get_class_metadata($config['useObjectClass']);
        $q = $dm->create_query_builder($config['useObjectClass'])->field($config['mappedBy'] . '.' . $meta->get_identifier()[0])->equals($wrapped->get_identifier())->get_query();
        $q->set_hydrate(false);
        $result = $q->getIterator();
        $count = 0;
        foreach ($result as $target_object) {
            ++$count;
            $slug = preg_replace("@^{$replacement}@smi", $target, $target_object[$config['slug']]);
            $dm->create_query_builder()->update_many($config['useObjectClass'])->field($config['slug'])->set($slug)->field($meta->get_identifier()[0])->equals($target_object['_id'])->get_query()->execute();
        }
        return $count;
    }
}