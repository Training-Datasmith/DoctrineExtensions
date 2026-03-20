<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tool\ORM\Repository;

use Doctrine\ORM\Entity_Repository;
if ((new \ReflectionClass(Entity_Repository::class))->get_method('__call')->has_return_type()) {
    // ORM 3.x
    /**
     * Helper trait to address compatibility issues between ORM 2.x and 3.x.
     *
     * @mixin EntityRepository
     *
     * @internal
     */
    trait Entity_Repository_Compat
    {
        /**
         * @phpstan-param list<mixed> $args
         */
        public function __call(string $method, array $args): mixed
        {
            return $this->do_call_with_compat($method, $args);
        }
        /**
         * @param string $method
         * @param array  $args
         *
         * @phpstan-param list<mixed> $args
         *
         * @return mixed
         */
        abstract protected function do_call_with_compat($method, $args);
    }
} else {
    // ORM 2.x
    /**
     * Helper trait to address compatibility issues between ORM 2.x and 3.x.
     *
     * @mixin EntityRepository
     *
     * @internal
     */
    trait Entity_Repository_Compat
    {
        /**
         * @param string $method
         * @param array  $args
         *
         * @phpstan-param list<mixed> $args
         *
         * @return mixed
         */
        public function __call($method, $args)
        {
            return $this->do_call_with_compat($method, $args);
        }
        /**
         * @param string $method
         * @param array  $args
         *
         * @phpstan-param list<mixed> $args
         *
         * @return mixed
         */
        abstract protected function do_call_with_compat($method, $args);
    }
}