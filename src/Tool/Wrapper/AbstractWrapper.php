<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tool\Wrapper;

use Doctrine\Deprecations\Deprecation;
use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\Unsupported_Object_Manager_Exception;
use Gedmo\Tool\Wrapper_Interface;
/**
 * Wraps entity or proxy for more convenient
 * manipulation
 *
 * @template TClassMetadata of ClassMetadata<TObject>
 * @template TObject        of object
 * @template TObjectManager of ObjectManager
 *
 * @template-implements WrapperInterface<TClassMetadata, TObject, TObjectManager>
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
abstract class Abstract_Wrapper implements Wrapper_Interface
{
    /**
     * Object metadata
     *
     * @var TClassMetadata
     */
    protected $meta;
    /**
     * Wrapped object
     *
     * @var TObject
     */
    protected $object;
    /**
     * Object manager instance
     *
     * @var TObjectManager
     */
    protected $om;
    /**
     * Wrap object factory method
     *
     * @param TObject        $object
     * @param TObjectManager $om
     *
     * @psalm-param object        $object
     * @psalm-param ObjectManager $om
     *
     * @throws UnsupportedObjectManagerException
     *
     * @return WrapperInterface<TClassMetadata, TObject, TObjectManager>
     */
    public static function wrap($object, Object_Manager $om)
    {
        if ($om instanceof Entity_Manager_Interface) {
            return new Entity_Wrapper($object, $om);
        }
        if ($om instanceof Document_Manager) {
            return new Mongo_Document_Wrapper($object, $om);
        }
        throw new Unsupported_Object_Manager_Exception('Given object manager is not managed by wrapper');
    }
    public static function clear(): void
    {
        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2410', 'Using "%s()" method is deprecated since gedmo/doctrine-extensions 3.5 and will be removed in version 4.0.', __METHOD__);
    }
    /**
     * @return TObject
     */
    public function get_object()
    {
        return $this->object;
    }
    /**
     * @return TClassMetadata
     */
    public function get_metadata()
    {
        return $this->meta;
    }
    public function populate(array $data)
    {
        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2410', 'Using "%s()" method is deprecated since gedmo/doctrine-extensions 3.5 and will be removed in version 4.0.', __METHOD__);
        foreach ($data as $field => $value) {
            $this->set_property_value($field, $value);
        }
        return $this;
    }
}