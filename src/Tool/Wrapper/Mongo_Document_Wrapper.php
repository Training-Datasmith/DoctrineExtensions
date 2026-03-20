<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tool\Wrapper;

use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Proxy_Manager\Proxy\Ghost_Object_Interface;
/**
 * Wraps document or proxy for more convenient
 * manipulation
 *
 * @template TObject of object
 *
 * @template-extends AbstractWrapper<ClassMetadata<TObject>, TObject, DocumentManager>
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Mongo_Document_Wrapper extends Abstract_Wrapper
{
    /**
     * Document identifier
     */
    private ?string $identifier = null;
    /**
     * Wrap document
     *
     * @param TObject $document
     */
    public function __construct($document, Document_Manager $dm)
    {
        $this->om = $dm;
        $this->object = $document;
        $this->meta = $dm->get_class_metadata(get_class($this->object));
    }
    public function get_property_value($property)
    {
        $this->initialize();
        return $this->meta->get_field_value($this->object, $property);
    }
    public function get_root_object_name()
    {
        return $this->meta->root_document_name;
    }
    public function set_property_value($property, $value): self
    {
        $this->initialize();
        $this->meta->set_field_value($this->object, $property, $value);
        return $this;
    }
    public function has_valid_identifier(): bool
    {
        return (bool) $this->get_identifier();
    }
    /**
     * @param bool $flatten
     */
    public function get_identifier($single = true, $flatten = false): string
    {
        if (!$this->identifier) {
            if ($this->object instanceof Ghost_Object_Interface) {
                $uow = $this->om->get_unit_of_work();
                if ($uow->is_in_identity_map($this->object)) {
                    $this->identifier = (string) $uow->get_document_identifier($this->object);
                } else {
                    $this->initialize();
                }
            }
            if (!$this->identifier) {
                $this->identifier = (string) $this->get_property_value($this->meta->identifier);
            }
        }
        return $this->identifier;
    }
    public function is_embedded_association($field)
    {
        return $this->get_metadata()->is_single_valued_embed($field);
    }
    /**
     * Initialize the document if it is proxy
     * required when is detached or not initialized
     *
     * @return void
     */
    protected function initialize()
    {
        if (method_exists($this->om, 'isUninitializedObject') && $this->om->is_uninitialized_object($this->object)) {
            $this->om->initialize_object($this->object);
            return;
        }
        // @todo: Drop support for this fallback when requiring `doctrine/mongodb-odm:^2.6 as a minimum`
        if ($this->object instanceof Ghost_Object_Interface && !$this->object->is_proxy_initialized()) {
            $this->om->initialize_object($this->object);
        }
    }
}