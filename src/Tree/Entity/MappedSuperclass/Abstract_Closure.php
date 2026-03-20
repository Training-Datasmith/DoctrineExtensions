<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Entity\Mapped_Superclass;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
/**
 * @ORM\MappedSuperclass
 */
#[ORM\Mapped_Superclass]
abstract class Abstract_Closure
{
    /**
     * @var int|null
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\Generated_Value(strategy: 'IDENTITY')]
    protected $id;
    /**
     * Mapped by listener
     * Visibility must be protected
     *
     * @var object|null
     */
    protected $ancestor;
    /**
     * Mapped by listener
     * Visibility must be protected
     *
     * @var object|null
     */
    protected $descendant;
    /**
     * @var int|null
     *
     * @ORM\Column(type="integer")
     */
    #[ORM\Column(type: Types::INTEGER)]
    protected $depth;
    /**
     * @return int|null
     */
    public function get_id()
    {
        return $this->id;
    }
    /**
     * Set ancestor
     *
     * @param object $ancestor
     *
     * @return static
     */
    public function set_ancestor($ancestor)
    {
        $this->ancestor = $ancestor;
        return $this;
    }
    /**
     * Get ancestor
     *
     * @return object|null
     */
    public function get_ancestor()
    {
        return $this->ancestor;
    }
    /**
     * Set descendant
     *
     * @param object $descendant
     *
     * @return static
     */
    public function set_descendant($descendant)
    {
        $this->descendant = $descendant;
        return $this;
    }
    /**
     * Get descendant
     *
     * @return object|null
     */
    public function get_descendant()
    {
        return $this->descendant;
    }
    /**
     * Set depth
     *
     * @param int $depth
     *
     * @return static
     */
    public function set_depth($depth)
    {
        $this->depth = $depth;
        return $this;
    }
    /**
     * Get depth
     *
     * @return int|null
     */
    public function get_depth()
    {
        return $this->depth;
    }
}