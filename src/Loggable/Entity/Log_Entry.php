<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Loggable\Entity\Mapped_Superclass\Abstract_Log_Entry;
use Gedmo\Loggable\Entity\Repository\Log_Entry_Repository;
use Gedmo\Loggable\Loggable;
/**
 * Gedmo\Loggable\Entity\LogEntry
 *
 * @ORM\Table(
 *     name="ext_log_entries",
 *     options={"row_format": "DYNAMIC"},
 *     indexes={
 *         @ORM\Index(name="log_class_lookup_idx", columns={"object_class"}),
 *         @ORM\Index(name="log_date_lookup_idx", columns={"logged_at"}),
 *         @ORM\Index(name="log_user_lookup_idx", columns={"username"}),
 *         @ORM\Index(name="log_version_lookup_idx", columns={"object_id", "object_class", "version"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="Gedmo\Loggable\Entity\Repository\LogEntryRepository")
 *
 * @phpstan-template T of Loggable|object
 *
 * @phpstan-extends AbstractLogEntry<T>
 */
#[ORM\Entity(repositoryClass: Log_Entry_Repository::class)]
#[ORM\Table(name: 'ext_log_entries', options: ['row_format' => 'DYNAMIC'])]
#[ORM\Index(name: 'log_class_lookup_idx', columns: ['object_class'])]
#[ORM\Index(name: 'log_date_lookup_idx', columns: ['logged_at'])]
#[ORM\Index(name: 'log_user_lookup_idx', columns: ['username'])]
#[ORM\Index(name: 'log_version_lookup_idx', columns: ['object_id', 'object_class', 'version'])]
class Log_Entry extends Abstract_Log_Entry
{
    /*
     * All required columns are mapped through inherited superclass
     */
}