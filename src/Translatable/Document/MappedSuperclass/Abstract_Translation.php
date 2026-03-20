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
 * Gedmo\Translatable\Document\MappedSuperclass\AbstractTranslation
 *
 * @MongoODM\MappedSuperclass
 */
#[Mongo_Odm\Mapped_Superclass]
abstract class Abstract_Translation
{
    /**
     * @var int
     *
     * @MongoODM\Id
     */
    #[Mongo_Odm\Id]
    protected $id;
    /**
     * @var string
     *
     * @MongoODM\Field(type="string")
     */
    #[Mongo_Odm\Field(type: Type::STRING)]
    protected $locale;
    /**
     * @var string
     *
     * @MongoODM\Field(type="string")
     */
    #[Mongo_Odm\Field(type: Type::STRING)]
    protected $object_class;
    /**
     * @var string
     *
     * @MongoODM\Field(type="string")
     */
    #[Mongo_Odm\Field(type: Type::STRING)]
    protected $field;
    /**
     * @var string
     *
     * @MongoODM\Field(type="string", name="foreign_key")
     */
    #[Mongo_Odm\Field(name: 'foreign_key', type: Type::STRING)]
    protected $foreign_key;
    /**
     * @var string
     *
     * @MongoODM\Field(type="string")
     */
    #[Mongo_Odm\Field(type: Type::STRING)]
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