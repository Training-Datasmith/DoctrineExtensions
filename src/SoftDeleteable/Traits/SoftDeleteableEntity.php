<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable\Traits;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
/**
 * Trait for soft-deletable objects.
 *
 * This implementation provides a mapping configuration for the Doctrine ORM.
 *
 * @author Wesley van Opdorp <wesley.van.opdorp@freshheads.com>
 */
trait Soft_Deleteable_Entity
{
    /**
     * @ORM\Column(type="datetime", nullable=true)
     *
     * @var \DateTime|null
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected $deleted_at;
    /**
     * Set or clear the deleted at timestamp.
     *
     * @return self
     */
    public function set_deleted_at(?\DateTime $deleted_at = null)
    {
        $this->deleted_at = $deleted_at;
        return $this;
    }
    /**
     * Get the deleted at timestamp value. Will return null if
     * the entity has not been soft deleted.
     *
     * @return \DateTime|null
     */
    public function get_deleted_at()
    {
        return $this->deleted_at;
    }
    /**
     * Check if the entity has been soft deleted.
     */
    public function is_deleted(): bool
    {
        return null !== $this->deleted_at;
    }
}