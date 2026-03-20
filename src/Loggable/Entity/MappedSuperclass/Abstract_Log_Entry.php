<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable\Entity\Mapped_Superclass;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Loggable\Log_Entry_Interface;
use Gedmo\Loggable\Loggable;
/**
 * @phpstan-template T of Loggable|object
 *
 * @phpstan-implements LogEntryInterface<T>
 *
 * @ORM\MappedSuperclass
 */
#[ORM\Mapped_Superclass]
abstract class Abstract_Log_Entry implements Log_Entry_Interface
{
    /**
     * @var int|null
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\Generated_Value]
    protected $id;
    /**
     * @var string|null
     *
     * @phpstan-var self::ACTION_CREATE|self::ACTION_UPDATE|self::ACTION_REMOVE|null
     *
     * @ORM\Column(type="string", length=8)
     */
    #[ORM\Column(type: Types::STRING, length: 8)]
    protected $action;
    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="logged_at", type="datetime")
     */
    #[ORM\Column(name: 'logged_at', type: Types::DATETIME_MUTABLE)]
    protected $logged_at;
    /**
     * @var string|null
     *
     * @ORM\Column(name="object_id", length=64, nullable=true)
     */
    #[ORM\Column(name: 'object_id', length: 64, nullable: true)]
    protected $object_id;
    /**
     * @var string|null
     *
     * @phpstan-var class-string<T>|null
     *
     * @ORM\Column(name="object_class", type="string", length=191)
     */
    #[ORM\Column(name: 'object_class', type: Types::STRING, length: 191)]
    protected $object_class;
    /**
     * @var int|null
     *
     * @ORM\Column(type="integer")
     */
    #[ORM\Column(type: Types::INTEGER)]
    protected $version;
    /**
     * @var array<string, mixed>|null
     *
     * @ORM\Column(type="array", nullable=true)
     *
     * NOTE: The attribute uses the "array" name directly instead of the constant since it was removed in DBAL 4.0.
     */
    #[ORM\Column(type: 'array', nullable: true)]
    protected $data;
    /**
     * @var string|null
     *
     * @ORM\Column(length=191, nullable=true)
     */
    #[ORM\Column(length: 191, nullable: true)]
    protected $username;
    /**
     * Get id
     *
     * @return int|null
     */
    public function get_id()
    {
        return $this->id;
    }
    /**
     * Get action
     */
    public function get_action()
    {
        return $this->action;
    }
    /**
     * Set action
     */
    public function set_action($action): void
    {
        $this->action = $action;
    }
    /**
     * Get object class
     */
    public function get_object_class()
    {
        return $this->object_class;
    }
    /**
     * Set object class
     */
    public function set_object_class($object_class): void
    {
        $this->object_class = $object_class;
    }
    /**
     * Get object id
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
     */
    public function get_data()
    {
        return $this->data;
    }
    /**
     * Set data
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
     */
    public function get_version()
    {
        return $this->version;
    }
}