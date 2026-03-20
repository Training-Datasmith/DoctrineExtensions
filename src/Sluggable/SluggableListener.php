<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Sluggable;

use Doctrine\Common\Event_Args;
use Doctrine\Persistence\Event\Lifecycle_Event_Args;
use Doctrine\Persistence\Event\Load_Class_Metadata_Event_Args;
use Doctrine\Persistence\Event\Manager_Event_Args;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Mapping\Mapped_Event_Subscriber;
use Gedmo\Sluggable\Handler\Slug_Handler_Interface;
use Gedmo\Sluggable\Handler\Slug_Handler_With_Unique_Callback_Interface;
use Gedmo\Sluggable\Mapping\Event\Sluggable_Adapter;
use Symfony\Component\String\Slugger\Ascii_Slugger;
use function Symfony\Component\String\u;
/**
 * The SluggableListener handles the generation of slugs
 * for documents and entities.
 *
 * This behavior can impact the performance of your application
 * since it does some additional calculations on persisted objects.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Klein Florian <florian.klein@free.fr>
 *
 * @phpstan-type SluggableConfiguration = array{
 *   mappedBy?: string,
 *   pathSeparator?: string,
 *   slug?: string,
 *   slugs?: array<string, SlugConfiguration>,
 *   unique?: bool,
 *   useObjectClass?: class-string,
 * }
 * @phpstan-type SlugConfiguration = array{
 *   fields: string[],
 *   slug: string,
 *   style: string,
 *   dateFormat: string,
 *   pathSeparator?: string,
 *   updatable: bool,
 *   unique: bool,
 *   unique_base: string,
 *   separator: string,
 *   prefix: string,
 *   suffix: string,
 *   handlers: array<class-string, array{
 *     mappedBy?: string,
 *     inverseSlugField?: string,
 *     parentRelationField?: string,
 *     relationClass?: class-string,
 *     relationField?: string,
 *     relationSlugField?: string,
 *     separator?: string,
 *     urilize?: bool,
 *   }>,
 *   uniqueOverTranslations: bool,
 *   useObjectClass?: class-string,
 * }
 *
 * @phpstan-extends MappedEventSubscriber<SluggableConfiguration, SluggableAdapter>
 */
class Sluggable_Listener extends Mapped_Event_Subscriber
{
    /**
     * The power exponent to jump
     * the slug unique number by tens.
     */
    private int $exponent = 0;
    /**
     * Transliteration callback for slugs
     *
     * @var callable(string, string, object): string
     */
    private $transliterator;
    /**
     * Urlize callback for slugs
     *
     * @var callable(string, string, object): string
     */
    private $urlizer;
    /**
     * List of inserted slugs for each object class.
     * This is needed in case there are identical slug
     * composition in number of persisted objects
     * during the same flush
     *
     * @var array<string, array<int, object>>
     *
     * @phpstan-var array<class-string, array<int, object>>
     */
    private array $persisted = [];
    /**
     * List of initialized slug handlers
     *
     * @var array<string, SlugHandlerInterface>
     *
     * @phpstan-var array<class-string<SlugHandlerInterface>, SlugHandlerInterface>
     */
    private array $handlers = [];
    /**
     * List of filters which are manipulated when slugs are generated
     *
     * @var array<string, array<string, mixed>>
     */
    private array $managed_filters = [];
    public function __construct()
    {
        parent::__construct();
        $this->set_transliterator(static fn(string $text, string $separator, object $object): string => u($text)->ascii()->to_string());
        /*
         * Note - Requiring the call to `lower()` in this chain contradicts with the `style` configuration
         * which doesn't require or enforce lowercase styling by default, but the Behat transliterator applied
         * this styling so it is used for B/C
         */
        $this->set_urlizer(static fn(string $text, string $separator, object $object): string => (new Ascii_Slugger())->slug($text, $separator)->lower()->to_string());
    }
    /**
     * Specifies the list of events to listen
     *
     * @return string[]
     */
    public function get_subscribed_events(): array
    {
        return ['onFlush', 'loadClassMetadata', 'prePersist'];
    }
    /**
     * Set the transliteration callable method
     * to transliterate slugs
     *
     * @param callable $callable
     *
     * @phpstan-param callable(string $text, string $separator, object $object): string $callable
     *
     * @throws InvalidArgumentException
     */
    public function set_transliterator($callable): void
    {
        if (!is_callable($callable)) {
            throw new InvalidArgumentException('Invalid transliterator callable parameter given');
        }
        $this->transliterator = $callable;
    }
    /**
     * Set the urlization callable method
     * to urlize slugs
     *
     * @param callable $callable
     *
     * @phpstan-param callable(string $text, string $separator, object $object): string $callable
     *
     * @throws InvalidArgumentException
     */
    public function set_urlizer($callable): void
    {
        if (!is_callable($callable)) {
            throw new InvalidArgumentException('Invalid urlizer callable parameter given');
        }
        $this->urlizer = $callable;
    }
    /**
     * Get currently used transliterator callable
     *
     * @return callable
     *
     * @phpstan-return callable(string $text, string $separator, object $object): string
     */
    public function get_transliterator()
    {
        return $this->transliterator;
    }
    /**
     * Get currently used urlizer callable
     *
     * @return callable
     *
     * @phpstan-return callable(string $text, string $separator, object $object): string
     */
    public function get_urlizer()
    {
        return $this->urlizer;
    }
    /**
     * Enables or disables the given filter when slugs are generated
     *
     * @param bool   $disable True by default
     *
     */
    public function add_managed_filter(string $name, $disable = true): void
    {
        $this->managed_filters[$name] = ['disabled' => $disable];
    }
    /**
     * Removes a filter from the managed set
     *
     *
     */
    public function remove_managed_filter(string $name): void
    {
        unset($this->managed_filters[$name]);
    }
    /**
     * Mapps additional metadata
     *
     * @param LoadClassMetadataEventArgs $eventArgs
     *
     * @phpstan-param LoadClassMetadataEventArgs<ClassMetadata<object>, ObjectManager> $eventArgs
     */
    public function load_class_metadata(Event_Args $event_args): void
    {
        $this->load_metadata_for_object_class($event_args->get_object_manager(), $event_args->get_class_metadata());
    }
    /**
     * Allows identifier fields to be slugged as usual
     *
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function pre_persist(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        if ($config = $this->get_configuration($om, $meta->get_name())) {
            foreach ($config['slugs'] as $slug_field => $options) {
                if ($meta->is_identifier($slug_field)) {
                    $meta->set_field_value($object, $slug_field, uniqid('__sluggable_placeholder__'));
                }
            }
        }
    }
    /**
     * Generate slug on objects being updated during flush
     * if they require changing
     *
     * @param ManagerEventArgs $args
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $args
     */
    public function on_flush(Event_Args $args): void
    {
        $this->persisted = [];
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $uow = $om->get_unit_of_work();
        $this->manage_filters_before_generation($om);
        // process all objects being inserted, using scheduled insertions instead
        // of prePersist in case if record will be changed before flushing this will
        // ensure correct result. No additional overhead is encountered
        foreach ($ea->get_scheduled_object_insertions($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            if ($this->get_configuration($om, $meta->get_name())) {
                // generate first to exclude this object from similar persisted slugs result
                $this->generate_slug($ea, $object);
                $this->persisted[$ea->get_root_object_class($meta)][] = $object;
            }
        }
        // we use onFlush and not preUpdate event to let other
        // event listeners be nested together
        foreach ($ea->get_scheduled_object_updates($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            if ($this->get_configuration($om, $meta->get_name()) && !$uow->is_scheduled_for_insert($object)) {
                $this->generate_slug($ea, $object);
                $this->persisted[$ea->get_root_object_class($meta)][] = $object;
            }
        }
        $this->manage_filters_after_generation($om);
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
    /**
     * Get the slug handler instance by $class name
     *
     * @phpstan-param class-string $class
     */
    private function get_handler(string $class): Slug_Handler_Interface
    {
        if (!isset($this->handlers[$class])) {
            $this->handlers[$class] = new $class($this);
        }
        return $this->handlers[$class];
    }
    /**
     * Creates the slug for object being flushed
     */
    private function generate_slug(Sluggable_Adapter $ea, object $object): void
    {
        $om = $ea->get_object_manager();
        $meta = $om->get_class_metadata(get_class($object));
        $uow = $om->get_unit_of_work();
        $change_set = $ea->get_object_change_set($uow, $object);
        $is_insert = $uow->is_scheduled_for_insert($object);
        $config = $this->get_configuration($om, $meta->get_name());
        foreach ($config['slugs'] as $slug_field => $options) {
            $has_handlers = [] !== $options['handlers'];
            $options['useObjectClass'] = $config['useObjectClass'];
            // collect the slug from fields
            $slug = $meta->get_field_value($object, $slug_field);
            // if slug should not be updated, skip it
            if (!$options['updatable'] && !$is_insert && (!isset($change_set[$slug_field]) || 0 === strpos($slug, '__sluggable_placeholder__'))) {
                continue;
            }
            // must fetch the old slug from changeset, since $object holds the new version
            $old_slug = isset($change_set[$slug_field]) ? $change_set[$slug_field][0] : $slug;
            $need_to_change_slug = false;
            // if slug is null, regenerate it, or needs an update
            if (null === $slug || 0 === strpos($slug, '__sluggable_placeholder__') || !isset($change_set[$slug_field])) {
                $slug = '';
                foreach ($options['fields'] as $sluggable_field) {
                    if (isset($change_set[$sluggable_field]) || isset($change_set[$slug_field])) {
                        $need_to_change_slug = true;
                    }
                    $value = $meta->get_field_value($object, $sluggable_field);
                    $slug .= $value instanceof \DateTimeInterface ? $value->format($options['dateFormat']) : $value;
                    $slug .= ' ';
                }
                // trim generated slug as it will have unnecessary trailing space
                $slug = trim($slug);
            } else {
                // slug was set manually
                $need_to_change_slug = true;
            }
            // notify slug handlers --> onChangeDecision
            if ($has_handlers) {
                foreach ($options['handlers'] as $class => $handler_options) {
                    $this->get_handler($class)->on_change_decision($ea, $options, $object, $slug, $need_to_change_slug);
                }
            }
            // if slug is changed, do further processing
            if ($need_to_change_slug) {
                $mapping = $meta->get_field_mapping($slug_field);
                // notify slug handlers --> postSlugBuild
                $urlized = false;
                if ($has_handlers) {
                    foreach ($options['handlers'] as $class => $handler_options) {
                        $this->get_handler($class)->post_slug_build($ea, $options, $object, $slug);
                        if ($this->get_handler($class)->handles_urlization()) {
                            $urlized = true;
                        }
                    }
                }
                // build the slug
                // Step 1: transliteration, changing 北京 to 'Bei Jing'
                $slug = call_user_func_array($this->transliterator, [$slug, $options['separator'], $object]);
                // Step 2: urlization (replace spaces by '-' etc...)
                if (!$urlized) {
                    $slug = call_user_func_array($this->urlizer, [$slug, $options['separator'], $object]);
                }
                // add suffix/prefix
                $slug = $options['prefix'] . $slug . $options['suffix'];
                // Step 3: stylize the slug
                switch ($options['style']) {
                    case 'camel':
                        $quoted_separator = preg_quote($options['separator']);
                        $slug = preg_replace_callback('/^[a-z]|' . $quoted_separator . '[a-z]/smi', static fn(array $m): string => u($m[0])->upper()->to_string(), $slug);
                        break;
                    case 'lower':
                        $slug = u($slug)->lower()->to_string();
                        break;
                    case 'upper':
                        $slug = u($slug)->upper()->to_string();
                        break;
                    default:
                        // leave it as is
                        break;
                }
                // cut slug if exceeded in length
                $length = $mapping->length ?? $mapping['length'] ?? null;
                if (null !== $length && strlen($slug) > $length) {
                    $slug = substr($slug, 0, $length);
                }
                if (($mapping->nullable ?? $mapping['nullable'] ?? false) && 0 === strlen($slug)) {
                    $slug = null;
                }
                // notify slug handlers --> beforeMakingUnique
                if ($has_handlers) {
                    foreach ($options['handlers'] as $class => $handler_options) {
                        $handler = $this->get_handler($class);
                        if ($handler instanceof Slug_Handler_With_Unique_Callback_Interface) {
                            $handler->before_making_unique($ea, $options, $object, $slug);
                        }
                    }
                }
                // make unique slug if requested
                if ($options['unique'] && null !== $slug) {
                    $this->exponent = 0;
                    $slug = $this->make_unique_slug($ea, $object, $slug, false, $options);
                }
                // notify slug handlers --> onSlugCompletion
                if ($has_handlers) {
                    foreach ($options['handlers'] as $class => $handler_options) {
                        $this->get_handler($class)->on_slug_completion($ea, $options, $object, $slug);
                    }
                }
                // set the final slug
                $meta->set_field_value($object, $slug_field, $slug);
                // recompute changeset
                $ea->recompute_single_object_change_set($uow, $meta, $object);
                // overwrite changeset (to set old value)
                $uow->property_changed($object, $slug_field, $old_slug, $slug);
            }
        }
    }
    /**
     * Generates the unique slug
     *
     * @param SlugConfiguration $config
     */
    private function make_unique_slug(Sluggable_Adapter $ea, object $object, string $preferred_slug, bool $recursing, array $config): string
    {
        $om = $ea->get_object_manager();
        $meta = $om->get_class_metadata(get_class($object));
        $similar_persisted = [];
        // extract unique base
        $base = false;
        if ($config['unique'] && isset($config['unique_base'])) {
            $base = $meta->get_field_value($object, $config['unique_base']);
        }
        // collect similar persisted slugs during this flush
        if (isset($this->persisted[$class = $ea->get_root_object_class($meta)])) {
            foreach ($this->persisted[$class] as $obj) {
                if (false !== $base && $meta->get_field_value($obj, $config['unique_base']) !== $base) {
                    continue;
                    // if unique_base field is not the same, do not take slug as similar
                }
                $slug = $meta->get_field_value($obj, $config['slug']);
                $quoted_preferred_slug = preg_quote($preferred_slug);
                if (preg_match("@^{$quoted_preferred_slug}.*@smi", $slug)) {
                    $similar_persisted[] = [$config['slug'] => $slug];
                }
            }
        }
        // load similar slugs
        $result = [...$ea->get_similar_slugs($object, $meta, $config, $preferred_slug), ...$similar_persisted];
        // leave only right slugs
        if (!$recursing) {
            // filter similar slugs
            $quoted_separator = preg_quote($config['separator']);
            $quoted_preferred_slug = preg_quote($preferred_slug);
            foreach ($result as $key => $similar) {
                if (!preg_match("@{$quoted_preferred_slug}(\$|{$quoted_separator}[\\d]+\$)@smi", $similar[$config['slug']])) {
                    unset($result[$key]);
                }
            }
        }
        if ($result) {
            $generated_slug = $preferred_slug;
            $same_slugs = [];
            foreach ($result as $list) {
                $same_slugs[] = $list[$config['slug']];
            }
            $i = 10 ** $this->exponent;
            $unique_suffix = (string) $i;
            if ($recursing || in_array($generated_slug, $same_slugs, true)) {
                do {
                    $generated_slug = $preferred_slug . $config['separator'] . $unique_suffix;
                    $unique_suffix = (string) ++$i;
                } while (in_array($generated_slug, $same_slugs, true));
            }
            $mapping = $meta->get_field_mapping($config['slug']);
            $length = $mapping->length ?? $mapping['length'] ?? null;
            if (null !== $length && strlen($generated_slug) > $length) {
                $generated_slug = substr($generated_slug, 0, $length - (strlen($unique_suffix) + strlen($config['separator'])));
                $this->exponent = strlen($unique_suffix) - 1;
                if (substr($generated_slug, -strlen($config['separator'])) == $config['separator']) {
                    $generated_slug = substr($generated_slug, 0, strlen($generated_slug) - strlen($config['separator']));
                }
                $generated_slug = $this->make_unique_slug($ea, $object, $generated_slug, true, $config);
            }
            $preferred_slug = $generated_slug;
        }
        return $preferred_slug;
    }
    private function manage_filters_before_generation(Object_Manager $om): void
    {
        $collection = $this->get_filter_collection_from_object_manager($om);
        $enabled_filters = array_keys($collection->get_enabled_filters());
        // set each managed filter to desired status
        foreach ($this->managed_filters as $name => &$config) {
            $enabled = in_array($name, $enabled_filters, true);
            $config['previouslyEnabled'] = $enabled;
            if ($config['disabled']) {
                if ($enabled) {
                    $collection->disable($name);
                }
            } else {
                $collection->enable($name);
            }
        }
    }
    private function manage_filters_after_generation(Object_Manager $om): void
    {
        $collection = $this->get_filter_collection_from_object_manager($om);
        // Restore managed filters to their original status
        foreach ($this->managed_filters as $name => &$config) {
            if (true === $config['previouslyEnabled']) {
                $collection->enable($name);
            }
            unset($config['previouslyEnabled']);
        }
    }
    /**
     * Retrieves a FilterCollection instance from the given ObjectManager.
     *
     * @throws InvalidArgumentException
     *
     * @return mixed
     */
    private function get_filter_collection_from_object_manager(Object_Manager $om)
    {
        if (is_callable([$om, 'getFilters'])) {
            return $om->get_filters();
        }
        if (is_callable([$om, 'getFilterCollection'])) {
            return $om->get_filter_collection();
        }
        throw new InvalidArgumentException('ObjectManager does not support filters');
    }
}