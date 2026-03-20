<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Reference_Integrity\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver;
use Gedmo\Mapping\Driver\File;
use Gedmo\Reference_Integrity\Mapping\Validator;
/**
 * This is a yaml mapping driver for ReferenceIntegrity
 * extension. Used for extraction of extended
 * metadata from yaml specifically for ReferenceIntegrity
 * extension.
 *
 * @author Evert Harmeling <evert.harmeling@freshheads.com>
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
        $validator = new Validator();
        if (isset($mapping['fields'])) {
            foreach ($mapping['fields'] as $property => $field_mapping) {
                if (isset($field_mapping['gedmo']['referenceIntegrity'])) {
                    if (!$meta->has_field($property)) {
                        throw new Invalid_Mapping_Exception(sprintf('Unable to find reference integrity [%s] as mapped property in entity - %s', $property, $meta->get_name()));
                    }
                    if (empty($mapping['fields'][$property]['mappedBy'])) {
                        throw new Invalid_Mapping_Exception(sprintf("'mappedBy' should be set on '%s' in '%s'", $property, $meta->get_name()));
                    }
                    if (!in_array($field_mapping['gedmo']['referenceIntegrity'], $validator->get_integrity_actions(), true)) {
                        throw new Invalid_Mapping_Exception(sprintf('Field - [%s] does not have a valid integrity option, [%s] in class - %s', $property, implode(', ', $validator->get_integrity_actions()), $meta->get_name()));
                    }
                    $config['referenceIntegrity'][$property][$mapping['fields'][$property]['mappedBy']] = $field_mapping['gedmo']['referenceIntegrity'];
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