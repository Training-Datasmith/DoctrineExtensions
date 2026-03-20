<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Translatable\Entity\Mapped_Superclass\Abstract_Translation;
use Gedmo\Translatable\Entity\Repository\Translation_Repository;
/**
 * Gedmo\Translatable\Entity\Translation
 *
 * @ORM\Table(
 *     name="ext_translations",
 *     options={"row_format": "DYNAMIC"},
 *     uniqueConstraints={@ORM\UniqueConstraint(name="lookup_unique_idx", columns={
 *         "foreign_key", "locale", "object_class", "field"
 *     })}
 * )
 * @ORM\Entity(repositoryClass="Gedmo\Translatable\Entity\Repository\TranslationRepository")
 */
#[ORM\Entity(repositoryClass: Translation_Repository::class)]
#[ORM\Table(name: 'ext_translations', options: ['row_format' => 'DYNAMIC'])]
#[ORM\Unique_Constraint(name: 'lookup_unique_idx', columns: ['foreign_key', 'locale', 'object_class', 'field'])]
class Translation extends Abstract_Translation
{
    /*
     * All required columns are mapped through inherited superclass
     */
}