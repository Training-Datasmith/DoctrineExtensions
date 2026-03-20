<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Uploadable\Mapping\Driver;

use Gedmo\Mapping\Annotation\Uploadable;
use Gedmo\Mapping\Annotation\Uploadable_File_Mime_Type;
use Gedmo\Mapping\Annotation\Uploadable_File_Name;
use Gedmo\Mapping\Annotation\Uploadable_File_Path;
use Gedmo\Mapping\Annotation\Uploadable_File_Size;
use Gedmo\Mapping\Driver\Abstract_Annotation_Driver;
use Gedmo\Uploadable\Mapping\Validator;
/**
 * Mapping driver for the uploaded extension which reads extended metadata from attributes on an uploadable class.
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @internal
 */
class Attribute extends Abstract_Annotation_Driver
{
    /**
     * Mapping object for the uploadable extension.
     */
    public const UPLOADABLE = Uploadable::class;
    /**
     * Mapping object to mark the field which will store the MIME type for an upload.
     */
    public const UPLOADABLE_FILE_MIME_TYPE = Uploadable_File_Mime_Type::class;
    /**
     * Mapping object to mark the field which will store the file name for an upload.
     */
    public const UPLOADABLE_FILE_NAME = Uploadable_File_Name::class;
    /**
     * Mapping object to mark the field which will store the filesystem path for an upload.
     */
    public const UPLOADABLE_FILE_PATH = Uploadable_File_Path::class;
    /**
     * Mapping object to mark the field which will store the file size for an upload.
     */
    public const UPLOADABLE_FILE_SIZE = Uploadable_File_Size::class;
    public function read_extended_metadata($meta, array &$config)
    {
        $class = $this->get_meta_reflection_class($meta);
        // class annotations
        if ($annot = $this->reader->get_class_annotation($class, self::UPLOADABLE)) {
            \assert($annot instanceof Uploadable);
            $config['uploadable'] = true;
            $config['allowOverwrite'] = $annot->allow_overwrite;
            $config['appendNumber'] = $annot->append_number;
            $config['path'] = $annot->path;
            $config['pathMethod'] = $annot->path_method;
            $config['fileMimeTypeField'] = false;
            $config['fileNameField'] = false;
            $config['filePathField'] = false;
            $config['fileSizeField'] = false;
            $config['callback'] = $annot->callback;
            $config['filenameGenerator'] = $annot->filename_generator;
            $config['maxSize'] = (float) $annot->max_size;
            $config['allowedTypes'] = $annot->allowed_types;
            $config['disallowedTypes'] = $annot->disallowed_types;
            foreach ($class->get_properties() as $prop) {
                if ($this->reader->get_property_annotation($prop, self::UPLOADABLE_FILE_MIME_TYPE)) {
                    $config['fileMimeTypeField'] = $prop->get_name();
                }
                if ($this->reader->get_property_annotation($prop, self::UPLOADABLE_FILE_NAME)) {
                    $config['fileNameField'] = $prop->get_name();
                }
                if ($this->reader->get_property_annotation($prop, self::UPLOADABLE_FILE_PATH)) {
                    $config['filePathField'] = $prop->get_name();
                }
                if ($this->reader->get_property_annotation($prop, self::UPLOADABLE_FILE_SIZE)) {
                    $config['fileSizeField'] = $prop->get_name();
                }
            }
            $config = Validator::validate_configuration($meta, $config);
        }
        /*
                // Code in case we need to identify entities which are not Uploadables, but have associations
                // with other Uploadable entities
        
                } else {
                    // We need to check if this class has a relation with Uploadable entities
                    $associations = $meta->getAssociationMappings();
        
                    foreach ($associations as $field => $association) {
                        $refl = new \ReflectionClass($association['targetEntity']);
        
                        if ($annot = $this->reader->getClassAnnotation($refl, self::UPLOADABLE)) {
                            \assert($annot instanceof Uploadable);
        
                            $config['hasUploadables'] = true;
        
                            if (!isset($config['uploadables'])) {
                                $config['uploadables'] = array();
                            }
        
                            $config['uploadables'][] = array(
                                'class'         => $association['targetEntity'],
                                'property'      => $association['fieldName']
                            );
                        }
                    }
                }*/
        $this->validate_full_metadata($meta, $config);
        return $config;
    }
}