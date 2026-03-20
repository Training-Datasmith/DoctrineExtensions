<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Timestampable\Traits;

use Doctrine\ODM\Mongo_Db\Mapping\Annotations as ODM;
use Doctrine\ODM\Mongo_Db\Types\Type;
use Gedmo\Mapping\Annotation as Gedmo;
/**
 * Trait for timestampable objects.
 *
 * This implementation provides a mapping configuration for the Doctrine MongoDB ODM.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
trait Timestampable_Document
{
    /**
     * @var \DateTime|null
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ODM\Field(type="date")
     */
    #[Gedmo\Timestampable(on: 'create')]
    #[ODM\Field(type: Type::DATE)]
    protected $created_at;
    /**
     * @var \DateTime|null
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ODM\Field(type="date")
     */
    #[Gedmo\Timestampable(on: 'update')]
    #[ODM\Field(type: Type::DATE)]
    protected $updated_at;
    /**
     * Sets createdAt.
     *
     * @return $this
     */
    public function set_created_at(\DateTime $created_at)
    {
        $this->created_at = $created_at;
        return $this;
    }
    /**
     * Returns createdAt.
     *
     * @return \DateTime|null
     */
    public function get_created_at()
    {
        return $this->created_at;
    }
    /**
     * Sets updatedAt.
     *
     * @return $this
     */
    public function set_updated_at(\DateTime $updated_at)
    {
        $this->updated_at = $updated_at;
        return $this;
    }
    /**
     * Returns updatedAt.
     *
     * @return \Datetime|null
     */
    public function get_updated_at()
    {
        return $this->updated_at;
    }
}