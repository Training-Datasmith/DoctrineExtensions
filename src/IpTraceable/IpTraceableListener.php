<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Ip_Traceable;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Abstract_Tracking_Listener;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Ip_Traceable\Mapping\Event\Ip_Traceable_Adapter;
use Gedmo\Tool\Ip_Address_Provider_Interface;
/**
 * The IpTraceable listener handles the update of
 * IPs on creation and update.
 *
 * @phpstan-extends AbstractTrackingListener<array, IpTraceableAdapter>
 *
 * @author Pierre-Charles Bertineau <pc.bertineau@alterphp.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Ip_Traceable_Listener extends Abstract_Tracking_Listener
{
    protected ?Ip_Address_Provider_Interface $ip_address_provider = null;
    /**
     * @var string|null
     */
    protected $ip;
    /**
     * Get the IP address value to set on an IP address field
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     * @param IpTraceableAdapter    $eventAdapter
     *
     * @return string|null
     */
    public function get_field_value($meta, $field, $event_adapter)
    {
        if ($this->ip_address_provider instanceof Ip_Address_Provider_Interface) {
            return $this->ip_address_provider->get_address();
        }
        return $this->ip;
    }
    /**
     * Set an IP address provider for the IP address value.
     */
    public function set_ip_address_provider(Ip_Address_Provider_Interface $ip_address_provider): void
    {
        $this->ip_address_provider = $ip_address_provider;
    }
    /**
     * Set an IP address value to return.
     *
     * If an IP address provider is also provided, it will take precedence over this value.
     *
     * @param string|null $ip
     *
     * @throws InvalidArgumentException
     */
    public function set_ip_value($ip = null): void
    {
        if (isset($ip) && false === filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("ip address is not valid {$ip}");
        }
        $this->ip = $ip;
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
}