<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sluggable\Handler;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Doctrine\Persistence\Proxy;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Sluggable\Mapping\Event\Sluggable_Adapter;
use Gedmo\Sluggable\Sluggable_Listener;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
use function Symfony\Component\String\u;
/**
 * Sluggable handler which slugs all parent nodes
 * recursively and synchronizes on updates. For instance
 * category tree slug could look like "food/fruits/apples"
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Tree_Slug_Handler implements Slug_Handler_With_Unique_Callback_Interface
{
    public const SEPARATOR = '/';
    /**
     * @var ObjectManager
     */
    protected $om;
    protected \Gedmo\Sluggable\Sluggable_Listener $sluggable;
    private string $prefix = '';
    private string $suffix = '';
    /**
     * True if node is being inserted
     */
    private bool $is_insert = false;
    /**
     * Transliterated parent slug
     */
    private string $parent_slug = '';
    /**
     * Used path separator
     */
    private string $used_path_separator = self::SEPARATOR;
    public function __construct(Sluggable_Listener $sluggable)
    {
        $this->sluggable = $sluggable;
    }
    public function on_change_decision(Sluggable_Adapter $ea, array &$config, $object, &$slug, &$need_to_change_slug): void
    {
        $this->om = $ea->get_object_manager();
        $this->is_insert = $this->om->get_unit_of_work()->is_scheduled_for_insert($object);
        $options = $config['handlers'][static::class];
        $this->used_path_separator = $options['separator'] ?? self::SEPARATOR;
        $this->prefix = $options['prefix'] ?? '';
        $this->suffix = $options['suffix'] ?? '';
        if (!$this->is_insert && !$need_to_change_slug) {
            $change_set = $ea->get_object_change_set($this->om->get_unit_of_work(), $object);
            if (isset($change_set[$options['parentRelationField']])) {
                $need_to_change_slug = true;
            }
        }
    }
    public function post_slug_build(Sluggable_Adapter $ea, array &$config, $object, &$slug): void
    {
        $options = $config['handlers'][static::class];
        $this->parent_slug = '';
        $wrapped = Abstract_Wrapper::wrap($object, $this->om);
        if ($parent = $wrapped->get_property_value($options['parentRelationField'])) {
            $parent = Abstract_Wrapper::wrap($parent, $this->om);
            $this->parent_slug = $parent->get_property_value($config['slug']);
            // if needed, remove suffix from parentSlug, so we can use it to prepend it to our slug
            if (isset($options['suffix'])) {
                $this->parent_slug = u($this->parent_slug)->trim_suffix($options['suffix'])->to_string();
            }
        }
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public static function validate(array $options, Class_Metadata $meta): void
    {
        if (!$meta->is_single_valued_association($options['parentRelationField'])) {
            throw new Invalid_Mapping_Exception("Unable to find tree parent slug relation through field - [{$options['parentRelationField']}] in class - {$meta->get_name()}");
        }
    }
    public function before_making_unique(Sluggable_Adapter $ea, array &$config, $object, &$slug): void
    {
        $slug = $this->transliterate($slug, $config['separator'], $object);
    }
    public function on_slug_completion(Sluggable_Adapter $ea, array &$config, $object, &$slug): void
    {
        if (!$this->is_insert) {
            $wrapped = Abstract_Wrapper::wrap($object, $this->om);
            $meta = $wrapped->get_metadata();
            $target = $wrapped->get_property_value($config['slug']);
            $config['pathSeparator'] = $this->used_path_separator;
            $ea->replace_relative($object, $config, $target . $config['pathSeparator'], $slug);
            $uow = $this->om->get_unit_of_work();
            // update in memory objects
            foreach ($uow->get_identity_map() as $class_name => $objects) {
                // for inheritance mapped classes, only root is always in the identity map
                if ($class_name !== $wrapped->get_root_object_name()) {
                    continue;
                }
                foreach ($objects as $object) {
                    // @todo: Remove the check against `method_exists()` in the next major release.
                    if (($object instanceof Proxy || method_exists($object, '__isInitialized')) && !$object->__is_initialized()) {
                        continue;
                    }
                    $object_slug = (string) $meta->get_field_value($object, $config['slug']);
                    if (preg_match("@^{$target}{$config['pathSeparator']}@smi", $object_slug)) {
                        $object_slug = str_replace($target, $slug, $object_slug);
                        $meta->set_field_value($object, $config['slug'], $object_slug);
                        $ea->set_original_object_property($uow, $object, $config['slug'], $object_slug);
                    }
                }
            }
        }
    }
    /**
     * Transliterates the slug and prefixes the slug
     * by collection of parent slugs
     *
     * @param string $separator
     * @param object $object
     *
     */
    public function transliterate(string $text, $separator, $object): string
    {
        $slug = $text . $this->suffix;
        if (strlen($this->parent_slug)) {
            return $this->parent_slug . $this->used_path_separator . $slug;
        }
        // if no parentSlug, apply our prefix
        return $this->prefix . $slug;
    }
    public function handles_urlization(): bool
    {
        return false;
    }
}