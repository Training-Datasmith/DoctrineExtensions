<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver;
use Gedmo\Mapping\Driver\File;
use Gedmo\Soft_Deleteable\Mapping\Validator;
/**
 * This is a yaml mapping driver for Timestampable
 * behavioral extension. Used for extraction of extended
 * metadata from yaml specifically for Timestampable
 * extension.
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
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
    public function read_extended_metadata($meta, array &$config): array
    {
        $mapping = $this->_get_mapping($meta->get_name());
        if (isset($mapping['gedmo'])) {
            $class_mapping = $mapping['gedmo'];
            if (isset($class_mapping['soft_deleteable'])) {
                $config['softDeleteable'] = true;
                if (!isset($class_mapping['soft_deleteable']['field_name'])) {
                    throw new Invalid_Mapping_Exception('Field name for SoftDeleteable class is mandatory.');
                }
                $field_name = $class_mapping['soft_deleteable']['field_name'];
                Validator::validate_field($meta, $field_name);
                $config['fieldName'] = $field_name;
                $config['timeAware'] = false;
                if (isset($class_mapping['soft_deleteable']['time_aware'])) {
                    if (!is_bool($class_mapping['soft_deleteable']['time_aware'])) {
                        throw new Invalid_Mapping_Exception('timeAware must be boolean. ' . gettype($class_mapping['soft_deleteable']['time_aware']) . ' provided.');
                    }
                    $config['timeAware'] = $class_mapping['soft_deleteable']['time_aware'];
                }
                $config['hardDelete'] = true;
                if (isset($class_mapping['soft_deleteable']['hard_delete'])) {
                    if (!is_bool($class_mapping['soft_deleteable']['hard_delete'])) {
                        throw new Invalid_Mapping_Exception('hardDelete must be boolean. ' . gettype($class_mapping['soft_deleteable']['hard_delete']) . ' provided.');
                    }
                    $config['hardDelete'] = $class_mapping['soft_deleteable']['hard_delete'];
                }
            }
        }
        return $config;
    }
    protected function _load_mapping_file($file)
    {
        return \Symfony\Component\Yaml\Yaml::parse(file_get_contents($file));
    }
}