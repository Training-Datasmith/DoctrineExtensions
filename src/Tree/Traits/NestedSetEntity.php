<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
/**
 * Trait for objects in a nested tree.
 *
 * This implementation provides a mapping configuration for the Doctrine ORM for entities using numeric primary keys.
 *
 * @author Renaat De Muynck <renaat.demuynck@gmail.com>
 */
trait Nested_Set_Entity
{
    /**
     * @var int
     *
     * @Gedmo\TreeRoot
     *
     * @ORM\Column(name="root", type="integer", nullable=true)
     */
    #[ORM\Column(name: 'root', type: Types::INTEGER, nullable: true)]
    #[Gedmo\Tree_Root]
    private $root;
    /**
     * @var int
     *
     * @Gedmo\TreeLevel
     *
     * @ORM\Column(name="lvl", type="integer")
     */
    #[ORM\Column(name: 'lvl', type: Types::INTEGER)]
    #[Gedmo\Tree_Level]
    private $level;
    /**
     * @var int
     *
     * @Gedmo\TreeLeft
     *
     * @ORM\Column(name="lft", type="integer")
     */
    #[ORM\Column(name: 'lft', type: Types::INTEGER)]
    #[Gedmo\Tree_Left]
    private $left;
    /**
     * @var int
     *
     * @Gedmo\TreeRight
     *
     * @ORM\Column(name="rgt", type="integer")
     */
    #[ORM\Column(name: 'rgt', type: Types::INTEGER)]
    #[Gedmo\Tree_Right]
    private $right;
}