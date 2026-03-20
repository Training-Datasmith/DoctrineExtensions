<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tool\Wrapper;

use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Mapping\Class_Metadata;
use Gedmo\Tool\Class_Utils;
/**
 * Wraps entity or proxy for more convenient
 * manipulation
 *
 * @template TObject of object
 *
 * @template-extends AbstractWrapper<ClassMetadata<TObject>, TObject, EntityManagerInterface>
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Entity_Wrapper extends Abstract_Wrapper
{
    /**
     * Entity identifier
     *
     * @var array<string, mixed>|null
     */
    private $identifier;
    /**
     * Wrap entity
     *
     * @param TObject $entity
     */
    public function __construct($entity, Entity_Manager_Interface $em)
    {
        $this->om = $em;
        $this->object = $entity;
        $this->meta = $em->get_class_metadata(get_class($this->object));
    }
    public function get_property_value($property)
    {
        $this->initialize();
        return $this->meta->get_field_value($this->object, $property);
    }
    public function set_property_value($property, $value): self
    {
        $this->initialize();
        $this->meta->set_field_value($this->object, $property, $value);
        return $this;
    }
    public function has_valid_identifier(): bool
    {
        return null !== $this->get_identifier();
    }
    public function get_root_object_name()
    {
        return $this->meta->root_entity_name;
    }
    /**
     * @param bool $flatten
     */
    public function get_identifier($single = true, $flatten = false)
    {
        $flatten = 1 < \func_num_args() && true === func_get_arg(1);
        if (null === $this->identifier) {
            $uow = $this->om->get_unit_of_work();
            $this->identifier = $uow->is_in_identity_map($this->object) ? $uow->get_entity_identifier($this->object) : $this->meta->get_identifier_values($this->object);
            if (is_array($this->identifier) && empty($this->identifier)) {
                $this->identifier = null;
            }
        }
        if (is_array($this->identifier)) {
            if ($single) {
                return reset($this->identifier);
            }
            if ($flatten) {
                $id = $this->identifier;
                foreach ($id as $i => $value) {
                    if (is_object($value) && $this->om->get_metadata_factory()->has_metadata_for(Class_Utils::get_class($value))) {
                        $id[$i] = (new self($value, $this->om))->get_identifier(false, true);
                    }
                }
                return implode(' ', $id);
            }
        }
        return $this->identifier;
    }
    public function is_embedded_association($field): bool
    {
        return false;
    }
    /**
     * Initialize the entity if it is proxy
     * required when is detached or not initialized
     *
     * @return void
     */
    protected function initialize()
    {
        if ($this->om->is_uninitialized_object($this->object)) {
            $this->om->initialize_object($this->object);
        }
    }
}