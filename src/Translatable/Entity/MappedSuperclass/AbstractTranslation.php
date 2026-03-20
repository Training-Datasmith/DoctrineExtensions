<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Entity\Mapped_Superclass;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
/**
 * Gedmo\Translatable\Entity\MappedSuperclass\AbstractTranslation
 *
 * @ORM\MappedSuperclass
 */
#[ORM\Mapped_Superclass]
abstract class Abstract_Translation
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\Generated_Value(strategy: 'IDENTITY')]
    protected $id;
    /**
     * @var string
     *
     * @ORM\Column(type="string", length=8)
     */
    #[ORM\Column(type: Types::STRING, length: 8)]
    protected $locale;
    /**
     * @var string
     *
     * @ORM\Column(name="object_class", type="string", length=191)
     */
    #[ORM\Column(name: 'object_class', type: Types::STRING, length: 191)]
    protected $object_class;
    /**
     * @var string
     *
     * @ORM\Column(type="string", length=32)
     */
    #[ORM\Column(type: Types::STRING, length: 32)]
    protected $field;
    /**
     * @var string
     *
     * @ORM\Column(name="foreign_key", type="string", length=64)
     */
    #[ORM\Column(name: 'foreign_key', type: Types::STRING, length: 64)]
    protected $foreign_key;
    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected $content;
    /**
     * Get id
     *
     * @return int $id
     */
    public function get_id()
    {
        return $this->id;
    }
    /**
     * Set locale
     *
     * @param string $locale
     *
     * @return static
     */
    public function set_locale($locale)
    {
        $this->locale = $locale;
        return $this;
    }
    /**
     * Get locale
     *
     * @return string
     */
    public function get_locale()
    {
        return $this->locale;
    }
    /**
     * Set field
     *
     * @param string $field
     *
     * @return static
     */
    public function set_field($field)
    {
        $this->field = $field;
        return $this;
    }
    /**
     * Get field
     *
     * @return string
     */
    public function get_field()
    {
        return $this->field;
    }
    /**
     * Set object class
     *
     * @param string $objectClass
     *
     * @return static
     */
    public function set_object_class($object_class)
    {
        $this->object_class = $object_class;
        return $this;
    }
    /**
     * Get objectClass
     *
     * @return string
     */
    public function get_object_class()
    {
        return $this->object_class;
    }
    /**
     * Set foreignKey
     *
     * @param string $foreignKey
     *
     * @return static
     */
    public function set_foreign_key($foreign_key)
    {
        $this->foreign_key = $foreign_key;
        return $this;
    }
    /**
     * Get foreignKey
     *
     * @return string
     */
    public function get_foreign_key()
    {
        return $this->foreign_key;
    }
    /**
     * Set content
     *
     * @param string $content
     *
     * @return static
     */
    public function set_content($content)
    {
        $this->content = $content;
        return $this;
    }
    /**
     * Get content
     *
     * @return string
     */
    public function get_content()
    {
        return $this->content;
    }
}