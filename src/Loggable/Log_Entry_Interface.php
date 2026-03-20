<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Loggable;

/**
 * Interface to be implemented by log entry models.
 *
 * @phpstan-template T of Loggable|object
 *
 * @author Javier Spagnoletti <phansys@gmail.com>
 */
interface Log_Entry_Interface
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_REMOVE = 'remove';
    /**
     * @phpstan-param self::ACTION_CREATE|self::ACTION_UPDATE|self::ACTION_REMOVE $action
     *
     * @return void
     */
    public function set_action(string $action);
    /**
     * @return string|null
     *
     * @phpstan-return self::ACTION_CREATE|self::ACTION_UPDATE|self::ACTION_REMOVE|null
     */
    public function get_action();
    /**
     * @return void
     */
    public function set_username(string $username);
    /**
     * @return string|null
     */
    public function get_username();
    /**
     * @phpstan-param class-string<T> $objectClass
     *
     * @return void
     */
    public function set_object_class(string $object_class);
    /**
     * @return string|null
     *
     * @phpstan-return class-string<T>|null
     */
    public function get_object_class();
    /**
     * @return void
     */
    public function set_logged_at();
    /**
     * @return \DateTimeInterface|null
     */
    public function get_logged_at();
    /**
     * @return void
     */
    public function set_object_id(string $object_id);
    /**
     * @return string|null
     */
    public function get_object_id();
    /**
     * @param array<string, mixed> $data
     *
     * @return void
     */
    public function set_data(array $data);
    /**
     * @return array<string, mixed>|null
     */
    public function get_data();
    /**
     * @return void
     */
    public function set_version(int $version);
    /**
     * @return int|null
     */
    public function get_version();
}