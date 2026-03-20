<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable\Filter;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Query\Filter\Sql_Filter;
use Gedmo\Soft_Deleteable\Soft_Deleteable_Listener;
/**
 * The SoftDeleteableFilter adds the condition necessary to
 * filter entities which were deleted "softly"
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Patrik Votoček <patrik@votocek.cz>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Soft_Deleteable_Filter extends Sql_Filter
{
    /**
     * @var SoftDeleteableListener
     */
    protected $listener;
    /**
     * @var EntityManagerInterface
     */
    protected $entity_manager;
    /**
     * @var array<string, bool>
     *
     * @phpstan-var array<class-string, bool>
     */
    protected $disabled = [];
    /**
     * @param string $targetTableAlias
     *
     * @throws Exception
     */
    public function add_filter_constraint(Class_Metadata $target_entity, $target_table_alias): string
    {
        $class = $target_entity->get_name();
        if (true === ($this->disabled[$class] ?? false)) {
            return '';
        }
        if (true === ($this->disabled[$target_entity->root_entity_name] ?? false)) {
            return '';
        }
        $config = $this->get_listener()->get_configuration($this->get_entity_manager(), $target_entity->name);
        if (!isset($config['softDeleteable']) || !$config['softDeleteable']) {
            return '';
        }
        $platform = $this->get_connection()->get_database_platform();
        $quote_strategy = $this->get_entity_manager()->get_configuration()->get_quote_strategy();
        $column = $quote_strategy->get_column_name($config['fieldName'], $target_entity, $platform);
        $add_cond_sql = $target_table_alias . '.' . $column . ' IS NULL';
        if (isset($config['timeAware']) && $config['timeAware']) {
            return "({$add_cond_sql} OR {$target_table_alias}.{$column} > {$platform->get_current_timestamp_sql()})";
        }
        return $add_cond_sql;
    }
    /**
     * @param string $class
     *
     * @phpstan-param class-string $class
     */
    public function disable_for_entity($class): void
    {
        $this->disabled[$class] = true;
        // Make sure the hash (@see SQLFilter::__toString()) for this filter will be changed to invalidate the query cache.
        $this->set_parameter(sprintf('disabled_%s', $class), true);
    }
    /**
     * @param string $class
     *
     * @phpstan-param class-string $class
     */
    public function enable_for_entity($class): void
    {
        $this->disabled[$class] = false;
        // Make sure the hash (@see SQLFilter::__toString()) for this filter will be changed to invalidate the query cache.
        $this->set_parameter(sprintf('disabled_%s', $class), false);
    }
    /**
     * @throws \RuntimeException
     *
     * @return SoftDeleteableListener
     */
    protected function get_listener()
    {
        if (null === $this->listener) {
            $em = $this->get_entity_manager();
            $evm = $em->get_event_manager();
            foreach ($evm->get_all_listeners() as $listeners) {
                foreach ($listeners as $listener) {
                    if ($listener instanceof Soft_Deleteable_Listener) {
                        $this->listener = $listener;
                        break 2;
                    }
                }
            }
            if (null === $this->listener) {
                throw new \RuntimeException('Listener "SoftDeleteableListener" was not added to the EventManager!');
            }
        }
        return $this->listener;
    }
    /**
     * @return EntityManagerInterface
     */
    protected function get_entity_manager()
    {
        if (null === $this->entity_manager) {
            $get_entity_manager = \Closure::bind(fn(): Entity_Manager_Interface => $this->em, $this, parent::class);
            $this->entity_manager = $get_entity_manager();
        }
        return $this->entity_manager;
    }
}