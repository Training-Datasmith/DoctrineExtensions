<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Uploadable;

use Doctrine\Common\Event_Args;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\Persistence\Event\Load_Class_Metadata_Event_Args;
use Doctrine\Persistence\Event\Manager_Event_Args;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Notify_Property_Changed;
use Doctrine\Persistence\Object_Manager;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Exception\Uploadable_Cant_Write_Exception;
use Gedmo\Exception\Uploadable_Couldnt_Guess_Mime_Type_Exception;
use Gedmo\Exception\Uploadable_Extension_Exception;
use Gedmo\Exception\Uploadable_File_Already_Exists_Exception;
use Gedmo\Exception\Uploadable_Form_Size_Exception;
use Gedmo\Exception\Uploadable_Ini_Size_Exception;
use Gedmo\Exception\Uploadable_Invalid_Mime_Type_Exception;
use Gedmo\Exception\Uploadable_Max_Size_Exception;
use Gedmo\Exception\Uploadable_No_File_Exception;
use Gedmo\Exception\Uploadable_No_Path_Defined_Exception;
use Gedmo\Exception\Uploadable_No_Tmp_Dir_Exception;
use Gedmo\Exception\Uploadable_Partial_Exception;
use Gedmo\Exception\Uploadable_Upload_Exception;
use Gedmo\Mapping\Event\Adapter_Interface;
use Gedmo\Mapping\Mapped_Event_Subscriber;
use Gedmo\Uploadable\Event\Uploadable_Post_File_Process_Event_Args;
use Gedmo\Uploadable\Event\Uploadable_Pre_File_Process_Event_Args;
use Gedmo\Uploadable\File_Info\File_Info_Array;
use Gedmo\Uploadable\File_Info\File_Info_Interface;
use Gedmo\Uploadable\Filename_Generator\Filename_Generator_Interface;
use Gedmo\Uploadable\Mapping\Validator;
use Gedmo\Uploadable\Mime_Type\Mime_Type_Guesser;
use Gedmo\Uploadable\Mime_Type\Mime_Type_Guesser_Interface;
/**
 * Uploadable listener
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @phpstan-type UploadableConfiguration = array{
 *  filePathField?: string,
 *  uploadable?: bool,
 *  fileNameField?: string,
 *  allowOverwrite?: bool,
 *  appendNumber?: bool,
 *  maxSize?: float,
 *  path?: string,
 *  pathMethod?: string,
 *  allowedTypes?: string[],
 *  disallowedTypes?: string[],
 *  filenameGenerator?: Validator::FILENAME_GENERATOR_*|class-string<FilenameGeneratorInterface>,
 *  fileMimeTypeField?: string,
 *  fileSizeField?: string,
 *  callback?: string,
 * }
 *
 * @phpstan-extends MappedEventSubscriber<UploadableConfiguration, AdapterInterface>
 */
class Uploadable_Listener extends Mapped_Event_Subscriber
{
    public const ACTION_INSERT = 'INSERT';
    public const ACTION_UPDATE = 'UPDATE';
    /**
     * Default path to move files in
     *
     * @var string
     */
    private $default_path;
    /**
     * Mime type guesser
     */
    private Mime_Type_Guesser_Interface $mime_type_guesser;
    /**
     * Default FileInfoInterface class
     *
     * @var class-string<FileInfoInterface>
     */
    private string $default_file_info_class = File_Info_Array::class;
    /**
     * Array of files to remove on postFlush
     *
     * @var array<int, string>
     */
    private array $pending_file_removals = [];
    /**
     * Array of FileInfoInterface objects. The index is the hash of the entity owner
     * of the FileInfoInterface object.
     *
     * @var array<int, array<string, object>>
     *
     * @phpstan-var array<int, array{entity: object, fileInfo: FileInfoInterface}>
     */
    private array $file_info_objects = [];
    public function __construct(?Mime_Type_Guesser_Interface $mime_type_guesser = null)
    {
        parent::__construct();
        $this->mime_type_guesser = $mime_type_guesser ?? new Mime_Type_Guesser();
    }
    /**
     * @return string[]
     */
    public function get_subscribed_events(): array
    {
        return ['loadClassMetadata', 'preFlush', 'onFlush', 'postFlush'];
    }
    /**
     * This event is needed in special cases where the entity needs to be updated, but it only has the
     * file field modified. Since we can't mark an entity as "dirty" in the "addEntityFileInfo" method,
     * doctrine thinks the entity has no changes, which produces that the "onFlush" event gets never called.
     * Here we mark the entity as dirty, so the "onFlush" event gets called, and the file is processed.
     *
     * @param ManagerEventArgs $args
     *
     * @phpstan-param ManagerEventArgs<ObjectManager> $args
     */
    public function pre_flush(Event_Args $args): void
    {
        if ([] === $this->file_info_objects) {
            // Nothing to do
            return;
        }
        $ea = $this->get_event_adapter($args);
        $om = $ea->get_object_manager();
        $uow = $om->get_unit_of_work();
        foreach ($this->file_info_objects as $info) {
            $entity = $info['entity'];
            $meta = $om->get_class_metadata(get_class($entity));
            $config = $this->get_configuration($om, $meta->get_name());
            // If the entity is in the identity map, it means it will be updated. We need to force the
            // "dirty check" here by "modifying" the path. We are actually setting the same value, but
            // this will mark the entity as dirty, and the "onFlush" event will be fired, even if there's
            // no other change in the entity's fields apart from the file itself.
            if ($uow->is_in_identity_map($entity)) {
                if ($config['filePathField']) {
                    $path = $this->get_file_path_field_value($meta, $config, $entity);
                    $uow->property_changed($entity, $config['filePathField'], $path, $path);
                } else {
                    $file_name = $this->get_file_name_field_value($meta, $config, $entity);
                    $uow->property_changed($entity, $config['fileNameField'], $file_name, $file_name);
                }
                $uow->schedule_for_update($entity);
            }
        }
    }
    /**
     * Handle file-uploading depending on the action
     * being done with objects
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
        // Do we need to upload files?
        foreach ($this->file_info_objects as $info) {
            $entity = $info['entity'];
            $scheduled_for_insert = $uow->is_scheduled_for_insert($entity);
            $scheduled_for_update = $uow->is_scheduled_for_update($entity);
            $action = $scheduled_for_insert || $scheduled_for_update ? $scheduled_for_insert ? self::ACTION_INSERT : self::ACTION_UPDATE : false;
            if ($action) {
                $this->process_file($ea, $entity, $action);
            }
        }
        // Do we need to remove any files?
        foreach ($ea->get_scheduled_object_deletions($uow) as $object) {
            $meta = $om->get_class_metadata(get_class($object));
            if ($config = $this->get_configuration($om, $meta->get_name())) {
                if (isset($config['uploadable']) && $config['uploadable']) {
                    $this->add_file_removal($meta, $config, $object);
                }
            }
        }
    }
    /**
     * Handle removal of files
     */
    public function post_flush(Event_Args $args): void
    {
        if ([] !== $this->pending_file_removals) {
            foreach ($this->pending_file_removals as $file) {
                $this->remove_file($file);
            }
            $this->pending_file_removals = [];
        }
        $this->file_info_objects = [];
    }
    /**
     * If it's a Uploadable object, verify if the file was uploaded.
     * If that's the case, process it.
     *
     * @param object $object
     * @param string $action
     *
     * @throws UploadableNoPathDefinedException
     * @throws UploadableCouldntGuessMimeTypeException
     * @throws UploadableMaxSizeException
     * @throws UploadableInvalidMimeTypeException
     */
    public function process_file(Adapter_Interface $ea, $object, $action): void
    {
        $oid = spl_object_id($object);
        $om = $ea->get_object_manager();
        \assert($om instanceof Entity_Manager_Interface);
        $uow = $om->get_unit_of_work();
        $meta = $om->get_class_metadata(get_class($object));
        $config = $this->get_configuration($om, $meta->get_name());
        if (!$config || !isset($config['uploadable']) || !$config['uploadable']) {
            // Nothing to do
            return;
        }
        $refl = $meta->get_reflection_class();
        $file_info = $this->file_info_objects[$oid]['fileInfo'];
        $evm = $om->get_event_manager();
        if ($evm->has_listeners(Events::uploadablePreFileProcess)) {
            $evm->dispatch_event(Events::uploadablePreFileProcess, new Uploadable_Pre_File_Process_Event_Args($this, $om, $config, $file_info, $object, $action));
        }
        // Validations
        if ($config['maxSize'] > 0 && $file_info->get_size() > $config['maxSize']) {
            $msg = 'File "%s" exceeds the maximum allowed size of %d bytes. File size: %d bytes';
            throw new Uploadable_Max_Size_Exception(sprintf($msg, $file_info->get_name(), $config['maxSize'], $file_info->get_size()));
        }
        $mime = $this->mime_type_guesser->guess($file_info->get_tmp_name());
        if (null === $mime) {
            throw new Uploadable_Couldnt_Guess_Mime_Type_Exception(sprintf('Couldn\'t guess mime type for file "%s".', $file_info->get_name()));
        }
        if ($config['allowedTypes'] || $config['disallowedTypes']) {
            $ok = $config['allowedTypes'] ? false : true;
            $mimes = $config['allowedTypes'] ?: $config['disallowedTypes'];
            foreach ($mimes as $m) {
                if ($mime === $m) {
                    $ok = $config['allowedTypes'] ? true : false;
                    break;
                }
            }
            if (!$ok) {
                throw new Uploadable_Invalid_Mime_Type_Exception(sprintf('Invalid mime type "%s" for file "%s".', $mime, $file_info->get_name()));
            }
        }
        $path = $this->get_path($meta, $config, $object);
        if (self::ACTION_UPDATE === $action) {
            // First we add the original file to the pendingFileRemovals array
            $this->add_file_removal($meta, $config, $object);
        }
        // We generate the filename based on configuration
        $generator_namespace = 'Gedmo\Uploadable\FilenameGenerator';
        switch ($config['filenameGenerator']) {
            case Validator::FILENAME_GENERATOR_ALPHANUMERIC:
                $generator_class = $generator_namespace . '\FilenameGeneratorAlphanumeric';
                break;
            case Validator::FILENAME_GENERATOR_SHA1:
                $generator_class = $generator_namespace . '\FilenameGeneratorSha1';
                break;
            case Validator::FILENAME_GENERATOR_NONE:
                $generator_class = false;
                break;
            default:
                $generator_class = $config['filenameGenerator'];
        }
        $info = $this->move_file($file_info, $path, $generator_class, $config['allowOverwrite'], $config['appendNumber'], $object);
        // We override the mime type with the guessed one
        $info['fileMimeType'] = $mime;
        if ('' !== $config['callback']) {
            $callback_method = $refl->get_method($config['callback']);
            if (PHP_VERSION_ID < 80100) {
                $callback_method->set_accessible(true);
            }
            $callback_method->invoke_args($object, [$info]);
        }
        if ($config['filePathField']) {
            $this->update_field($object, $uow, $ea, $meta, $config['filePathField'], $info['filePath']);
        }
        if ($config['fileNameField']) {
            $this->update_field($object, $uow, $ea, $meta, $config['fileNameField'], $info['fileName']);
        }
        if ($config['fileMimeTypeField']) {
            $this->update_field($object, $uow, $ea, $meta, $config['fileMimeTypeField'], $info['fileMimeType']);
        }
        if ($config['fileSizeField']) {
            $value = $om->get_connection()->convert_to_php_value($info['fileSize'], $meta->get_type_of_field($config['fileSizeField']));
            $this->update_field($object, $uow, $ea, $meta, $config['fileSizeField'], $value);
        }
        $ea->recompute_single_object_change_set($uow, $meta, $object);
        if ($evm->has_listeners(Events::uploadablePostFileProcess)) {
            $evm->dispatch_event(Events::uploadablePostFileProcess, new Uploadable_Post_File_Process_Event_Args($this, $om, $config, $file_info, $object, $action));
        }
        unset($this->file_info_objects[$oid]);
    }
    /**
     * Simple wrapper for the function "unlink" to ease testing
     *
     * @param string $filePath
     *
     * @return bool
     */
    public function remove_file($file_path)
    {
        if (is_file($file_path)) {
            return @unlink($file_path);
        }
        return false;
    }
    /**
     * Moves the file to the specified path
     *
     * @param string|bool $filenameGeneratorClass
     * @param bool        $overwrite
     * @param bool        $appendNumber
     * @param object      $object
     *
     * @phpstan-param class-string|false $filenameGeneratorClass
     *
     * @throws UploadableUploadException
     * @throws UploadableNoFileException
     * @throws UploadableExtensionException
     * @throws UploadableIniSizeException
     * @throws UploadableFormSizeException
     * @throws UploadableFileAlreadyExistsException
     * @throws UploadablePartialException
     * @throws UploadableNoTmpDirException
     * @throws UploadableCantWriteException
     * @return array<string, int|string|null>
     */
    public function move_file(File_Info_Interface $file_info, string $path, $filename_generator_class = false, $overwrite = false, $append_number = false, $object = null): array
    {
        if ($file_info->get_error() > 0) {
            switch ($file_info->get_error()) {
                case 1:
                    $msg = 'Size of uploaded file "%s" exceeds limit imposed by directive "upload_max_filesize" in php.ini';
                    throw new Uploadable_Ini_Size_Exception(sprintf($msg, $file_info->get_name()));
                case 2:
                    $msg = 'Size of uploaded file "%s" exceeds limit imposed by option MAX_FILE_SIZE in your form.';
                    throw new Uploadable_Form_Size_Exception(sprintf($msg, $file_info->get_name()));
                case 3:
                    $msg = 'File "%s" was partially uploaded.';
                    throw new Uploadable_Partial_Exception(sprintf($msg, $file_info->get_name()));
                case 4:
                    $msg = 'No file was uploaded!';
                    throw new Uploadable_No_File_Exception($msg);
                case 6:
                    $msg = 'Upload failed. Temp dir is missing.';
                    throw new Uploadable_No_Tmp_Dir_Exception($msg);
                case 7:
                    $msg = 'File "%s" couldn\'t be uploaded because directory is not writable.';
                    throw new Uploadable_Cant_Write_Exception(sprintf($msg, $file_info->get_name()));
                case 8:
                    $msg = 'A PHP Extension stopped the uploaded for some reason.';
                    throw new Uploadable_Extension_Exception($msg);
                default:
                    throw new Uploadable_Upload_Exception(sprintf('There was an unknown problem while uploading file "%s"', $file_info->get_name()));
            }
        }
        $info = ['fileName' => '', 'fileExtension' => '', 'fileWithoutExt' => '', 'origFileName' => '', 'filePath' => '', 'fileMimeType' => $file_info->get_type(), 'fileSize' => $file_info->get_size()];
        $info['fileName'] = basename($file_info->get_name());
        $info['filePath'] = $path . '/' . $info['fileName'];
        $has_extension = strrpos($info['fileName'], '.');
        if ($has_extension) {
            $info['fileExtension'] = substr($info['filePath'], strrpos($info['filePath'], '.'));
            $info['fileWithoutExt'] = substr($info['filePath'], 0, strrpos($info['filePath'], '.'));
        } else {
            $info['fileWithoutExt'] = $info['filePath'];
        }
        // Save the original filename for later use
        $info['origFileName'] = $info['fileName'];
        // Now we generate the filename using the configured class
        if (false !== $filename_generator_class) {
            $filename = $filename_generator_class::generate(str_replace($path . '/', '', $info['fileWithoutExt']), $info['fileExtension'], $object);
            $info['filePath'] = str_replace('/' . $info['fileName'], '/' . $filename, $info['filePath']);
            $info['fileName'] = $filename;
            if ($pos = strrpos($info['filePath'], '.')) {
                // ignores positions like "./file" at 0 see #915
                $info['fileWithoutExt'] = substr($info['filePath'], 0, $pos);
            } else {
                $info['fileWithoutExt'] = $info['filePath'];
            }
        }
        if (is_file($info['filePath'])) {
            if ($overwrite) {
                $this->cancel_file_removal($info['filePath']);
                $this->remove_file($info['filePath']);
            } elseif ($append_number) {
                $counter = 1;
                $info['filePath'] = $info['fileWithoutExt'] . '-' . $counter . $info['fileExtension'];
                do {
                    $info['filePath'] = $info['fileWithoutExt'] . '-' . ++$counter . $info['fileExtension'];
                } while (is_file($info['filePath']));
            } else {
                throw new Uploadable_File_Already_Exists_Exception(sprintf('File "%s" already exists!', $info['filePath']));
            }
        }
        if (!$this->do_move_file($file_info->get_tmp_name(), $info['filePath'], $file_info->is_uploaded_file())) {
            throw new Uploadable_Upload_Exception(sprintf('File "%s" was not uploaded, or there was a problem moving it to the location "%s".', $file_info->get_name(), $path));
        }
        return $info;
    }
    /**
     * Simple wrapper method used to move the file. If it's an uploaded file
     * it will use the "move_uploaded_file method. If it's not, it will
     * simple move it
     *
     * @param string $source         Source file
     * @param string $dest           Destination file
     * @param bool   $isUploadedFile Whether this is an uploaded file?
     */
    public function do_move_file($source, $dest, $is_uploaded_file = true): bool
    {
        return $is_uploaded_file ? @move_uploaded_file($source, $dest) : @copy($source, $dest);
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
     * Sets the default path
     *
     * @param string $path
     */
    public function set_default_path($path): void
    {
        $this->default_path = $path;
    }
    /**
     * Returns default path
     *
     * @return string|null
     */
    public function get_default_path()
    {
        return $this->default_path;
    }
    /**
     * Sets file info default class
     *
     * @param string $defaultFileInfoClass
     */
    public function set_default_file_info_class($default_file_info_class): void
    {
        if (!is_string($default_file_info_class) || !class_exists($default_file_info_class) || !is_subclass_of($default_file_info_class, File_Info_Interface::class)) {
            throw new InvalidArgumentException(sprintf('Default FileInfo class must be a valid class, and it must implement "%s".', File_Info_Interface::class));
        }
        $this->default_file_info_class = $default_file_info_class;
    }
    /**
     * Returns file info default class
     *
     * @return class-string<FileInfoInterface>
     */
    public function get_default_file_info_class(): string
    {
        return $this->default_file_info_class;
    }
    /**
     * Adds a FileInfoInterface object for the given entity
     *
     * @param object                                 $entity
     * @param array<string, mixed>|FileInfoInterface $fileInfo
     *
     * @throws \RuntimeException
     */
    public function add_entity_file_info($entity, $file_info): void
    {
        $file_info_class = $this->get_default_file_info_class();
        $file_info = is_array($file_info) ? new $file_info_class($file_info) : $file_info;
        if (!$file_info instanceof File_Info_Interface) {
            $msg = 'You must pass an instance of FileInfoInterface or a valid array for entity of class "%s".';
            throw new \RuntimeException(sprintf($msg, get_class($entity)));
        }
        $this->file_info_objects[spl_object_id($entity)] = ['entity' => $entity, 'fileInfo' => $file_info];
    }
    /**
     * @param object $entity
     *
     * @return FileInfoInterface
     */
    public function get_entity_file_info($entity)
    {
        $oid = spl_object_id($entity);
        if (!isset($this->file_info_objects[$oid])) {
            throw new \RuntimeException(sprintf('There\'s no FileInfoInterface object for entity of class "%s".', get_class($entity)));
        }
        return $this->file_info_objects[$oid]['fileInfo'];
    }
    public function set_mime_type_guesser(Mime_Type_Guesser_Interface $mime_type_guesser): void
    {
        $this->mime_type_guesser = $mime_type_guesser;
    }
    public function get_mime_type_guesser(): \Gedmo\Uploadable\Mime_Type\Mime_Type_Guesser_Interface
    {
        return $this->mime_type_guesser;
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     * @param object                $object Entity
     *
     * @throws UploadableNoPathDefinedException
     */
    protected function get_path(Class_Metadata $meta, array $config, ?object $object): string
    {
        $path = $config['path'];
        if ('' === $path) {
            $default_path = $this->get_default_path();
            if ('' !== $config['pathMethod']) {
                $get_path_method = \Closure::bind(fn(string $path_method, ?string $default_path): string => $this->{$path_method}($default_path), $object, $meta->get_reflection_class()->get_name());
                $path = $get_path_method($config['pathMethod'], $default_path);
            } elseif (null !== $default_path) {
                $path = $default_path;
            } else {
                $msg = 'You have to define the path to save files either in the listener, or in the class "%s"';
                throw new Uploadable_No_Path_Defined_Exception(sprintf($msg, $meta->get_name()));
            }
        }
        Validator::validate_path($path);
        return rtrim($path, '\/');
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     * @param object                $object Entity
     *
     * @return void
     */
    protected function add_file_removal(\Doctrine\Persistence\Mapping\Class_Metadata $meta, array $config, $object)
    {
        if ($config['filePathField']) {
            $this->pending_file_removals[] = $this->get_file_path_field_value($meta, $config, $object);
        } else {
            $path = $this->get_path($meta, $config, $object);
            $file_name = $this->get_file_name_field_value($meta, $config, $object);
            $this->pending_file_removals[] = $path . DIRECTORY_SEPARATOR . $file_name;
        }
    }
    /**
     * @param string $filePath
     *
     * @return void
     */
    protected function cancel_file_removal($file_path)
    {
        $k = array_search($file_path, $this->pending_file_removals, true);
        if (false !== $k) {
            unset($this->pending_file_removals[$k]);
        }
    }
    /**
     * Returns value of the entity's property
     *
     * @param ClassMetadata<object> $meta
     * @param string                $propertyName
     * @param object                $object
     *
     * @return mixed
     */
    protected function get_property_value_from_object(Class_Metadata $meta, $property_name, ?object $object)
    {
        $get_file_path = \Closure::bind(fn(string $property_name) => $this->{$property_name}, $object, $meta->get_reflection_class()->get_name());
        return $get_file_path($property_name);
    }
    /**
     * Returns the path of the entity's file
     *
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     * @param object                $object
     *
     * @return string
     */
    protected function get_file_path_field_value(Class_Metadata $meta, array $config, $object)
    {
        return $this->get_property_value_from_object($meta, $config['filePathField'], $object);
    }
    /**
     * Returns the name of the entity's file
     *
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     * @param object                $object
     *
     * @return string
     */
    protected function get_file_name_field_value(Class_Metadata $meta, array $config, $object)
    {
        return $this->get_property_value_from_object($meta, $config['fileNameField'], $object);
    }
    protected function get_namespace(): string
    {
        return __NAMESPACE__;
    }
    /**
     * @param object                $object
     * @param object                $uow
     * @param ClassMetadata<object> $meta
     * @param string                $field
     * @param mixed                 $value
     * @param bool                  $notifyPropertyChanged
     *
     * @return void
     */
    protected function update_field($object, $uow, Adapter_Interface $ea, Class_Metadata $meta, $field, $value, $notify_property_changed = true)
    {
        $old_value = $meta->get_field_value($object, $field);
        $meta->set_field_value($object, $field, $value);
        if ($notify_property_changed && $object instanceof Notify_Property_Changed) {
            $uow = $ea->get_object_manager()->get_unit_of_work();
            $uow->property_changed($object, $field, $old_value, $value);
        }
    }
}