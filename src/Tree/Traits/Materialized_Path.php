<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tree\Traits;

use Doctrine\Common\Collections\Array_Collection;
use Doctrine\Common\Collections\Collection;
/**
 * Trait for objects in a materialized path tree.
 *
 *  This implementation does not provide any mapping configurations.
 *
 * @author Steffen Roßkamp <steffen.rosskamp@gimmickmedia.de>
 */
trait Materialized_Path
{
    /**
     * @var string
     */
    protected $path;
    /**
     * @var self|null
     */
    protected $parent;
    /**
     * @var int
     */
    protected $level;
    /**
     * @var Collection<int, self>|self[]|null
     */
    protected $children;
    /**
     * @var string
     */
    protected $hash;
    /**
     * @return self
     */
    public function set_parent(?self $parent = null)
    {
        $this->parent = $parent;
        return $this;
    }
    /**
     * @return self
     */
    public function get_parent()
    {
        return $this->parent;
    }
    /**
     * @param string $path
     *
     * @return self
     */
    public function set_path($path)
    {
        $this->path = $path;
        return $this;
    }
    /**
     * @return string
     */
    public function get_path()
    {
        return $this->path;
    }
    /**
     * @return int
     */
    public function get_level()
    {
        return $this->level;
    }
    /**
     * @param string $hash
     *
     * @return self
     */
    public function set_hash($hash)
    {
        $this->hash = $hash;
        return $this;
    }
    /**
     * @return string
     */
    public function get_hash()
    {
        return $this->hash;
    }
    /**
     * @param Collection<int, self>|self[] $children
     *
     * @return self
     */
    public function set_children($children)
    {
        $this->children = $children;
        return $this;
    }
    /**
     * @return Collection<int, self>|self[]
     */
    public function get_children()
    {
        return $this->children ??= new Array_Collection();
    }
}