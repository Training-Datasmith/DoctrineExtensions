<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Uploadable\Mapping;

use Doctrine\Persistence\Mapping\Class_Metadata;
use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Exception\Uploadable_Cant_Write_Exception;
use Gedmo\Exception\Uploadable_Invalid_Path_Exception;
use Gedmo\Uploadable\Filename_Generator\Filename_Generator_Interface;
/**
 * This class is used to validate mapping information
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Validator
{
    public const UPLOADABLE_FILE_MIME_TYPE = 'UploadableFileMimeType';
    public const UPLOADABLE_FILE_NAME = 'UploadableFileName';
    public const UPLOADABLE_FILE_PATH = 'UploadableFilePath';
    public const UPLOADABLE_FILE_SIZE = 'UploadableFileSize';
    public const FILENAME_GENERATOR_SHA1 = 'SHA1';
    public const FILENAME_GENERATOR_ALPHANUMERIC = 'ALPHANUMERIC';
    public const FILENAME_GENERATOR_NONE = 'NONE';
    /**
     * Determines if we should throw an exception in the case the "allowedTypes" and
     * "disallowedTypes" options are BOTH set. Useful for testing purposes
     *
     * @var bool
     */
    public static $enable_mime_types_config_exception = true;
    /**
     * List of types which are valid for UploadableFileMimeType field
     *
     * @var string[]
     */
    public static $valid_file_mime_type_types = ['string'];
    /**
     * List of types which are valid for UploadableFileName field
     *
     * @var string[]
     */
    public static $valid_file_name_types = ['string'];
    /**
     * List of types which are valid for UploadableFilePath field
     *
     * @var string[]
     */
    public static $valid_file_path_types = ['string'];
    /**
     * List of types which are valid for UploadableFileSize field for ORM
     *
     * @var string[]
     */
    public static $valid_file_size_types = ['decimal'];
    /**
     * List of types which are valid for UploadableFileSize field for ODM
     *
     * @var string[]
     */
    public static $valid_file_size_types_odm = ['float'];
    /**
     * Whether to validate if the directory of the file exists and is writable, useful to disable it when using
     * stream wrappers which don't support is_dir (like Gaufrette)
     *
     * @var bool
     */
    public static $validate_writable_directory = true;
    /**
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    public static function validate_file_name_field(Class_Metadata $meta, $field): void
    {
        self::validate_field($meta, $field, self::UPLOADABLE_FILE_NAME, self::$valid_file_name_types);
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    public static function validate_file_mime_type_field(Class_Metadata $meta, $field): void
    {
        self::validate_field($meta, $field, self::UPLOADABLE_FILE_MIME_TYPE, self::$valid_file_mime_type_types);
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    public static function validate_file_path_field(Class_Metadata $meta, $field): void
    {
        self::validate_field($meta, $field, self::UPLOADABLE_FILE_PATH, self::$valid_file_path_types);
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param string                $field
     */
    public static function validate_file_size_field(Class_Metadata $meta, $field): void
    {
        self::validate_field($meta, $field, self::UPLOADABLE_FILE_SIZE, self::$valid_file_size_types);
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param string                $uploadableField
     * @param string[]              $validFieldTypes
     *
     */
    public static function validate_field($meta, string $field, $uploadable_field, $valid_field_types): void
    {
        if ($meta->is_mapped_superclass) {
            return;
        }
        $field_mapping = $meta->get_field_mapping($field);
        if (!in_array($field_mapping->type ?? $field_mapping['type'], $valid_field_types, true)) {
            $msg = 'Field "%s" to work as an "%s" field must be of one of the following types: "%s".';
            throw new Invalid_Mapping_Exception(sprintf($msg, $field, $uploadable_field, implode(', ', $valid_field_types)));
        }
    }
    /**
     * @param string $path
     */
    public static function validate_path($path): void
    {
        if (!is_string($path) || '' === $path) {
            throw new Uploadable_Invalid_Path_Exception('Path must be a string containing the path to a valid directory.');
        }
        if (!self::$validate_writable_directory) {
            return;
        }
        if (!is_dir($path) && !@mkdir($path, 0777, true)) {
            throw new Uploadable_Invalid_Path_Exception(sprintf('Unable to create "%s" directory.', $path));
        }
        if (!is_writable($path)) {
            throw new Uploadable_Cant_Write_Exception(sprintf('Directory "%s" is not writable.', $path));
        }
    }
    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     *
     * @return array<string, mixed>
     *
     * @todo Stop receiving by reference the `$config` parameter and use `array` as return type declaration
     */
    public static function validate_configuration(Class_Metadata $meta, array &$config): array
    {
        if (!$config['filePathField'] && !$config['fileNameField']) {
            throw new Invalid_Mapping_Exception(sprintf('Class "%s" must have an UploadableFilePath or UploadableFileName field.', $meta->get_name()));
        }
        $refl = $meta->get_reflection_class();
        if ('' !== $config['pathMethod'] && !$refl->has_method($config['pathMethod'])) {
            throw new Invalid_Mapping_Exception(sprintf('Class "%s" doesn\'t have method "%s"!', $meta->get_name(), $config['pathMethod']));
        }
        if ('' !== $config['callback'] && !$refl->has_method($config['callback'])) {
            throw new Invalid_Mapping_Exception(sprintf('Class "%s" doesn\'t have method "%s"!', $meta->get_name(), $config['callback']));
        }
        $config['maxSize'] = (float) $config['maxSize'];
        if ($config['maxSize'] < 0) {
            throw new Invalid_Mapping_Exception(sprintf('Option "maxSize" must be a number >= 0 for class "%s".', $meta->get_name()));
        }
        if (self::$enable_mime_types_config_exception && '' !== $config['allowedTypes'] && '' !== $config['disallowedTypes']) {
            $msg = 'You\'ve set "allowedTypes" and "disallowedTypes" options. You must set only one in class "%s".';
            throw new Invalid_Mapping_Exception(sprintf($msg, $meta->get_name()));
        }
        $config['allowedTypes'] = $config['allowedTypes'] ? false !== strpos($config['allowedTypes'], ',') ? explode(',', $config['allowedTypes']) : [$config['allowedTypes']] : false;
        $config['disallowedTypes'] = $config['disallowedTypes'] ? false !== strpos($config['disallowedTypes'], ',') ? explode(',', $config['disallowedTypes']) : [$config['disallowedTypes']] : false;
        if ($config['fileNameField']) {
            self::validate_file_name_field($meta, $config['fileNameField']);
        }
        if ($config['filePathField']) {
            self::validate_file_path_field($meta, $config['filePathField']);
        }
        if ($config['fileMimeTypeField']) {
            self::validate_file_mime_type_field($meta, $config['fileMimeTypeField']);
        }
        if ($config['fileSizeField']) {
            self::validate_file_size_field($meta, $config['fileSizeField']);
        }
        switch ((string) $config['filenameGenerator']) {
            case self::FILENAME_GENERATOR_ALPHANUMERIC:
            case self::FILENAME_GENERATOR_SHA1:
            case self::FILENAME_GENERATOR_NONE:
                break;
            default:
                if (!class_exists($config['filenameGenerator']) || !is_subclass_of($config['filenameGenerator'], Filename_Generator_Interface::class)) {
                    throw new Invalid_Mapping_Exception(sprintf('Class "%s" needs a valid value for filenameGenerator. It can be: SHA1, ALPHANUMERIC, NONE or a class implementing %s.', $meta->get_name(), Filename_Generator_Interface::class));
                }
        }
        return $config;
    }
}