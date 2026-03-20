<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Soft_Deleteable\Query\Tree_Walker;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\Abstract_Platform;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Mapping\Quote_Strategy;
use Doctrine\ORM\Query\AST\Delete_Clause;
use Doctrine\ORM\Query\AST\Delete_Statement;
use Doctrine\ORM\Query\AST\Select_Statement;
use Doctrine\ORM\Query\AST\Update_Statement;
use Doctrine\ORM\Query\Exec\Abstract_Sql_Executor;
use Doctrine\ORM\Query\Exec\Prepared_Executor_Finalizer;
use Doctrine\ORM\Query\Exec\Single_Table_Delete_Update_Executor;
use Doctrine\ORM\Query\Exec\Sql_Finalizer;
use Doctrine\ORM\Query\Sql_Output_Walker;
use Gedmo\Exception\RuntimeException;
use Gedmo\Exception\UnexpectedValueException;
use Gedmo\Soft_Deleteable\Query\Tree_Walker\Exec\Multi_Table_Delete_Executor;
use Gedmo\Soft_Deleteable\Soft_Deleteable_Listener;
use Gedmo\Tool\ORM\Walker\Sql_Walker_Compat;
/**
 * This SqlWalker is needed when you need to use a DELETE DQL query.
 * It will update the "deletedAt" field with the actual date, instead
 * of actually deleting it.
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Soft_Deleteable_Walker extends Sql_Output_Walker
{
    use Sql_Walker_Compat;
    /**
     * @var Connection
     *
     * @deprecated to be removed in 4.0, use the `getConnection()` method instead.
     */
    protected $conn;
    /**
     * @var AbstractPlatform
     *
     * @deprecated to be removed in 4.0, fetch the platform from the connection instead
     */
    protected $platform;
    protected \Gedmo\Soft_Deleteable\Soft_Deleteable_Listener $listener;
    /**
     * @var array<string, mixed>
     */
    protected $configuration;
    /**
     * @var string|null
     *
     * @deprecated to be removed in 4.0, unused
     */
    protected $alias;
    /**
     * @var string
     */
    protected $deleted_at_field;
    /**
     * @var ClassMetadata<object>
     */
    protected $meta;
    private Quote_Strategy $quote_strategy;
    public function __construct($query, $parser_result, array $query_components)
    {
        parent::__construct($query, $parser_result, $query_components);
        $this->conn = $this->get_connection();
        $this->platform = $this->get_connection()->get_database_platform();
        $this->listener = $this->get_soft_deleteable_listener();
        $this->quote_strategy = $this->get_entity_manager()->get_configuration()->get_quote_strategy();
        $this->extract_components($this->get_query_components());
    }
    /**
     * @param SelectStatement|UpdateStatement|DeleteStatement $statement
     *
     * @throws UnexpectedValueException when an unsupported AST statement is given
     *
     * @phpstan-assert DeleteStatement $statement
     */
    protected function do_get_executor_with_compat($statement): Abstract_Sql_Executor
    {
        if (!$statement instanceof Delete_Statement) {
            throw new UnexpectedValueException('SoftDeleteable walker should be used only on delete statement');
        }
        return $this->create_delete_statement_executor($statement);
    }
    /**
     * @param DeleteStatement|UpdateStatement|SelectStatement $AST
     *
     * @throws UnexpectedValueException when an unsupported AST statement is given
     *
     * @phpstan-assert DeleteStatement $AST
     */
    protected function do_get_finalizer_with_compat($AST): Sql_Finalizer
    {
        if (!$AST instanceof Delete_Statement) {
            throw new UnexpectedValueException('SoftDeleteable walker should be used only on delete statement');
        }
        return new Prepared_Executor_Finalizer($this->create_delete_statement_executor($AST));
    }
    protected function create_delete_statement_executor(Delete_Statement $AST): Abstract_Sql_Executor
    {
        assert(class_exists($AST->delete_clause->abstract_schema_name));
        $primary_class = $this->get_entity_manager()->get_class_metadata($AST->delete_clause->abstract_schema_name);
        return $primary_class->is_inheritance_type_joined() ? new Multi_Table_Delete_Executor($AST, $this, $this->meta, $this->get_connection()->get_database_platform(), $this->configuration) : new Single_Table_Delete_Update_Executor($AST, $this);
    }
    /**
     * Changes a DELETE clause into an UPDATE clause for a soft-deleteable entity.
     */
    protected function do_walk_delete_clause_with_compat(Delete_Clause $delete_clause): string
    {
        $em = $this->get_entity_manager();
        assert(class_exists($delete_clause->abstract_schema_name));
        $class = $em->get_class_metadata($delete_clause->abstract_schema_name);
        $table_name = $class->get_table_name();
        $this->set_sql_table_alias($table_name, $table_name, $delete_clause->alias_identification_variable);
        $platform = $this->get_connection()->get_database_platform();
        $quoted_table_name = $this->quote_strategy->get_table_name($class, $platform);
        $quoted_column_name = $this->quote_strategy->get_column_name($this->deleted_at_field, $class, $platform);
        return 'UPDATE ' . $quoted_table_name . ' SET ' . $quoted_column_name . ' = ' . $platform->get_current_timestamp_sql();
    }
    /**
     * Get the currently used SoftDeleteableListener
     *
     * @throws RuntimeException if listener is not found
     */
    private function get_soft_deleteable_listener(): Soft_Deleteable_Listener
    {
        if (null === $this->listener) {
            $em = $this->get_entity_manager();
            foreach ($em->get_event_manager()->get_all_listeners() as $listeners) {
                foreach ($listeners as $listener) {
                    if ($listener instanceof Soft_Deleteable_Listener) {
                        $this->listener = $listener;
                        break 2;
                    }
                }
            }
            if (null === $this->listener) {
                throw new RuntimeException('The SoftDeleteable listener could not be found.');
            }
        }
        return $this->listener;
    }
    /**
     * Search for components in the delete clause
     *
     * @param array<string, array<string, mixed>> $queryComponents
     */
    private function extract_components(array $query_components): void
    {
        $em = $this->get_entity_manager();
        foreach ($query_components as $comp) {
            $meta = $comp['metadata'];
            $config = $this->listener->get_configuration($em, $meta->get_name());
            if ($config && isset($config['softDeleteable']) && $config['softDeleteable']) {
                $this->configuration = $config;
                $this->deleted_at_field = $config['fieldName'];
                $this->meta = $meta;
            }
        }
    }
}