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
 * FileInfoArray
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class File_Info_Array implements File_Info_Interface
{
    /**
     * @var array<string, int|string>
     *
     * @phpstan-var array{error: int, size: int, type: string, tmp_name: string, name: string}
     */
    protected array $file_info;
    /**
     * @param array<string, int|string> $fileInfo
     */
    public function __construct(array $file_info)
    {
        $keys = ['error', 'size', 'type', 'tmp_name', 'name'];
        foreach ($keys as $k) {
            if (!isset($file_info[$k])) {
                $msg = 'There are missing keys in the fileInfo. ';
                $msg .= 'Keys needed: ' . implode(',', $keys);
                throw new \RuntimeException($msg);
            }
        }
        $this->file_info = $file_info;
    }
    public function get_tmp_name()
    {
        return $this->file_info['tmp_name'];
    }
    public function get_name()
    {
        return $this->file_info['name'];
    }
    public function get_size()
    {
        return $this->file_info['size'];
    }
    public function get_type()
    {
        return $this->file_info['type'];
    }
    public function get_error()
    {
        return $this->file_info['error'];
    }
    public function is_uploaded_file(): bool
    {
        return true;
    }
}