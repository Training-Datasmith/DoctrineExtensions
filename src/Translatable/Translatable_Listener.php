<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable;

use Doctrine\Common\Event_Args;
use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ORM\Orm_Invalid_Argument_Exception;
use Doctrine\Persistence\Event\Lifecycle_Event_Args;
use Doctrine\Persistence\Event\Load_Class_Metadata_Event_Args;
use Doctrine\Persistence\Event\Manager_Event_Args;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Exception\RuntimeException;
use Gedmo\Mapping\Mapped_Event_Subscriber;
use Gedmo\Tool\Wrapper\Abstract_Wrapper;
use Gedmo\Translatable\Mapping\Event\Translatable_Adapter;
/**
 * The translation listener handles the generation and
 * loading of translations for entities which implements
 * the Translatable interface.
 *
 * This behavior can impact the performance of your application
 * since it does an additional query for each field to translate.
 *
 * Nevertheless the annotation metadata is properly cached and
 * it is not a big overhead to lookup all entity annotations since
 * the caching is activated for metadata
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @phpstan-type TranslatableConfiguration = array{
 *   fields?: string[],
 *   fallback?: array<string, bool>,
 *   locale?: string,
 *   translationClass?: class-string,
 *   useObjectClass?: class-string,
 * }
 *
 * @phpstan-extends MappedEventSubscriber<TranslatableConfiguration, TranslatableAdapter>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Translatable_Listener extends Mapped_Event_Subscriber
{
    /**
     * Query hint to override the fallback of translations
     * integer 1 for true, 0 false
     */
    public const HINT_FALLBACK = 'gedmo.translatable.fallback';
    /**
     * Query hint to override the fallback locale
     */
    public const HINT_TRANSLATABLE_LOCALE = 'gedmo.translatable.locale';
    /**
     * Query hint to use inner join strategy for translations
     */
    public const HINT_INNER_JOIN = 'gedmo.translatable.inner_join.translations';
    /**
     * Locale which is set on this listener.
     * If Entity being translated has locale defined it
     * will override this one
     *
     * @var string
     */
    protected $locale = 'en_US';
    /**
     * Default locale, this changes behavior
     * to not update the original record field if locale
     * which is used for updating is not default. This
     * will load the default translation in other locales
     * if record is not translated yet
     */
    private string $default_locale = 'en_US';
    /**
     * If this is set to false, when if entity does
     * not have a translation for requested locale
     * it will show a blank value
     */
    private bool $translation_fallback = false;
    /**
     * List of translations which do not have the foreign
     * key generated yet - MySQL case. These translations
     * will be updated with new keys on postPersist event
     *
     * @var array<int, array<int, object|Translatable>>
     */
    private array $pending_translation_inserts = [];
    /**
     * Currently in case if there is TranslationQueryWalker
     * in charge. We need to skip issuing additional queries
     * on load
     */
    private bool $skip_on_load = false;
    /**
     * Tracks locale the objects currently translated in
     *
     * @var array<int, string>
     */
    private array $translated_in_locale = [];
    /**
     * Whether or not, to persist default locale
     * translation or keep it in original record
     */
    private bool $persist_default_locale_translation = false;
    /**
     * Tracks translation object for default locale
     *
     * @var array<int, array<string, object|Translatable>>
     */
    private array $translation_in_default_locale = [];
    /**
     * Default translation value upon missing translation
     */
    private ?string $default_translation_value = null;
    /**
     * Specifies the list of events to listen
     *
     * @return string[]
     */
    public function get_subscribed_events(): array
    {
        return ['postLoad', 'postPersist', 'preFlush', 'onFlush', 'loadClassMetadata'];
    }
    /**
     * Set to skip or not onLoad event
     *
     * @param bool $bool
     *
     * @return static
     */
    public function set_skip_on_load($bool): self
    {
        $this->skip_on_load = (bool) $bool;
        return $this;
    }
    /**
     * Whether or not, to persist default locale
     * translation or keep it in original record
     *
     * @param bool $bool
     *
     * @return static
     */
    public function set_persist_default_locale_translation($bool): self
    {
        $this->persist_default_locale_translation = (bool) $bool;
        return $this;
    }
    /**
     * Check if should persist default locale
     * translation or keep it in original record
     */
    public function get_persist_default_locale_translation(): bool
    {
        return $this->persist_default_locale_translation;
    }
    /**
     * Add additional $translation for pending $oid object
     * which is being inserted
     *
     * @param object $translation
     *
     */
    public function add_pending_translation_insert(int $oid, $translation): void
    {
        $this->pending_translation_inserts[$oid][] = $translation;
    }
    /**
     * Maps additional metadata
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
     * Get the translation class to be used
     * for the object $class
     *
     *
     * @phpstan-param class-string $class
     *
     * @return string
     * @phpstan-return class-string
     */
    public function get_translation_class(Translatable_Adapter $ea, string $class)
    {
        return self::$configurations[$this->name][$class]['translationClass'] ?? $ea->get_default_translation_class();
    }
    /**
     * Enable or disable translation fallback
     * to original record value
     *
     * @param bool $bool
     *
     * @return static
     */
    public function set_translation_fallback($bool): self
    {
        $this->translation_fallback = (bool) $bool;
        return $this;
    }
    /**
     * Weather or not is using the translation
     * fallback to original record
     */
    public function get_translation_fallback(): bool
    {
        return $this->translation_fallback;
    }
    /**
     * Set the locale to use for translation listener
     *
     * @param string $locale
     *
     * @return static
     */
    public function set_translatable_locale($locale): self
    {
        $this->validate_locale($locale);
        $this->locale = $locale;
        return $this;
    }
    /**
     * Set the default translation value on missing translation
     *
     * @deprecated usage of a non nullable value for defaultTranslationValue is deprecated
     * and will be removed on the next major release which will rely on the expected types
     */
    public function set_default_translation_value(?string $default_translation_value): void
    {
        $this->default_translation_value = $default_translation_value;
    }
    /**
     * Sets the default locale, this changes behavior
     * to not update the original record field if locale
     * which is used for updating is not default
     *
     *
     * @return static
     */
    public function set_default_locale(string $locale): self
    {
        $this->validate_locale($locale);
        $this->default_locale = $locale;
        return $this;
    }
    /**
     * Gets the default locale
     */
    public function get_default_locale(): string
    {
        return $this->default_locale;
    }
    /**
     * Get currently set global locale, used
     * extensively during query execution
     *
     * @return string
     */
    public function get_listener_locale()
    {
        return $this->locale;
    }
    /**
     * Gets the locale to use for translation. Loads object
     * defined locale first.
     *
     * @param object                $object
     * @param ClassMetadata<object> $meta
     * @param object                $om
     *
     * @throws RuntimeException if language or locale property is not found in entity
     *
     * @return string
     */
    public function get_translatable_locale($object, $meta, $om = null)
    {
        $locale = $this->locale;
        $configuration_locale = self::$configurations[$this->name][$meta->get_name()]['locale'] ?? null;
        if (null !== $configuration_locale) {
            $class = $meta->get_reflection_class();
            if (!$class->has_property($configuration_locale)) {
                throw new RuntimeException("There is no locale or language property ({$configuration_locale}) found on object: {$meta->get_name()}");
            }
            $reflection_property = $class->get_property($configuration_locale);
            if (PHP_VERSION_ID < 80100) {
                $reflection_property->set_accessible(true);
            }
            $value = $reflection_property->get_value($object);
            if (is_object($value) && method_exists($value, '__toString')) {
                $value = $value->__toString();
            }
            if ($this->is_valid_locale($value)) {
                $locale = $value;
            }
        } elseif ($om instanceof Document_Manager) {
            [, $parent_object] = $om->get_unit_of_work()->get_parent_association($object);
            if (null !== $parent_object) {
                $parent_meta = $om->get_class_metadata(get_class($parent_object));
                $locale = $this->get_translatable_locale($parent_object, $parent_meta, $om);
            }
        }
        return $locale;
    }
    /**
     * Handle translation changes in default locale
     *
     * This has to be done in the preFlush because, when an entity has been loaded
     * in a different locale, no changes will be detected.
     *
     * @param ManagerEventArgs $args
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $args
     */
    public function pre_flush(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $uow = $om->get_unit_of_work();
        foreach ($this->translation_in_default_locale as $oid => $fields) {
            $trans = reset($fields);
            assert(false !== $trans);
            if ($ea->uses_personal_translation(get_class($trans))) {
                $entity = $trans->get_object();
            } else {
                $entity = $uow->try_get_by_id($trans->get_foreign_key(), $trans->get_object_class());
            }
            if (!$entity) {
                continue;
            }
            try {
                $uow->schedule_for_update($entity);
            } catch (Orm_Invalid_Argument_Exception $e) {
                foreach ($fields as $field => $trans) {
                    $this->remove_translation_in_default_locale($oid, $field);
                }
            }
        }
    }
    /**
     * Looks for translatable objects being inserted or updated
     * for further processing
     *
     * @param ManagerEventArgs $args
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $args
     */
    public function on_flush(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $uow = $om->get_unit_of_work();
        // check all scheduled inserts for Translatable objects
        foreach ($ea->get_scheduled_object_insertions($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            $config = $this->get_configuration($om, $meta->get_name());
            if (isset($config['fields'])) {
                $this->handle_translatable_object_update($ea, $object, true);
            }
        }
        // check all scheduled updates for Translatable entities
        foreach ($ea->get_scheduled_object_updates($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            $config = $this->get_configuration($om, $meta->get_name());
            if (isset($config['fields'])) {
                $this->handle_translatable_object_update($ea, $object, false);
            }
        }
        // check scheduled deletions for Translatable entities
        foreach ($ea->get_scheduled_object_deletions($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            $config = $this->get_configuration($om, $meta->get_name());
            if (isset($config['fields'])) {
                $wrapped = Abstract_Wrapper::wrap($object, $om);
                $trans_class = $this->get_translation_class($ea, $meta->get_name());
                \assert($wrapped instanceof Abstract_Wrapper);
                $ea->remove_associated_translations($wrapped, $trans_class, $config['useObjectClass']);
            }
        }
    }
    /**
     * Checks for inserted object to update their translation
     * foreign keys
     *
     * @param LifecycleEventArgs $args
     *
     * @phpstan-param LifecycleEventArgs<ObjectManager> $args
     */
    public function post_persist(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        // check if entity is tracked by translatable and without foreign key
        if ($this->get_configuration($om, $meta->get_name()) && [] !== $this->pending_translation_inserts) {
            $oid = spl_object_id($object);
            if (array_key_exists($oid, $this->pending_translation_inserts)) {
                // load the pending translations without key
                $wrapped = Abstract_Wrapper::wrap($object, $om);
                $object_id = $wrapped->get_identifier();
                $translation_class = $this->get_translation_class($ea, get_class($object));
                foreach ($this->pending_translation_inserts[$oid] as $translation) {
                    if ($ea->uses_personal_translation($translation_class)) {
                        $translation->set_object($object_id);
                    } else {
                        $translation->set_foreign_key($object_id);
                    }
                    $ea->insert_translation_record($translation);
                }
                unset($this->pending_translation_inserts[$oid]);
            }
        }
    }
    /**
     * After object is loaded, listener updates the translations
     * by currently used locale
     *
     * @param ManagerEventArgs $args
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $args
     */
    public function post_load(Event_Args $args): void
    {
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $object = $ea->get_object();
        $meta = $om->get_class_metadata(get_class($object));
        $config = $this->get_configuration($om, $meta->get_name());
        $locale = $this->default_locale;
        $oid = null;
        if (isset($config['fields'])) {
            $locale = $this->get_translatable_locale($object, $meta, $om);
            $oid = spl_object_id($object);
            $this->translated_in_locale[$oid] = $locale;
        }
        if ($this->skip_on_load) {
            return;
        }
        if (isset($config['fields']) && ($locale !== $this->default_locale || $this->persist_default_locale_translation)) {
            // fetch translations
            $translation_class = $this->get_translation_class($ea, $config['useObjectClass']);
            $result = $ea->load_translations($object, $translation_class, $locale, $config['useObjectClass']);
            // translate object's translatable properties
            foreach ($config['fields'] as $field) {
                $translated = $this->default_translation_value;
                foreach ($result as $entry) {
                    if ($entry['field'] == $field) {
                        $translated = $entry['content'] ?? null;
                        break;
                    }
                }
                // update translation
                if ($this->default_translation_value !== $translated || !$this->translation_fallback && (!isset($config['fallback'][$field]) || !$config['fallback'][$field]) || $this->translation_fallback && isset($config['fallback'][$field]) && !$config['fallback'][$field]) {
                    $ea->set_translation_value($object, $field, $translated);
                    // ensure clean changeset
                    $ea->set_original_object_property($om->get_unit_of_work(), $object, $field, $meta->get_field_value($object, $field));
                }
            }
        }
    }
    /**
     * Sets translation object which represents translation in default language.
     *
     * @param int                 $oid   hash of basic entity
     * @param string              $field field of basic entity
     * @param object|Translatable $trans Translation object
     */
    public function set_translation_in_default_locale($oid, string $field, $trans): void
    {
        if (!isset($this->translation_in_default_locale[$oid])) {
            $this->translation_in_default_locale[$oid] = [];
        }
        $this->translation_in_default_locale[$oid][$field] = $trans;
    }
    public function is_skip_on_load(): bool
    {
        return $this->skip_on_load;
    }
    /**
     * Check if object has any translation object which represents translation in default language.
     * This is for internal use only.
     *
     * @param int $oid hash of the basic entity
     */
    public function has_translations_in_default_locale($oid): bool
    {
        return array_key_exists($oid, $this->translation_in_default_locale);
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
    /**
     * Validates the given locale
     *
     * @param string $locale locale to validate
     *
     * @throws InvalidArgumentException if locale is not valid
     *
     * @return void
     */
    protected function validate_locale($locale)
    {
        if (!$this->is_valid_locale($locale)) {
            throw new InvalidArgumentException('Locale or language cannot be empty and must be set through Listener or Entity');
        }
    }
    /**
     * Check if the given locale is valid
     */
    private function is_valid_locale(?string $locale): bool
    {
        return is_string($locale) && strlen($locale);
    }
    /**
     * Creates the translation for object being flushed
     *
     * @throws \UnexpectedValueException if locale is not valid, or
     *                                   primary key is composite, missing or invalid
     */
    private function handle_translatable_object_update(Translatable_Adapter $ea, object $object, bool $is_insert): void
    {
        $om = $ea->get_object_manager();
        $wrapped = Abstract_Wrapper::wrap($object, $om);
        $meta = $wrapped->get_metadata();
        $config = $this->get_configuration($om, $meta->get_name());
        // no need cache, metadata is loaded only once in MetadataFactoryClass
        $translation_class = $this->get_translation_class($ea, $config['useObjectClass']);
        $translation_metadata = $om->get_class_metadata($translation_class);
        // check for the availability of the primary key
        $object_id = $wrapped->get_identifier();
        // load the currently used locale
        $locale = $this->get_translatable_locale($object, $meta, $om);
        $uow = $om->get_unit_of_work();
        $oid = spl_object_id($object);
        $change_set = $ea->get_object_change_set($uow, $object);
        $translatable_fields = $config['fields'];
        foreach ($translatable_fields as $field) {
            $was_persisted_separetely = false;
            $skip = isset($this->translated_in_locale[$oid]) && $locale === $this->translated_in_locale[$oid];
            $skip = $skip && !isset($change_set[$field]) && !$this->get_translation_in_default_locale($oid, $field);
            if ($skip) {
                continue;
                // locale is same and nothing changed
            }
            $translation = null;
            foreach ($ea->get_scheduled_object_insertions($uow) as $trans) {
                if ($locale !== $this->default_locale && get_class($trans) === $translation_class && $trans->get_locale() === $this->default_locale && $trans->get_field() === $field && $this->belongs_to_object($ea, $trans, $object)) {
                    $this->set_translation_in_default_locale($oid, $field, $trans);
                    break;
                }
            }
            // lookup persisted translations
            foreach ($ea->get_scheduled_object_insertions($uow) as $trans) {
                if (get_class($trans) !== $translation_class) {
                    continue;
                }
                if ($trans->get_locale() !== $locale) {
                    continue;
                }
                if ($trans->get_field() !== $field) {
                    continue;
                }
                if ($ea->uses_personal_translation($translation_class)) {
                    $was_persisted_separetely = $trans->get_object() === $object;
                } else {
                    $was_persisted_separetely = $trans->get_object_class() === $config['useObjectClass'] && $trans->get_foreign_key() === $object_id;
                }
                if ($was_persisted_separetely) {
                    $translation = $trans;
                    break;
                }
            }
            // check if translation already is created
            if (!$is_insert && !$translation) {
                \assert($wrapped instanceof Abstract_Wrapper);
                $translation = $ea->find_translation($wrapped, $locale, $field, $translation_class, $config['useObjectClass']);
            }
            // create new translation if translation not already created and locale is different from default locale, otherwise, we have the date in the original record
            $persist_new_translation = !$translation && ($locale !== $this->default_locale || $this->persist_default_locale_translation);
            if ($persist_new_translation) {
                $translation = $translation_metadata->new_instance();
                $translation->set_locale($locale);
                $translation->set_field($field);
                if ($ea->uses_personal_translation($translation_class)) {
                    $translation->set_object($object);
                } else {
                    $translation->set_object_class($config['useObjectClass']);
                    $translation->set_foreign_key($object_id);
                }
            }
            if ($translation) {
                // set the translated field, take value using reflection
                $content = $ea->get_translation_value($object, $field);
                $translation->set_content($content);
                // check if need to update in database
                $trans_wrapper = Abstract_Wrapper::wrap($translation, $om);
                if ((null === $content && !$is_insert || is_bool($content) || is_int($content) || is_string($content) || !empty($content)) && ($is_insert || !$trans_wrapper->get_identifier() || isset($change_set[$field]))) {
                    if ($is_insert && !$object_id && !$ea->uses_personal_translation($translation_class)) {
                        // if we do not have the primary key yet available
                        // keep this translation in memory to insert it later with foreign key
                        $this->pending_translation_inserts[spl_object_id($object)][] = $translation;
                    } else if ($was_persisted_separetely) {
                        $ea->recompute_single_object_changeset($uow, $translation_metadata, $translation);
                    } else {
                        $om->persist($translation);
                        $uow->compute_change_set($translation_metadata, $translation);
                    }
                }
            }
            if ($is_insert && null !== $this->get_translation_in_default_locale($oid, $field)) {
                // We can't rely on object field value which is created in non-default locale.
                // If we provide translation for default locale as well, the latter is considered to be trusted
                // and object content should be overridden.
                $wrapped->set_property_value($field, $this->get_translation_in_default_locale($oid, $field)->get_content());
                $ea->recompute_single_object_changeset($uow, $meta, $object);
                $this->remove_translation_in_default_locale($oid, $field);
            }
        }
        $this->translated_in_locale[$oid] = $locale;
        // check if we have default translation and need to reset the translation
        if (!$is_insert && strlen($this->default_locale)) {
            $this->validate_locale($this->default_locale);
            $modified_change_set = $change_set;
            foreach ($change_set as $field => $changes) {
                if (!in_array($field, $translatable_fields, true)) {
                    continue;
                }
                if ($locale === $this->default_locale) {
                    continue;
                }
                $ea->set_original_object_property($uow, $object, $field, $changes[0]);
                unset($modified_change_set[$field]);
            }
            $ea->recompute_single_object_changeset($uow, $meta, $object);
            // cleanup current changeset only if working in a another locale different than de default one, otherwise the changeset will always be reverted
            if ($locale !== $this->default_locale) {
                $ea->clear_object_change_set($uow, $object);
                // recompute changeset only if there are changes other than reverted translations
                if ($modified_change_set || $this->has_translations_in_default_locale($oid)) {
                    foreach ($modified_change_set as $field => $changes) {
                        $ea->set_original_object_property($uow, $object, $field, $changes[0]);
                    }
                    foreach ($translatable_fields as $field) {
                        if (null !== $this->get_translation_in_default_locale($oid, $field)) {
                            $wrapped->set_property_value($field, $this->get_translation_in_default_locale($oid, $field)->get_content());
                            $this->remove_translation_in_default_locale($oid, $field);
                        }
                    }
                    $ea->recompute_single_object_changeset($uow, $meta, $object);
                }
            }
        }
    }
    /**
     * Removes translation object which represents translation in default language.
     * This is for internal use only.
     *
     * @param int    $oid   hash of the basic entity
     * @param string $field field of basic entity
     */
    private function remove_translation_in_default_locale(int $oid, string $field): void
    {
        if (isset($this->translation_in_default_locale[$oid])) {
            if (isset($this->translation_in_default_locale[$oid][$field])) {
                unset($this->translation_in_default_locale[$oid][$field]);
            }
            if (!$this->translation_in_default_locale[$oid]) {
                // We removed the final remaining elements from the
                // translationInDefaultLocale[$oid] array, so we might as well
                // completely remove the entry at $oid.
                unset($this->translation_in_default_locale[$oid]);
            }
        }
    }
    /**
     * Gets translation object which represents translation in default language.
     * This is for internal use only.
     *
     * @param int    $oid   hash of the basic entity
     * @param string $field field of basic entity
     *
     * @return object|Translatable|null Returns translation object if it exists or NULL otherwise
     */
    private function get_translation_in_default_locale(int $oid, string $field)
    {
        return $this->translation_in_default_locale[$oid][$field] ?? null;
    }
    /**
     * Checks if the translation entity belongs to the object in question
     */
    private function belongs_to_object(Translatable_Adapter $ea, object $trans, object $object): bool
    {
        if ($ea->uses_personal_translation(get_class($trans))) {
            return $trans->get_object() === $object;
        }
        return $trans->get_foreign_key() === $object->get_id() && $trans->get_object_class() === get_class($object);
    }
}