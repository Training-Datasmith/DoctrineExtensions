<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tool\ORM\Walker;

use Doctrine\ORM\Query\Sql_Walker;
if ((new \ReflectionClass(Sql_Walker::class))->get_method('getExecutor')->has_return_type()) {
    /**
     * Helper trait to address compatibility issues between ORM 2.x and 3.x.
     *
     * @mixin SqlWalker
     *
     * @internal
     */
    trait Sql_Walker_Compat
    {
        use Sql_Walker_Compat_For_Orm3;
    }
} else {
    /**
     * Helper trait to address compatibility issues between ORM 2.x and 3.x.
     *
     * @mixin SqlWalker
     *
     * @internal
     */
    trait Sql_Walker_Compat
    {
        use Sql_Walker_Compat_For_Orm2;
    }
}