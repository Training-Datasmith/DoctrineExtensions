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
 * Object for managing translations.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
interface Translation_Interface
{
    /**
     * Set the translatable item.
     *
     * @param object $translatable
     *
     * @return void
     */
    public function set_translatable($translatable);
    /**
     * Get the translatable item.
     *
     * @return object
     */
    public function get_translatable();
    /**
     * Set the translation locale.
     *
     * @param string $locale
     *
     * @return void
     */
    public function set_locale($locale);
    /**
     * Get the translation locale.
     *
     * @return string
     */
    public function get_locale();
    /**
     * Set the translated property.
     *
     * @param string $property
     *
     * @return void
     */
    public function set_property($property);
    /**
     * Get the translated property.
     *
     * @return string
     */
    public function get_property();
    /**
     * Set the translation value.
     *
     * @param string $value
     *
     * @return static
     */
    public function set_value($value);
    /**
     * Get the translation value.
     *
     * @return string
     */
    public function get_value();
}