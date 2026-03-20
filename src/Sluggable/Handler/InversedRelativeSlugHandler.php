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
/**
 * Sluggable handler which should be used for inversed relation mapping
 * used together with RelativeSlugHandler. Updates back related slug on
 * relation changes
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Inversed_Relative_Slug_Handler implements Slug_Handler_Interface
{
    /**
     * @var ObjectManager
     */
    protected $om;
    protected \Gedmo\Sluggable\Sluggable_Listener $sluggable;
    public function __construct(Sluggable_Listener $sluggable)
    {
        $this->sluggable = $sluggable;
    }
    public function on_change_decision(Sluggable_Adapter $ea, array &$config, $object, &$slug, &$need_to_change_slug)
    {
    }
    public function post_slug_build(Sluggable_Adapter $ea, array &$config, $object, &$slug)
    {
    }
    /**
     * @param ClassMetadata<object> $meta
     */
    public static function validate(array $options, Class_Metadata $meta): void
    {
        if (!isset($options['relationClass']) || !strlen($options['relationClass'])) {
            throw new Invalid_Mapping_Exception("'relationClass' option must be specified for object slug mapping - {$meta->get_name()}");
        }
        if (!isset($options['mappedBy']) || !strlen($options['mappedBy'])) {
            throw new Invalid_Mapping_Exception("'mappedBy' option must be specified for object slug mapping - {$meta->get_name()}");
        }
        if (!isset($options['inverseSlugField']) || !strlen($options['inverseSlugField'])) {
            throw new Invalid_Mapping_Exception("'inverseSlugField' option must be specified for object slug mapping - {$meta->get_name()}");
        }
    }
    public function on_slug_completion(Sluggable_Adapter $ea, array &$config, $object, &$slug): void
    {
        $this->om = $ea->get_object_manager();
        $is_insert = $this->om->get_unit_of_work()->is_scheduled_for_insert($object);
        if (!$is_insert) {
            $options = $config['handlers'][static::class];
            $wrapped = Abstract_Wrapper::wrap($object, $this->om);
            $old_slug = $wrapped->get_property_value($config['slug']);
            $mapped_by_config = $this->sluggable->get_configuration($this->om, $options['relationClass']);
            if ($mapped_by_config) {
                assert(class_exists($options['relationClass']));
                $meta = $this->om->get_class_metadata($options['relationClass']);
                if (!$meta->is_single_valued_association($options['mappedBy'])) {
                    throw new Invalid_Mapping_Exception('Unable to find ' . $wrapped->get_metadata()->get_name() . " relation - [{$options['mappedBy']}] in class - {$meta->get_name()}");
                }
                if (!isset($mapped_by_config['slugs'][$options['inverseSlugField']])) {
                    throw new Invalid_Mapping_Exception("Unable to find slug field - [{$options['inverseSlugField']}] in class - {$meta->get_name()}");
                }
                $mapped_by_config['slug'] = $mapped_by_config['slugs'][$options['inverseSlugField']]['slug'];
                $mapped_by_config['mappedBy'] = $options['mappedBy'];
                $ea->replace_inverse_relative($object, $mapped_by_config, $slug, $old_slug);
                $uow = $this->om->get_unit_of_work();
                // update in memory objects
                foreach ($uow->get_identity_map() as $class_name => $objects) {
                    // for inheritance mapped classes, only root is always in the identity map
                    if ($class_name !== $mapped_by_config['useObjectClass']) {
                        continue;
                    }
                    foreach ($objects as $object) {
                        // @todo: Remove the check against `method_exists()` in the next major release.
                        if (($object instanceof Proxy || method_exists($object, '__isInitialized')) && !$object->__is_initialized()) {
                            continue;
                        }
                        $object_slug = (string) $meta->get_field_value($object, $mapped_by_config['slug']);
                        if (preg_match("@^{$old_slug}@smi", $object_slug)) {
                            $object_slug = str_replace($old_slug, $slug, $object_slug);
                            $meta->set_field_value($object, $mapped_by_config['slug'], $object_slug);
                            $ea->set_original_object_property($uow, $object, $mapped_by_config['slug'], $object_slug);
                        }
                    }
                }
            }
        }
    }
    public function handles_urlization(): bool
    {
        return false;
    }
}