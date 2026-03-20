<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Mapping\Event;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Mapping\Event\Adapter_Interface;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
/**
 * Doctrine event adapter for the Translatable extension.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
interface Translatable_Adapter extends Adapter_Interface
{
    /**
     * Checks if the given translation class is a subclass of the personal translation class.
     *
     * @param string $translationClassName
     *
     * @phpstan-param class-string $translationClassName
     *
     * @return bool
     */
    public function uses_personal_translation($translation_class_name);
    /**
     * Get the default translation class used to store translations.
     *
     * @return string
     *
     * @phpstan-return class-string
     */
    public function get_default_translation_class();
    /**
     * Load the translations for a given object.
     *
     * @param object $object
     * @param string $translationClass
     * @param string $locale
     * @param string $objectClass
     *
     * @phpstan-param class-string $translationClass
     * @phpstan-param class-string $objectClass
     *
     * @return array<int, array<string, mixed>>
     */
    public function load_translations($object, $translation_class, $locale, $object_class);
    /**
     * Search for an existing translation record.
     *
     * @param string $locale
     * @param string $field
     * @param string $translationClass
     * @param string $objectClass
     *
     * @phpstan-param AbstractWrapper<ClassMetadata<object>, object, ObjectManager> $wrapped
     * @phpstan-param class-string $translationClass
     * @phpstan-param class-string $objectClass
     *
     * @return mixed null if nothing is found, translation object otherwise
     */
    public function find_translation(Abstract_Wrapper $wrapped, $locale, $field, $translation_class, $object_class);
    /**
     * Removes all associated translations for the given object.
     *
     * @param string $transClass
     * @param string $objectClass
     *
     * @phpstan-param AbstractWrapper<ClassMetadata<object>, object, ObjectManager> $wrapped
     * @phpstan-param class-string $transClass
     * @phpstan-param class-string $objectClass
     *
     * @return int
     */
    public function remove_associated_translations(Abstract_Wrapper $wrapped, $trans_class, $object_class);
    /**
     * Inserts the translation record.
     *
     * @param object $translation
     *
     * @return void
     */
    public function insert_translation_record($translation);
    /**
     * Get the transformed value for translation storage.
     *
     * @param object $object
     * @param string $field
     * @param mixed  $value
     *
     * @return mixed
     */
    public function get_translation_value($object, $field, $value = false);
    /**
     * Transform the value from the database for translation
     *
     * @param object $object
     * @param string $field
     * @param mixed  $value
     *
     * @return void
     */
    public function set_translation_value($object, $field, $value);
}