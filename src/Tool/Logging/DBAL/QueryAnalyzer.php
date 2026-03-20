<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tool\Logging\DBAL;

use Doctrine\DBAL\Logging\Sql_Logger;
use Doctrine\DBAL\Platforms\Abstract_Platform;
use Doctrine\DBAL\Types\Type;
/**
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @deprecated since gedmo/doctrine-extensions 3.5.
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Query_Analyzer implements Sql_Logger
{
    /**
     * Used database platform
     *
     * @var AbstractPlatform
     */
    protected $platform;
    /**
     * Start time of currently executed query
     */
    private ?float $query_start_time = null;
    /**
     * Total execution time of all queries
     */
    private int $total_execution_time = 0;
    /**
     * List of queries executed
     *
     * @var string[]
     */
    private array $queries = [];
    /**
     * Query execution times indexed
     * in same order as queries
     *
     * @var float[]
     */
    private array $query_execution_times = [];
    /**
     * Initialize log listener with database
     * platform, which is needed for parameter
     * conversion
     */
    public function __construct(Abstract_Platform $platform)
    {
        $this->platform = $platform;
    }
    public function start_query(string $sql, ?array $params = null, ?array $types = null): void
    {
        $this->query_start_time = microtime(true);
        $this->queries[] = $this->generate_sql($sql, $params, $types);
    }
    public function stop_query(): void
    {
        $ms = (int) (round(microtime(true) - $this->query_start_time, 4) * 1000);
        $this->query_execution_times[] = $ms;
        $this->total_execution_time += $ms;
    }
    /**
     * Clean all collected data
     */
    public function clean_up(): self
    {
        $this->queries = [];
        $this->query_execution_times = [];
        $this->total_execution_time = 0;
        return $this;
    }
    /**
     * Dump the statistics of executed queries
     *
     * @param bool $dumpOnlySql
     */
    public function get_output($dump_only_sql = false): string
    {
        $output = '';
        if (!$dump_only_sql) {
            $output .= 'Platform: ' . $this->platform->get_name() . PHP_EOL;
            $output .= 'Executed queries: ' . count($this->queries) . ', total time: ' . $this->total_execution_time . ' ms' . PHP_EOL;
        }
        foreach ($this->queries as $index => $sql) {
            if (!$dump_only_sql) {
                $output .= 'Query(' . ($index + 1) . ') - ' . $this->query_execution_times[$index] . ' ms' . PHP_EOL;
            }
            $output .= $sql . ';' . PHP_EOL;
        }
        return $output . PHP_EOL;
    }
    /**
     * Index of the slowest query executed
     *
     * @return int
     */
    public function get_slowest_query_index()
    {
        $index = 0;
        $slowest = 0;
        foreach ($this->query_execution_times as $i => $time) {
            if ($time > $slowest) {
                $slowest = $time;
                $index = $i;
            }
        }
        return $index;
    }
    /**
     * Get total execution time of queries
     */
    public function get_total_execution_time(): int
    {
        return $this->total_execution_time;
    }
    /**
     * Get all queries
     *
     * @return string[]
     */
    public function get_executed_queries(): array
    {
        return $this->queries;
    }
    /**
     * Get number of executed queries
     */
    public function get_num_executed_queries(): int
    {
        return count($this->queries);
    }
    /**
     * Get all query execution times
     *
     * @return float[]
     */
    public function get_execution_times(): array
    {
        return $this->query_execution_times;
    }
    /**
     * Create the SQL with mapped parameters
     *
     * @param array<int|string, mixed>|null       $params
     * @param array<int|string, string|Type>|null $types
     */
    private function generate_sql(string $sql, ?array $params, ?array $types): string
    {
        if (null === $params || [] === $params) {
            return $sql;
        }
        $converted = $this->get_converted_params($params, $types);
        if (is_int(key($params))) {
            $index = key($converted);
            $sql = preg_replace_callback('@\?@sm', static function ($match) use (&$index, $converted) {
                return $converted[$index++];
            }, $sql);
        } else {
            foreach ($converted as $key => $value) {
                $sql = str_replace(':' . $key, $value, $sql);
            }
        }
        return $sql;
    }
    /**
     * Get the converted parameter list
     *
     * @param array<int|string, mixed>       $params
     * @param array<int|string, string|Type> $types
     *
     * @return array<int|string, mixed>
     */
    private function get_converted_params(array $params, array $types): array
    {
        $result = [];
        foreach ($params as $position => $value) {
            if (isset($types[$position])) {
                $type = $types[$position];
                if (is_string($type)) {
                    $type = Type::get_type($type);
                }
                if ($type instanceof Type) {
                    $value = $type->convert_to_database_value($value, $this->platform);
                }
            } else if ($value instanceof \DateTimeInterface) {
                $value = $value->format($this->platform->get_date_time_format_string());
            } elseif (null !== $value) {
                $type = Type::get_type(gettype($value));
                $value = $type->convert_to_database_value($value, $this->platform);
            }
            if (is_string($value)) {
                $value = "'{$value}'";
            } elseif (null === $value) {
                $value = 'NULL';
            }
            $result[$position] = $value;
        }
        return $result;
    }
}