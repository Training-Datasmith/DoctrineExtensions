<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translator;

/**
 * Base translation class.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
abstract class Translation implements Translation_Interface
{
    /**
     * @var object|null
     */
    protected $translatable;
    /**
     * @var string|null
     */
    protected $locale;
    /**
     * @var string|null
     */
    protected $property;
    /**
     * @var string|null
     */
    protected $value;
    /**
     * Set translatable
     *
     * @param object $translatable
     */
    public function set_translatable($translatable): void
    {
        $this->translatable = $translatable;
    }
    /**
     * Get translatable
     *
     * @return object|null
     */
    public function get_translatable()
    {
        return $this->translatable;
    }
    /**
     * Set locale
     *
     * @param string $locale
     */
    public function set_locale($locale): void
    {
        $this->locale = $locale;
    }
    /**
     * Get locale
     *
     * @return string|null
     */
    public function get_locale()
    {
        return $this->locale;
    }
    /**
     * Set property
     *
     * @param string $property
     */
    public function set_property($property): void
    {
        $this->property = $property;
    }
    /**
     * Get property
     *
     * @return string|null
     */
    public function get_property()
    {
        return $this->property;
    }
    /**
     * Set value
     *
     * @param string $value
     *
     * @return static
     */
    public function set_value($value)
    {
        $this->value = $value;
        return $this;
    }
    /**
     * Get value
     *
     * @return string|null
     */
    public function get_value()
    {
        return $this->value;
    }
}