<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sortable\Entity\Repository;

use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Entity_Repository;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query_Builder;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Sortable\Sortable_Listener;
/**
 * Sortable Repository
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @template T of object
 *
 * @template-extends EntityRepository<T>
 */
class Sortable_Repository extends Entity_Repository
{
    /**
     * Sortable listener on event manager
     */
    protected \Gedmo\Sortable\Sortable_Listener $listener;
    /**
     * @var array<string, mixed>
     */
    protected $config;
    /**
     * @var ClassMetadata<T>
     */
    protected $meta;
    /**
     * @param ClassMetadata<T> $class
     */
    public function __construct(Entity_Manager_Interface $em, Class_Metadata $class)
    {
        parent::__construct($em, $class);
        $sortable_listener = null;
        foreach ($em->get_event_manager()->get_all_listeners() as $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof Sortable_Listener) {
                    $sortable_listener = $listener;
                    break 2;
                }
            }
        }
        if (null === $sortable_listener) {
            throw new Invalid_Mapping_Exception('This repository can be attached only to ORM sortable listener');
        }
        $this->listener = $sortable_listener;
        $this->meta = $this->get_class_metadata();
        $this->config = $this->listener->get_configuration($this->get_entity_manager(), $this->meta->get_name());
    }
    /**
     * @param array<string, mixed> $groupValues
     *
     * @return Query
     */
    public function get_by_sortable_groups_query(array $group_values = [])
    {
        return $this->get_by_sortable_groups_query_builder($group_values)->get_query();
    }
    /**
     * @param array<string, mixed> $groupValues
     *
     * @return QueryBuilder
     */
    public function get_by_sortable_groups_query_builder(array $group_values = [])
    {
        $groups = isset($this->config['groups']) ? array_combine(array_values($this->config['groups']), array_keys($this->config['groups'])) : [];
        foreach ($group_values as $name => $value) {
            if (!in_array($name, $this->config['groups'], true)) {
                throw new \InvalidArgumentException('Sortable group "' . $name . '" is not defined in Entity ' . $this->meta->get_name());
            }
            unset($groups[$name]);
        }
        if ([] !== $groups) {
            throw new \InvalidArgumentException('You need to specify values for the following groups to select by sortable groups: ' . implode(', ', array_keys($groups)));
        }
        $qb = $this->create_query_builder('n');
        $qb->order_by('n.' . $this->config['position']);
        $i = 1;
        foreach ($group_values as $group => $value) {
            $qb->and_where('n.' . $group . ' = :group' . $i)->set_parameter('group' . $i, $value);
            ++$i;
        }
        return $qb;
    }
    /**
     * @param array<string, mixed> $groupValues
     *
     * @return array<int, object>
     */
    public function get_by_sortable_groups(array $group_values = [])
    {
        $query = $this->get_by_sortable_groups_query($group_values);
        return $query->get_result();
    }
}