<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Blameable\Traits;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
/**
 * Trait for blamable objects.
 *
 * This implementation provides a mapping configuration for the Doctrine ORM.
 *
 * @author David Buchmann <mail@davidbu.ch>
 */
trait Blameable_Entity
{
    /**
     * @var string
     *
     * @Gedmo\Blameable(on="create")
     *
     * @ORM\Column(nullable=true)
     */
    #[ORM\Column(nullable: true)]
    #[Gedmo\Blameable(on: 'create')]
    protected $created_by;
    /**
     * @var string
     *
     * @Gedmo\Blameable(on="update")
     *
     * @ORM\Column(nullable=true)
     */
    #[ORM\Column(nullable: true)]
    #[Gedmo\Blameable(on: 'update')]
    protected $updated_by;
    /**
     * Sets createdBy.
     *
     * @param string $createdBy
     *
     * @return $this
     */
    public function set_created_by($created_by)
    {
        $this->created_by = $created_by;
        return $this;
    }
    /**
     * Returns createdBy.
     *
     * @return string
     */
    public function get_created_by()
    {
        return $this->created_by;
    }
    /**
     * Sets updatedBy.
     *
     * @param string $updatedBy
     *
     * @return $this
     */
    public function set_updated_by($updated_by)
    {
        $this->updated_by = $updated_by;
        return $this;
    }
    /**
     * Returns updatedBy.
     *
     * @return string
     */
    public function get_updated_by()
    {
        return $this->updated_by;
    }
}