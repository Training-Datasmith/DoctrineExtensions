<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Document\Mapped_Superclass;

use Doctrine\ODM\Mongo_Db\Mapping\Annotations as MongoODM;
use Doctrine\ODM\Mongo_Db\Types\Type;
/**
 * Gedmo\Translatable\Document\AbstractPersonalTranslation
 *
 * @MongoODM\MappedSuperclass
 */
#[Mongo_Odm\Mapped_Superclass]
abstract class Abstract_Personal_Translation
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
     * @MongoODM\Field(type="string")
     */
    #[Mongo_Odm\Field(type: Type::STRING)]
    protected $locale;
    /**
     * Related document with ManyToOne relation
     * must be mapped by user
     *
     * @var object|null
     */
    protected $object;
    /**
     * @var string|null
     *
     * @MongoODM\Field(type="string")
     */
    #[Mongo_Odm\Field(type: Type::STRING)]
    protected $field;
    /**
     * @var string|null
     *
     * @MongoODM\Field(type="string")
     */
    #[Mongo_Odm\Field(type: Type::STRING)]
    protected $content;
    /**
     * Get id
     *
     * @return string|null $id
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
     * Get object related
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