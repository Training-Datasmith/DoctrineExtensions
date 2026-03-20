<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable\Document\Mapped_Superclass;

use Doctrine\ODM\Mongo_Db\Mapping\Annotations as MongoODM;
use Doctrine\ODM\Mongo_Db\Types\Type;
use Gedmo\Loggable\Log_Entry_Interface;
use Gedmo\Loggable\Loggable;
/**
 * @phpstan-template T of Loggable|object
 *
 * @phpstan-implements LogEntryInterface<T>
 *
 * @MongoODM\MappedSuperclass
 */
#[Mongo_Odm\Mapped_Superclass]
abstract class Abstract_Log_Entry implements Log_Entry_Interface
{
    /**
     * @var string|null
     *
     * @MongoODM\Id
     */
    #[Mongo_Odm\Id]
    protected $id;
    /**
     * @var string|null
     *
     * @phpstan-var self::ACTION_CREATE|self::ACTION_UPDATE|self::ACTION_REMOVE|null
     *
     * @MongoODM\Field(type="string")
     */
    #[Mongo_Odm\Field(type: Type::STRING)]
    protected $action;
    /**
     * @var \DateTime|null
     *
     * @MongoODM\Field(type="date")
     */
    #[Mongo_Odm\Field(type: Type::DATE)]
    protected $logged_at;
    /**
     * @var string|null
     *
     * @MongoODM\Field(type="string", nullable=true)
     */
    #[Mongo_Odm\Field(type: Type::STRING, nullable: true)]
    protected $object_id;
    /**
     * @var string|null
     *
     * @phpstan-var class-string<T>|null
     *
     * @MongoODM\Field(type="string")
     */
    #[Mongo_Odm\Field(type: Type::STRING)]
    protected $object_class;
    /**
     * @var int|null
     *
     * @MongoODM\Field(type="int")
     */
    #[Mongo_Odm\Field(type: Type::INT)]
    protected $version;
    /**
     * @var array<string, mixed>|null
     *
     * @MongoODM\Field(type="hash", nullable=true)
     */
    #[Mongo_Odm\Field(type: Type::HASH, nullable: true)]
    protected $data;
    /**
     * @var string|null
     *
     * @MongoODM\Field(type="string", nullable=true)
     */
    #[Mongo_Odm\Field(type: Type::STRING, nullable: true)]
    protected $username;
    /**
     * Get id
     *
     * @return string|null
     */
    public function get_id()
    {
        return $this->id;
    }
    /**
     * Get action
     *
     * @return string|null
     */
    public function get_action()
    {
        return $this->action;
    }
    /**
     * Set action
     *
     * @param string $action
     */
    public function set_action($action): void
    {
        $this->action = $action;
    }
    /**
     * Get object class
     *
     * @return string|null
     */
    public function get_object_class()
    {
        return $this->object_class;
    }
    /**
     * Set object class
     *
     * @param string $objectClass
     */
    public function set_object_class($object_class): void
    {
        $this->object_class = $object_class;
    }
    /**
     * Get object id
     *
     * @return string|null
     */
    public function get_object_id()
    {
        return $this->object_id;
    }
    /**
     * Set object id
     *
     * @param string $objectId
     */
    public function set_object_id($object_id): void
    {
        $this->object_id = $object_id;
    }
    /**
     * Get username
     *
     * @return string|null
     */
    public function get_username()
    {
        return $this->username;
    }
    /**
     * Set username
     *
     * @param string $username
     */
    public function set_username($username): void
    {
        $this->username = $username;
    }
    /**
     * Get loggedAt
     *
     * @return \DateTime|null
     */
    public function get_logged_at()
    {
        return $this->logged_at;
    }
    /**
     * Set loggedAt to "now"
     */
    public function set_logged_at(): void
    {
        $this->logged_at = new \DateTime();
    }
    /**
     * Get data
     *
     * @return array<string, mixed>|null
     */
    public function get_data()
    {
        return $this->data;
    }
    /**
     * Set data
     *
     * @param array<string, mixed> $data
     */
    public function set_data($data): void
    {
        $this->data = $data;
    }
    /**
     * Set current version
     *
     * @param int $version
     */
    public function set_version($version): void
    {
        $this->version = $version;
    }
    /**
     * Get current version
     *
     * @return int|null
     */
    public function get_version()
    {
        return $this->version;
    }
}