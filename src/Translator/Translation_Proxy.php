<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translator;

use Doctrine\Common\Collections\Collection;
/**
 * Proxy class for Entity/Document translations.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class Translation_Proxy
{
    /**
     * @var string
     */
    protected $locale;
    /**
     * @var object
     */
    protected $translatable;
    /**
     * @var string[]
     */
    protected array $properties;
    /**
     * @var string
     *
     * @phpstan-var class-string<TranslationInterface>
     */
    protected $class;
    /**
     * @var Collection<int, TranslationInterface>
     */
    protected $coll;
    /**
     * Initializes translations collection
     *
     * @param object   $translatable object to translate
     * @param string   $locale       translation name
     * @param string[] $properties   object properties to translate
     * @param string   $class        translation entity|document class
     *
     * @phpstan-param class-string<TranslationInterface> $class
     * @phpstan-param Collection<int, TranslationInterface> $coll
     *
     * @throws \InvalidArgumentException Translation class doesn't implement TranslationInterface
     */
    public function __construct($translatable, $locale, array $properties, $class, Collection $coll)
    {
        $this->translatable = $translatable;
        $this->locale = $locale;
        $this->properties = $properties;
        $this->class = $class;
        $this->coll = $coll;
        if (!is_subclass_of($class, Translation_Interface::class)) {
            throw new \InvalidArgumentException(sprintf('Translation class should implement %s, "%s" given', Translation_Interface::class, $class));
        }
    }
    /**
     * @param mixed[] $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments)
    {
        $matches = [];
        if (preg_match('/^(set|get)(.*)$/', $method, $matches)) {
            $property = lcfirst($matches[2]);
            if (in_array($property, $this->properties, true)) {
                switch ($matches[1]) {
                    case 'get':
                        return $this->get_translated_value($property);
                    case 'set':
                        if (isset($arguments[0])) {
                            $this->set_translated_value($property, $arguments[0]);
                            return $this;
                        }
                }
            }
        }
        $return = call_user_func_array([$this->translatable, $method], $arguments);
        if ($this->translatable === $return) {
            return $this;
        }
        return $return;
    }
    /**
     * @return mixed
     */
    public function __get(string $property)
    {
        if (in_array($property, $this->properties, true)) {
            if (method_exists($this, $getter = 'get' . ucfirst($property))) {
                return $this->{$getter};
            }
            return $this->get_translated_value($property);
        }
        return $this->translatable->{$property};
    }
    /**
     * @param mixed  $value
     */
    public function __set(string $property, $value)
    {
        if (in_array($property, $this->properties, true)) {
            if (method_exists($this, $setter = 'set' . ucfirst($property))) {
                $this->{$setter}($value);
                return;
            }
            $this->set_translated_value($property, $value);
            return;
        }
        $this->translatable->{$property} = $value;
    }
    /**
     * @return bool
     */
    public function __isset(string $property)
    {
        return in_array($property, $this->properties, true);
    }
    /**
     * Returns locale name for the current translation proxy instance.
     *
     * @return string
     */
    public function get_proxy_locale()
    {
        return $this->locale;
    }
    /**
     * Returns translated value for specific property.
     *
     * @param string $property property name
     *
     * @return mixed
     */
    public function get_translated_value(string $property)
    {
        return $this->find_or_create_translation_for_property($property, $this->get_proxy_locale())->get_value();
    }
    /**
     * Sets translated value for specific property.
     *
     * @param string $property property name
     * @param string $value    value
     */
    public function set_translated_value(string $property, $value): void
    {
        $this->find_or_create_translation_for_property($property, $this->get_proxy_locale())->set_value($value);
    }
    /**
     * Finds existing or creates new translation for specified property
     */
    private function find_or_create_translation_for_property(string $property, string $locale): Translation_Interface
    {
        foreach ($this->coll as $translation) {
            if ($locale === $translation->get_locale() && $property === $translation->get_property()) {
                return $translation;
            }
        }
        /** @var TranslationInterface $translation */
        $translation = new $this->class();
        $translation->set_translatable($this->translatable);
        $translation->set_property($property);
        $translation->set_locale($locale);
        $this->coll->add($translation);
        return $translation;
    }
}