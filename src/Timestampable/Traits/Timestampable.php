<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Timestampable\Traits;

/**
 * Trait for timestampable objects.
 *
 * This implementation does not provide any mapping configurations.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
trait Timestampable
{
    /**
     * @var \DateTime|null
     */
    protected $created_at;
    /**
     * @var \DateTime|null
     */
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
     * @return \DateTime|null
     */
    public function get_updated_at()
    {
        return $this->updated_at;
    }
}