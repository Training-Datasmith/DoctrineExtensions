<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Blameable;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Abstract_Tracking_Listener;
use Gedmo\Blameable\Mapping\Event\Blameable_Adapter;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Tool\Actor_Provider_Interface;
/**
 * The Blameable listener handles the update of
 * dates on creation and update.
 *
 * @phpstan-extends AbstractTrackingListener<array, BlameableAdapter>
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Blameable_Listener extends Abstract_Tracking_Listener
{
    protected ?Actor_Provider_Interface $actor_provider = null;
    /**
     * @var mixed
     */
    protected $user;
    /**
     * Get the user value to set on a blameable field
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     * @param BlameableAdapter      $eventAdapter
     *
     * @return mixed
     */
    public function get_field_value($meta, $field, $event_adapter)
    {
        $actor = $this->actor_provider instanceof Actor_Provider_Interface ? $this->actor_provider->get_actor() : $this->user;
        if ($meta->has_association($field)) {
            if (null !== $actor && !is_object($actor)) {
                throw new InvalidArgumentException('Blame is reference, user must be an object');
            }
            return $actor;
        }
        // ok so it's not an association, then it is a string, or an object
        if (is_object($actor)) {
            if (method_exists($actor, 'getUserIdentifier')) {
                return (string) $actor->get_user_identifier();
            }
            if (method_exists($actor, 'getUsername')) {
                return (string) $actor->get_username();
            }
            if (method_exists($actor, '__toString')) {
                return $actor->__toString();
            }
            throw new InvalidArgumentException('Field expects string, user must be a string, or object should have method getUserIdentifier, getUsername or __toString');
        }
        return $actor;
    }
    /**
     * Set an actor provider for the user value.
     */
    public function set_actor_provider(Actor_Provider_Interface $actor_provider): void
    {
        $this->actor_provider = $actor_provider;
    }
    /**
     * Set a user value to return.
     *
     * If an actor provider is also provided, it will take precedence over this value.
     *
     * @param mixed $user
     */
    public function set_user_value($user): void
    {
        $this->user = $user;
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
}