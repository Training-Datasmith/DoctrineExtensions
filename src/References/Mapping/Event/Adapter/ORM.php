<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\References\Mapping\Event\Adapter;

use Doctrine\ODM\Mongo_Db\Document_Manager as MongoDocumentManager;
use Doctrine\ODM\PHPCR\Document_Manager as PhpcrDocumentManager;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\Persistence\Proxy as PersistenceProxy;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Mapping\Event\Adapter\ORM as BaseAdapterORM;
use Gedmo\References\Mapping\Event\References_Adapter;
use Proxy_Manager\Proxy\Ghost_Object_Interface;
/**
 * Doctrine event adapter for ORM references behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
final class ORM extends Base_Adapter_Orm implements References_Adapter
{
    public function get_identifier($om, $object, $single = true)
    {
        if ($om instanceof Entity_Manager_Interface) {
            return $this->extract_identifier($om, $object, $single);
        }
        if ($om instanceof Mongo_Document_Manager) {
            $meta = $om->get_class_metadata(get_class($object));
            if ($object instanceof Ghost_Object_Interface) {
                $id = $om->get_unit_of_work()->get_document_identifier($object);
            } else {
                $id = $meta->get_field_value($object, $meta->get_identifier()[0]);
            }
            if ($single || !$id) {
                return $id;
            }
            return [$meta->get_identifier()[0] => $id];
        }
        if ($om instanceof Phpcr_Document_Manager) {
            $meta = $om->get_class_metadata(get_class($object));
            assert(1 === count($meta->get_identifier()));
            $id = $meta->get_field_value($object, $meta->get_identifier()[0]);
            if ($single || !$id) {
                return $id;
            }
            return [$meta->get_identifier()[0] => $id];
        }
        return null;
    }
    public function get_single_reference($om, $class, $identifier)
    {
        $this->throw_if_not_document_manager($om);
        $meta = $om->get_class_metadata($class);
        if (!$om instanceof Mongo_Document_Manager) {
            return $om->get_reference($class, $identifier);
        }
        if (!$meta->is_inheritance_type_none()) {
            return $om->find($class, $identifier);
        }
        return $om->get_reference($class, $identifier);
    }
    public function extract_identifier($om, $object, $single = true)
    {
        if ($object instanceof Persistence_Proxy) {
            $id = $om->get_unit_of_work()->get_entity_identifier($object);
        } else {
            $meta = $om->get_class_metadata(get_class($object));
            $id = [];
            foreach ($meta->get_identifier() as $name) {
                $id[$name] = $meta->get_field_value($object, $name);
                // return null if one of identifiers is missing
                if (!$id[$name]) {
                    return null;
                }
            }
        }
        if ($single) {
            return current($id);
        }
        return $id;
    }
    /**
     * Override so we don't get an exception. We want to allow this.
     *
     * @param mixed $dm
     *
     * @phpstan-assert MongoDocumentManager|PhpcrDocumentManager $dm
     */
    private function throw_if_not_document_manager($dm): void
    {
        if (!$dm instanceof Mongo_Document_Manager && !$dm instanceof Phpcr_Document_Manager) {
            throw new InvalidArgumentException(sprintf('Expected a %s or %s instance but got "%s"', Mongo_Document_Manager::class, 'Doctrine\ODM\PHPCR\DocumentManager', is_object($dm) ? get_class($dm) : gettype($dm)));
        }
    }
}