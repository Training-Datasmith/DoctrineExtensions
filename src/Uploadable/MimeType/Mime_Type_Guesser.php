<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Uploadable\Mime_Type;

use Gedmo\Exception\Uploadable_File_Not_Readable_Exception;
use Gedmo\Exception\Uploadable_Invalid_File_Exception;
/**
 * Mime type guesser
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Mime_Type_Guesser implements Mime_Type_Guesser_Interface
{
    public function guess($file_path)
    {
        if (!is_file($file_path)) {
            throw new Uploadable_Invalid_File_Exception(sprintf('File "%s" does not exist.', $file_path));
        }
        if (!is_readable($file_path)) {
            throw new Uploadable_File_Not_Readable_Exception(sprintf('File "%s" is not readable.', $file_path));
        }
        if (function_exists('finfo_open')) {
            if (!$finfo = new \finfo(FILEINFO_MIME_TYPE)) {
                return null;
            }
            return $finfo->file($file_path);
        }
        return null;
    }
}