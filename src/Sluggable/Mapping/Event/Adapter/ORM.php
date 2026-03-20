<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sluggable\Mapping\Event\Adapter;

use Doctrine\ORM\Mapping\Class_Metadata as EntityClassMetadata;
use Doctrine\ORM\Mapping\Class_Metadata_Info as LegacyEntityClassMetadata;
use Doctrine\ORM\Query;
use Gedmo\Mapping\Event\Adapter\ORM as BaseAdapterORM;
use Gedmo\Sluggable\Mapping\Event\Sluggable_Adapter;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
use Gedmo\Tool\Wrapper\Entity_Wrapper;
use Gedmo\Translatable\Query\Tree_Walker\Translation_Walker;
use Gedmo\Translatable\Translatable;
/**
 * Doctrine event adapter for ORM adapted
 * for sluggable behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class ORM extends Base_Adapter_Orm implements Sluggable_Adapter
{
    public function get_similar_slugs($object, $meta, array $config, $slug)
    {
        $em = $this->get_object_manager();
        /** @var EntityWrapper<object> $wrapped */
        $wrapped = Abstract_Wrapper::wrap($object, $em);
        $qb = $em->create_query_builder();
        $qb->select('rec.' . $config['slug'])->from($config['useObjectClass'], 'rec')->where($qb->expr()->like('rec.' . $config['slug'], ':slug'));
        $qb->set_parameter('slug', $slug . '%');
        // use the unique_base to restrict the uniqueness check
        if ($config['unique'] && isset($config['unique_base'])) {
            $ubase = $wrapped->get_property_value($config['unique_base']);
            if (array_key_exists($config['unique_base'], $wrapped->get_metadata()->get_association_mappings())) {
                $mapping = $wrapped->get_metadata()->get_association_mapping($config['unique_base']);
            } else {
                $mapping = false;
            }
            if (($ubase || 0 === $ubase) && !$mapping) {
                $qb->and_where('rec.' . $config['unique_base'] . ' = :unique_base');
                $qb->set_parameter(':unique_base', $ubase);
            } elseif ($ubase && $mapping && in_array($mapping['type'], [Entity_Class_Metadata::ONE_TO_ONE, Entity_Class_Metadata::MANY_TO_ONE], true)) {
                $mapped_alias = 'mapped_' . $config['unique_base'];
                $wrapped_ubase = Abstract_Wrapper::wrap($ubase, $em);
                $metadata = $wrapped_ubase->get_metadata();
                assert($metadata instanceof Entity_Class_Metadata || $metadata instanceof Legacy_Entity_Class_Metadata);
                $qb->inner_join('rec.' . $config['unique_base'], $mapped_alias);
                foreach (array_keys($mapping['targetToSourceKeyColumns']) as $i => $mapped_key) {
                    $mapped_prop = $metadata->get_field_name($mapped_key);
                    $qb->and_where($qb->expr()->eq($mapped_alias . '.' . $mapped_prop, ':assoc' . $i));
                    $qb->set_parameter(':assoc' . $i, $wrapped_ubase->get_property_value($mapped_prop));
                }
            } else {
                $qb->and_where($qb->expr()->is_null('rec.' . $config['unique_base']));
            }
        }
        // include identifiers
        foreach ((array) $wrapped->get_identifier(false) as $id => $value) {
            if (!$meta->is_identifier($config['slug'])) {
                $named_id = str_replace('.', '_', $id);
                $qb->and_where($qb->expr()->neq('rec.' . $id, ':' . $named_id));
                $qb->set_parameter($named_id, $value, $meta->get_type_of_field($named_id));
            }
        }
        $query = $qb->get_query();
        $query->set_hydration_mode(Query::HYDRATE_ARRAY);
        // Force translation walker to look for slug translations to avoid duplicated slugs
        // TODO: Remove isset when removing support of YAML driver
        if (isset($config['uniqueOverTranslations']) && $config['uniqueOverTranslations'] && $object instanceof Translatable) {
            $query->set_hint(Query::HINT_CUSTOM_OUTPUT_WALKER, Translation_Walker::class);
        }
        return $query->get_array_result();
    }
    public function replace_relative($object, array $config, $target, $replacement)
    {
        $em = $this->get_object_manager();
        $qb = $em->create_query_builder();
        $qb->update($config['useObjectClass'], 'rec')->set('rec.' . $config['slug'], $qb->expr()->concat($qb->expr()->literal($replacement), $qb->expr()->substring('rec.' . $config['slug'], mb_strlen($target))))->where($qb->expr()->like('rec.' . $config['slug'], $qb->expr()->literal($target . '%')));
        // update in memory
        $q = $qb->get_query();
        return $q->execute();
    }
    public function replace_inverse_relative($object, array $config, $target, $replacement)
    {
        $em = $this->get_object_manager();
        $qb = $em->create_query_builder();
        $qb->update($config['useObjectClass'], 'rec')->set('rec.' . $config['slug'], $qb->expr()->concat($qb->expr()->literal($target), $qb->expr()->substring('rec.' . $config['slug'], mb_strlen($replacement) + 1)))->where($qb->expr()->like('rec.' . $config['slug'], $qb->expr()->literal($replacement . '%')));
        $q = $qb->get_query();
        return $q->execute();
    }
}