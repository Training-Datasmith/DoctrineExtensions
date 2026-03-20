<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable\Filter\ODM;

use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Doctrine\ODM\Mongo_Db\Query\Filter\Bson_Filter;
use Gedmo\Soft_Deleteable\Soft_Deleteable_Listener;
/**
 * @final since gedmo/doctrine-extensions 3.11
 */
class Soft_Deleteable_Filter extends Bson_Filter
{
    /**
     * @var SoftDeleteableListener|null
     */
    protected $listener;
    /**
     * @var DocumentManager|null
     *
     * @deprecated `BsonFilter::$dm` is a protected property, thus this property is not required
     */
    protected $document_manager;
    /**
     * @var array<string, bool>
     */
    protected $disabled = [];
    /**
     * Gets the criteria part to add to a query.
     *
     * @return array<string, array<int, array<string, array<string, \DateTime>|null>>|null> The criteria array, if there is available, empty array otherwise
     *
     * @phpstan-return array<string, array<int, array<string, array{'$gt': \DateTime}|null>>|null>
     */
    public function add_filter_criteria(Class_Metadata $target_entity): array
    {
        $class = $target_entity->get_name();
        if (true === ($this->disabled[$class] ?? false)) {
            return [];
        }
        if (true === ($this->disabled[$target_entity->root_document_name] ?? false)) {
            return [];
        }
        $config = $this->get_listener()->get_configuration($this->get_document_manager(), $target_entity->name);
        if (!isset($config['softDeleteable']) || !$config['softDeleteable']) {
            return [];
        }
        $column = $target_entity->get_field_mapping($config['fieldName']);
        if (isset($config['timeAware']) && $config['timeAware']) {
            return ['$or' => [[$column['fieldName'] => null], [$column['fieldName'] => ['$gt' => new \DateTime()]]]];
        }
        return [$column['fieldName'] => null];
    }
    /**
     * @param string $class
     *
     * @phpstan-param class-string $class
     */
    public function disable_for_document($class): void
    {
        $this->disabled[$class] = true;
    }
    /**
     * @param string $class
     *
     * @phpstan-param class-string $class
     */
    public function enable_for_document($class): void
    {
        $this->disabled[$class] = false;
    }
    /**
     * @return SoftDeleteableListener
     */
    protected function get_listener()
    {
        if (null === $this->listener) {
            $em = $this->get_document_manager();
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
     * @return DocumentManager
     */
    protected function get_document_manager()
    {
        // Remove the following assignment on the next major release.
        $this->document_manager = $this->dm;
        return $this->dm;
    }
}