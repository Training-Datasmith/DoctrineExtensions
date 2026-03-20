<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Strategy\ORM;

use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Mapping\Class_Metadata;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
use Gedmo\Tree\Strategy\Abstract_Materialized_Path;
/**
 * This strategy makes tree using materialized path strategy
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Materialized_Path extends Abstract_Materialized_Path
{
    /**
     * @param EntityManagerInterface $om
     * @param ClassMetadata<object>  $meta
     */
    public function remove_node($om, $meta, $config, $node): void
    {
        $wrapped = Abstract_Wrapper::wrap($node, $om);
        $path = addcslashes($wrapped->get_property_value($config['path']), '%');
        $separator = $config['path_ends_with_separator'] ? null : $config['path_separator'];
        // Remove node's children
        $qb = $om->create_query_builder();
        $qb->select('e')->from($config['useObjectClass'], 'e')->where($qb->expr()->like('e.' . $config['path'], $qb->expr()->literal($path . $separator . '%')));
        if (isset($config['level'])) {
            $lvl_field = $config['level'];
            $lvl = $wrapped->get_property_value($lvl_field);
            if (!empty($lvl)) {
                $qb->and_where($qb->expr()->gt('e.' . $lvl_field, $qb->expr()->literal($lvl)));
            }
        }
        $results = $qb->get_query()->to_iterable();
        foreach ($results as $node) {
            $om->remove($node);
        }
    }
    /**
     * @param EntityManagerInterface $om
     * @param ClassMetadata<object>  $meta
     */
    public function get_children($om, $meta, $config, $path)
    {
        $path = addcslashes($path, '%');
        $qb = $om->create_query_builder();
        $qb->select('e')->from($config['useObjectClass'], 'e')->where($qb->expr()->like('e.' . $config['path'], $qb->expr()->literal($path . '%')))->and_where('e.' . $config['path'] . ' != :path')->order_by('e.' . $config['path'], 'asc');
        // This may save some calls to updateNode
        $qb->set_parameter('path', $path);
        return $qb->get_query()->get_result();
    }
}