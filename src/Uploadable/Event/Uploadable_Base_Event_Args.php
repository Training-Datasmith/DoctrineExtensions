<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Uploadable\Event;

use Doctrine\Common\Event_Args;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Uploadable\File_Info\File_Info_Interface;
use Gedmo\Uploadable\Uploadable_Listener;
/**
 * Abstract Base Event to be extended by Uploadable events
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
abstract class Uploadable_Base_Event_Args extends Event_Args
{
    /**
     * The instance of the Uploadable listener that fired this event
     */
    private Uploadable_Listener $uploadable_listener;
    private Entity_Manager_Interface $em;
    /**
     * The Uploadable entity
     *
     * @var object
     */
    private $entity;
    /**
     * The configuration of the Uploadable extension for this entity class
     *
     * @todo Check if this property must be removed, as it is never set.
     *
     * @var array
     */
    private $extension_configuration;
    private File_Info_Interface $file_info;
    /**
     * Is the file being created, updated or removed?
     * This value can be: CREATE, UPDATE or DELETE
     *
     * @var string
     */
    private $action;
    /**
     * @param object $entity
     * @param string $action
     */
    public function __construct(Uploadable_Listener $listener, Entity_Manager_Interface $em, array $config, File_Info_Interface $file_info, $entity, $action)
    {
        $this->uploadable_listener = $listener;
        $this->em = $em;
        $this->file_info = $file_info;
        $this->entity = $entity;
        $this->action = $action;
    }
    /**
     * Retrieve the associated listener
     *
     * @return UploadableListener
     */
    public function get_listener()
    {
        return $this->uploadable_listener;
    }
    /**
     * Retrieve associated EntityManager
     *
     * @return EntityManagerInterface
     */
    public function get_entity_manager()
    {
        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2639', '"%s()" is deprecated since gedmo/doctrine-extensions 3.14 and will be removed in version 4.0.', __METHOD__);
        return $this->em;
    }
    /**
     * Retrieve associated EntityManager
     *
     * @return ObjectManager
     */
    public function get_object_manager()
    {
        return $this->em;
    }
    /**
     * Retrieve associated Entity
     *
     * @return object
     */
    public function get_entity()
    {
        Deprecation::trigger('gedmo/doctrine-extensions', 'https://github.com/doctrine-extensions/DoctrineExtensions/pull/2639', '"%s()" is deprecated since gedmo/doctrine-extensions 3.14 and will be removed in version 4.0.', __METHOD__);
        return $this->entity;
    }
    /**
     * Retrieve associated Object
     *
     * @return object
     */
    public function get_object()
    {
        return $this->entity;
    }
    /**
     * Retrieve associated Uploadable extension configuration
     *
     * @return array
     */
    public function get_extension_configuration()
    {
        return $this->extension_configuration;
    }
    /**
     * Retrieve the FileInfo associated with this entity.
     *
     * @return FileInfoInterface
     */
    public function get_file_info()
    {
        return $this->file_info;
    }
    /**
     * Retrieve the action being performed to the entity: CREATE, UPDATE or DELETE
     *
     * @return string
     */
    public function get_action()
    {
        return $this->action;
    }
}