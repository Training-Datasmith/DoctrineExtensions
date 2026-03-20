<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Ip_Traceable\Traits;

use Doctrine\ODM\Mongo_Db\Mapping\Annotations as ODM;
use Doctrine\ODM\Mongo_Db\Types\Type;
use Gedmo\Mapping\Annotation as Gedmo;
/**
 * Trait for IP traceable objects.
 *
 * This implementation provides a mapping configuration for the Doctrine MongoDB ODM.
 *
 * @author Pierre-Charles Bertineau <pc.bertineau@alterphp.com>
 */
trait Ip_Traceable_Document
{
    /**
     * @var string
     *
     * @Gedmo\IpTraceable(on="create")
     *
     * @ODM\Field(type="string")
     */
    #[ODM\Field(type: Type::STRING)]
    #[Gedmo\Ip_Traceable(on: 'create')]
    protected $created_from_ip;
    /**
     * @var string
     *
     * @Gedmo\IpTraceable(on="update")
     *
     * @ODM\Field(type="string")
     */
    #[ODM\Field(type: Type::STRING)]
    #[Gedmo\Ip_Traceable(on: 'update')]
    protected $updated_from_ip;
    /**
     * Sets createdFromIp.
     *
     * @param string $createdFromIp
     *
     * @return $this
     */
    public function set_created_from_ip($created_from_ip)
    {
        $this->created_from_ip = $created_from_ip;
        return $this;
    }
    /**
     * Returns createdFromIp.
     *
     * @return string
     */
    public function get_created_from_ip()
    {
        return $this->created_from_ip;
    }
    /**
     * Sets updatedFromIp.
     *
     * @param string $updatedFromIp
     *
     * @return $this
     */
    public function set_updated_from_ip($updated_from_ip)
    {
        $this->updated_from_ip = $updated_from_ip;
        return $this;
    }
    /**
     * Returns updatedFromIp.
     *
     * @return string
     */
    public function get_updated_from_ip()
    {
        return $this->updated_from_ip;
    }
}