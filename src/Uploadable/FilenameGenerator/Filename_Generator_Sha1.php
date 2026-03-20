<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Uploadable\Filename_Generator;

/**
 * FilenameGeneratorSha1
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Filename_Generator_Sha1 implements Filename_Generator_Interface
{
    public static function generate($filename, $extension, $object = null): string
    {
        return sha1(uniqid($filename . $extension, true)) . $extension;
    }
}