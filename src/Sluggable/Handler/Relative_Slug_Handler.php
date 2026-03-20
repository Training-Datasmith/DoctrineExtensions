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
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Sluggable\Mapping\Event\Sluggable_Adapter;
use Gedmo\Sluggable\Sluggable_Listener;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
/**
 * Sluggable handler which should be used in order to prefix
 * a slug of related object. For instance user may belong to a company
 * in this case user slug could look like 'company-name/user-firstname'
 * where path separator separates the relative slug
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Relative_Slug_Handler implements Slug_Handler_Interface
{
    public const SEPARATOR = '/';
    /**
     * @var ObjectManager
     */
    protected $om;
    protected \Gedmo\Sluggable\Sluggable_Listener $sluggable;
    /**
     * Used options
     *
     * @var array<string, mixed>
     */
    private array $used_options = [];
    /**
     * Callable of original transliterator which is used by the sluggable listener.
     *
     * @var callable(string, string, object): string
     */
    private $original_transliterator;
    public function __construct(Sluggable_Listener $sluggable)
    {
        $this->sluggable = $sluggable;
    }
    public function on_change_decision(Sluggable_Adapter $ea, array &$config, $object, &$slug, &$need_to_change_slug): void
    {
        $this->om = $ea->get_object_manager();
        $is_insert = $this->om->get_unit_of_work()->is_scheduled_for_insert($object);
        $this->used_options = $config['handlers'][static::class];
        if (!isset($this->used_options['separator'])) {
            $this->used_options['separator'] = self::SEPARATOR;
        }
        if (!$is_insert && !$need_to_change_slug) {
            $change_set = $ea->get_object_change_set($this->om->get_unit_of_work(), $object);
            if (isset($change_set[$this->used_options['relationField']])) {
                $need_to_change_slug = true;
            }
        }
    }
    public function post_slug_build(Sluggable_Adapter $ea, array &$config, $object, &$slug): void
    {
        $this->original_transliterator = $this->sluggable->get_transliterator();
        $this->sluggable->set_transliterator([$this, 'transliterate']);
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public static function validate(array $options, Class_Metadata $meta): void
    {
        if (!$meta->is_single_valued_association($options['relationField'])) {
            throw new Invalid_Mapping_Exception("Unable to find slug relation through field - [{$options['relationField']}] in class - {$meta->get_name()}");
        }
    }
    public function on_slug_completion(Sluggable_Adapter $ea, array &$config, $object, &$slug)
    {
    }
    /**
     * Transliterates the slug and prefixes the slug
     * by relative one
     *
     * @param string $text
     * @param string $separator
     * @param object $object
     *
     * @return string
     */
    public function transliterate($text, $separator, $object)
    {
        $result = call_user_func_array($this->original_transliterator, [$text, $separator, $object]);
        $result = call_user_func_array($this->sluggable->get_urlizer(), [$result, $separator, $object]);
        $wrapped = Abstract_Wrapper::wrap($object, $this->om);
        $relation = $wrapped->get_property_value($this->used_options['relationField']);
        if ($relation) {
            $wrapped_relation = Abstract_Wrapper::wrap($relation, $this->om);
            $slug = $wrapped_relation->get_property_value($this->used_options['relationSlugField']);
            if (isset($this->used_options['urilize']) && $this->used_options['urilize']) {
                $slug = call_user_func_array($this->original_transliterator, [$slug, $separator, $object]);
            }
            $result = $slug . $this->used_options['separator'] . $result;
        }
        $this->sluggable->set_transliterator($this->original_transliterator);
        return $result;
    }
    public function handles_urlization(): bool
    {
        return true;
    }
}