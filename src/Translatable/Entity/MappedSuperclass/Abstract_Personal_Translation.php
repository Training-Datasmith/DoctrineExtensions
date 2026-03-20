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
 * Gedmo\Translatable\Entity\MappedSuperclass\AbstractPersonalTranslation
 *
 * @ORM\MappedSuperclass
 */
#[ORM\Mapped_Superclass]
abstract class Abstract_Personal_Translation
{
    /**
     * @var int|null
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
     * @ORM\Column(type="string", length=32)
     */
    #[ORM\Column(type: Types::STRING, length: 32)]
    protected $field;
    /**
     * Related entity with ManyToOne relation
     * must be mapped by user
     *
     * @var object
     */
    protected $object;
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
     * @return int|null $id
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
     * @return string $field
     */
    public function get_field()
    {
        return $this->field;
    }
    /**
     * Set object related
     *
     * @param object $object
     *
     * @return static
     */
    public function set_object($object)
    {
        $this->object = $object;
        return $this;
    }
    /**
     * Get related object
     *
     * @return object
     */
    public function get_object()
    {
        return $this->object;
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