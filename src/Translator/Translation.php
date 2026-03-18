<?php

declare(strict_types=1);

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
abstract class Translation implements TranslationInterface
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
    public function setTranslatable($translatable): void
    {
        $this->translatable = $translatable;
    }

    /**
     * Get translatable
     *
     * @return object|null
     */
    public function getTranslatable()
    {
        return $this->translatable;
    }

    /**
     * Set locale
     *
     * @param string $locale
     */
    public function setLocale($locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Get locale
     *
     * @return string|null
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set property
     *
     * @param string $property
     */
    public function setProperty($property): void
    {
        $this->property = $property;
    }

    /**
     * Get property
     *
     * @return string|null
     */
    public function getProperty()
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
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return string|null
     */
    public function getValue()
    {
        return $this->value;
    }
}
