<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Document;

use Doctrine\ODM\Mongo_Db\Mapping\Annotations as ODM;
use Gedmo\Translatable\Document\Mapped_Superclass\Abstract_Translation;
use Gedmo\Translatable\Document\Repository\Translation_Repository;
/**
 * Gedmo\Translatable\Document\Translation
 *
 * @ODM\Document(repositoryClass="Gedmo\Translatable\Document\Repository\TranslationRepository")
 * @ODM\UniqueIndex(name="lookup_unique_idx", keys={
 *     "foreign_key": "asc",
 *     "locale": "asc",
 *     "object_class": "asc",
 *     "field": "asc"
 * })
 */
#[ODM\Document(repositoryClass: Translation_Repository::class)]
#[ODM\Unique_Index(name: 'lookup_unique_idx', keys: ['foreign_key' => 'asc', 'locale' => 'asc', 'object_class' => 'asc', 'field' => 'asc'])]
class Translation extends Abstract_Translation
{
    /*
     * All required columns are mapped through inherited superclass
     */
}