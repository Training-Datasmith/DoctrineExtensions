<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Hydrator\ORM;

use Doctrine\ORM\Internal\Hydration\Simple_Object_Hydrator as BaseSimpleObjectHydrator;
use Gedmo\Exception\RuntimeException;
use Gedmo\Tool\ORM\Hydration\Entity_Manager_Retriever;
use Gedmo\Tool\ORM\Hydration\Hydrator_Compat;
use Gedmo\Translatable\Translatable_Listener;
/**
 * If query uses TranslationQueryWalker and is hydrating
 * objects - when it requires this custom object hydrator
 * in order to skip onLoad event from triggering retranslation
 * of the fields
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Simple_Object_Hydrator extends Base_Simple_Object_Hydrator
{
    use Entity_Manager_Retriever;
    use Hydrator_Compat;
    /**
     * State of skipOnLoad for listener between hydrations
     *
     * @see SimpleObjectHydrator::prepare()
     * @see SimpleObjectHydrator::cleanup()
     */
    private ?bool $saved_skip_on_load = null;
    protected function do_prepare_with_compat(): void
    {
        $listener = $this->get_translatable_listener();
        $this->saved_skip_on_load = $listener->is_skip_on_load();
        $listener->set_skip_on_load(true);
        parent::prepare();
    }
    protected function do_cleanup_with_compat(): void
    {
        parent::cleanup();
        $listener = $this->get_translatable_listener();
        $listener->set_skip_on_load($this->saved_skip_on_load ?? false);
    }
    /**
     * Get the currently used TranslatableListener
     *
     * @throws RuntimeException if listener is not found
     *
     * @return TranslatableListener
     */
    protected function get_translatable_listener()
    {
        foreach ($this->get_entity_manager()->get_event_manager()->get_all_listeners() as $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof Translatable_Listener) {
                    return $listener;
                }
            }
        }
        throw new RuntimeException('The translation listener could not be found');
    }
}