<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Uploadable\File_Info;

/**
 * FileInfoInterface
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 */
interface File_Info_Interface
{
    /**
     * @return string|null
     */
    public function get_tmp_name();
    /**
     * @return string|null
     */
    public function get_name();
    /**
     * @return int|null
     */
    public function get_size();
    /**
     * @return string|null
     */
    public function get_type();
    /**
     * @return int
     */
    public function get_error();
    /**
     * This method must return true if the file is coming from $_FILES, or false instead.
     *
     * @return bool
     */
    public function is_uploaded_file();
}