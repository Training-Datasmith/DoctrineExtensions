<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver;
use Gedmo\Mapping\Driver\File;
/**
 * This is a yaml mapping driver for Translatable
 * behavioral extension. Used for extraction of extended
 * metadata from yaml specifically for Translatable
 * extension.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @deprecated since gedmo/doctrine-extensions 3.5, will be removed in version 4.0.
 *
 * @internal
 */
class Yaml extends File implements Driver
{
    /**
     * File extension
     *
     * @var string
     */
    protected $_extension = '.dcm.yml';
    public function read_extended_metadata($meta, array &$config)
    {
        $mapping = $this->_get_mapping($meta->get_name());
        if (isset($mapping['gedmo'])) {
            $class_mapping = $mapping['gedmo'];
            if (isset($class_mapping['translation']['entity'])) {
                $translation_entity = $class_mapping['translation']['entity'];
                if (!$cl = $this->get_related_class_name($meta, $translation_entity)) {
                    throw new Invalid_Mapping_Exception("Translation entity class: {$translation_entity} does not exist.");
                }
                $config['translationClass'] = $cl;
            }
            if (isset($class_mapping['translation']['locale'])) {
                $config['locale'] = $class_mapping['translation']['locale'];
            } elseif (isset($class_mapping['translation']['language'])) {
                $config['locale'] = $class_mapping['translation']['language'];
            }
        }
        if (isset($mapping['fields'])) {
            foreach ($mapping['fields'] as $field => $field_mapping) {
                $config = $this->build_field_configuration($field, $field_mapping, $config);
            }
        }
        if (isset($mapping['attributeOverride'])) {
            foreach ($mapping['attributeOverride'] as $field => $override_mapping) {
                $config = $this->build_field_configuration($field, $override_mapping, $config);
            }
        }
        if (!$meta->is_mapped_superclass && $config) {
            if (is_array($meta->get_identifier()) && count($meta->get_identifier()) > 1) {
                throw new Invalid_Mapping_Exception("Translatable does not support composite identifiers in class - {$meta->get_name()}");
            }
        }
        return $config;
    }
    protected function _load_mapping_file($file)
    {
        return \Symfony\Component\Yaml\Yaml::parse(file_get_contents($file));
    }
    /**
     * @param array<string, mixed> $fieldMapping
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function build_field_configuration(string $field, array $field_mapping, array $config): array
    {
        if (isset($field_mapping['gedmo'])) {
            if (in_array('translatable', $field_mapping['gedmo'], true) || isset($field_mapping['gedmo']['translatable'])) {
                // fields cannot be overrided and throws mapping exception
                $config['fields'][] = $field;
                if (isset($field_mapping['gedmo']['translatable']['fallback'])) {
                    $config['fallback'][$field] = $field_mapping['gedmo']['translatable']['fallback'];
                }
            }
        }
        return $config;
    }
}