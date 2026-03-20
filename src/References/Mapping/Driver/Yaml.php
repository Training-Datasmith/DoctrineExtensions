<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\References\Mapping\Driver;

use Gedmo\Exception\Invalid_Mapping_Exception;
use Gedmo\Mapping\Driver;
use Gedmo\Mapping\Driver\File;
/**
 * @author Gonzalo Vilaseca <gonzalo.vilaseca@reiss.com>
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
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $valid_references = ['referenceOne' => [], 'referenceMany' => [], 'referenceManyEmbed' => []];
    /**
     * @return mixed[]
     */
    public function read_extended_metadata($meta, array &$config): array
    {
        $mapping = $this->_get_mapping($meta->get_name());
        if (isset($mapping['gedmo'], $mapping['gedmo']['reference'])) {
            foreach ($mapping['gedmo']['reference'] as $field => $field_mapping) {
                $reference = $field_mapping['reference'];
                if (!in_array($reference, array_keys($this->valid_references), true)) {
                    throw new Invalid_Mapping_Exception($reference . ' is not a valid reference, valid references are: ' . implode(', ', array_keys($this->valid_references)));
                }
                $config[$reference][$field] = ['field' => $field, 'type' => $field_mapping['type'], 'class' => $field_mapping['class']];
                if (array_key_exists('mappedBy', $field_mapping)) {
                    $config[$reference][$field]['mappedBy'] = $field_mapping['mappedBy'];
                }
                if (array_key_exists('identifier', $field_mapping)) {
                    $config[$reference][$field]['identifier'] = $field_mapping['identifier'];
                }
                if (array_key_exists('inversedBy', $field_mapping)) {
                    $config[$reference][$field]['inversedBy'] = $field_mapping['inversedBy'];
                }
            }
        }
        $config = array_merge($this->valid_references, $config);
        return $config;
    }
    protected function _load_mapping_file($file)
    {
        return \Symfony\Component\Yaml\Yaml::parse($file);
    }
}