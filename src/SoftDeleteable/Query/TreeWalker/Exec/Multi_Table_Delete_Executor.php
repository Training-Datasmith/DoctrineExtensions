<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable\Query\Tree_Walker\Exec;

use Doctrine\DBAL\Platforms\Abstract_Platform;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Exec\Multi_Table_Delete_Executor as BaseMultiTableDeleteExecutor;
/**
 * This class is used when a DELETE DQL query is called for entities
 * that are part of an inheritance tree
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Multi_Table_Delete_Executor extends Base_Multi_Table_Delete_Executor
{
    /**
     * @param ClassMetadata<object> $meta
     * @param array<string, mixed>  $config
     */
    public function __construct(Node $AST, $sql_walker, Class_Metadata $meta, Abstract_Platform $platform, array $config)
    {
        parent::__construct($AST, $sql_walker);
        $sql_statements = $this->get_sql_statements();
        $quote_strategy = $sql_walker->get_entity_manager()->get_configuration()->get_quote_strategy();
        foreach ($sql_statements as $index => $stmt) {
            $matches = [];
            preg_match('/DELETE FROM (\w+) .+/', $stmt, $matches);
            if (isset($matches[1]) && $quote_strategy->get_table_name($meta, $platform) === $matches[1]) {
                $sql_statements[$index] = str_replace('DELETE FROM', 'UPDATE', $stmt);
                $sql_statements[$index] = str_replace('WHERE', 'SET ' . $config['fieldName'] . ' = ' . $platform->get_current_timestamp_sql() . ' WHERE', $sql_statements[$index]);
            } else {
                // We have to avoid the removal of registers of child entities of a SoftDeleteable entity
                unset($sql_statements[$index]);
            }
        }
        // @todo: Once the minimum supported ORM version is 2.17, this can always write to the `$this->sqlStatements` property
        if (property_exists($this, 'sqlStatements')) {
            $this->sql_statements = $sql_statements;
        } else {
            $this->_sql_statements = $sql_statements;
        }
    }
}