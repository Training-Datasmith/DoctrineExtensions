<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tool\ORM\Hydration;

use Doctrine\ORM\Internal\Hydration\Abstract_Hydrator;
// The methods we need the compat bridge for are protected, so we're using a public method for this check
if ((new \ReflectionClass(Abstract_Hydrator::class))->get_method('onClear')->has_return_type()) {
    // ORM 3.x
    /**
     * Helper trait to address compatibility issues between ORM 2.x and 3.x.
     *
     * @mixin AbstractHydrator
     *
     * @internal
     */
    trait Hydrator_Compat
    {
        /**
         * Executes one-time preparation tasks, once each time hydration is started
         * through {@link hydrateAll} or {@link toIterable()}.
         */
        protected function prepare(): void
        {
            $this->do_prepare_with_compat();
        }
        protected function do_prepare_with_compat(): void
        {
            parent::prepare();
        }
        /**
         * Executes one-time cleanup tasks at the end of a hydration that was initiated
         * through {@link hydrateAll} or {@link toIterable()}.
         */
        protected function cleanup(): void
        {
            $this->do_cleanup_with_compat();
        }
        protected function do_cleanup_with_compat(): void
        {
            parent::cleanup();
        }
        /**
         * Hydrates all rows from the current statement instance at once.
         */
        protected function hydrate_all_data(): array
        {
            return $this->do_hydrate_all_data();
        }
        /**
         * @return mixed[]
         */
        protected function do_hydrate_all_data()
        {
            return parent::hydrate_all_data();
        }
    }
} else {
    // ORM 2.x
    /**
     * Helper trait to address compatibility issues between ORM 2.x and 3.x.
     *
     * @mixin AbstractHydrator
     *
     * @internal
     */
    trait Hydrator_Compat
    {
        /**
         * Executes one-time preparation tasks, once each time hydration is started
         * through {@link hydrateAll} or {@link toIterable()}.
         *
         * @return void
         */
        protected function prepare()
        {
            $this->do_prepare_with_compat();
        }
        protected function do_prepare_with_compat(): void
        {
            parent::prepare();
        }
        /**
         * Executes one-time cleanup tasks at the end of a hydration that was initiated
         * through {@link hydrateAll} or {@link toIterable()}.
         *
         * @return void
         */
        protected function cleanup()
        {
            $this->do_cleanup_with_compat();
        }
        protected function do_cleanup_with_compat(): void
        {
            parent::cleanup();
        }
        /**
         * Hydrates all rows from the current statement instance at once.
         *
         * @return mixed[]
         */
        protected function hydrate_all_data()
        {
            return $this->do_hydrate_all_data();
        }
        /**
         * @return mixed[]
         */
        protected function do_hydrate_all_data()
        {
            return parent::hydrate_all_data();
        }
    }
}