<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Translatable\Query\Tree_Walker;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\Abstract_My_Sql_Platform;
use Doctrine\DBAL\Platforms\Abstract_Platform;
use Doctrine\DBAL\Platforms\Postgre_Sql_Platform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST\Delete_Statement;
use Doctrine\ORM\Query\AST\From_Clause;
use Doctrine\ORM\Query\AST\Group_By_Clause;
use Doctrine\ORM\Query\AST\Having_Clause;
use Doctrine\ORM\Query\AST\Join;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\AST\Order_By_Clause;
use Doctrine\ORM\Query\AST\Range_Variable_Declaration;
use Doctrine\ORM\Query\AST\Select_Clause;
use Doctrine\ORM\Query\AST\Select_Statement;
use Doctrine\ORM\Query\AST\Simple_Select_Clause;
use Doctrine\ORM\Query\AST\Subselect_From_Clause;
use Doctrine\ORM\Query\AST\Update_Statement;
use Doctrine\ORM\Query\AST\Where_Clause;
use Doctrine\ORM\Query\Exec\Abstract_Sql_Executor;
use Doctrine\ORM\Query\Exec\Single_Select_Executor;
use Doctrine\ORM\Query\Exec\Single_Select_Sql_Finalizer;
use Doctrine\ORM\Query\Exec\Sql_Finalizer;
use Doctrine\ORM\Query\Sql_Output_Walker;
use Gedmo\Exception\RuntimeException;
use Gedmo\Tool\ORM\Walker\Sql_Walker_Compat;
use Gedmo\Translatable\Hydrator\ORM\Object_Hydrator;
use Gedmo\Translatable\Hydrator\ORM\Simple_Object_Hydrator;
use Gedmo\Translatable\Mapping\Event\Adapter\ORM as TranslatableEventAdapter;
use Gedmo\Translatable\Translatable_Listener;
/**
 * The translation sql output walker makes it possible
 * to translate all query components during single query.
 * It works with any select query, any hydration method.
 *
 * Behind the scenes, during the object hydration it forces
 * custom hydrator in order to interact with TranslatableListener
 * and skip postLoad event which would cause automatic retranslation
 * of the fields.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @final since gedmo/doctrine-extensions 3.11
 */
class Translation_Walker extends Sql_Output_Walker
{
    use Sql_Walker_Compat;
    /**
     * Name for translation fallback hint
     *
     * @internal
     */
    public const HINT_TRANSLATION_FALLBACKS = '__gedmo.translatable.stored.fallbacks';
    /**
     * Customized object hydrator name
     *
     * @internal
     */
    public const HYDRATE_OBJECT_TRANSLATION = '__gedmo.translatable.object.hydrator';
    /**
     * Customized object hydrator name
     *
     * @internal
     */
    public const HYDRATE_SIMPLE_OBJECT_TRANSLATION = '__gedmo.translatable.simple_object.hydrator';
    /**
     * Stores all component references from select clause
     *
     * @var array<string, array<string, mixed>>
     *
     * @phpstan-var array<string, array{metadata: ClassMetadata<object>}>
     */
    private array $translated_components = [];
    /**
     * DBAL database platform
     */
    private Abstract_Platform $platform;
    /**
     * DBAL database connection
     */
    private Connection $conn;
    /**
     * List of aliases to replace with translation
     * content reference
     *
     * @var array<string, string>
     */
    private array $replacements = [];
    /**
     * List of joins for translated components in query
     *
     * @var array<string, string>
     */
    private array $components = [];
    private Translatable_Listener $listener;
    public function __construct($query, $parser_result, array $query_components)
    {
        parent::__construct($query, $parser_result, $query_components);
        $this->conn = $this->get_connection();
        $this->platform = $this->get_connection()->get_database_platform();
        $this->listener = $this->get_translatable_listener();
        $this->extract_translated_components($query_components);
    }
    /**
     * Gets an executor that can be used to execute the result of this walker.
     *
     * @param SelectStatement|UpdateStatement|DeleteStatement $statement
     */
    protected function do_get_executor_with_compat($statement): Abstract_Sql_Executor
    {
        // If it's not a Select, the TreeWalker ought to skip it, and just return the parent.
        // @see https://github.com/doctrine-extensions/DoctrineExtensions/issues/2013
        if (!$statement instanceof Select_Statement) {
            return parent::get_executor($statement);
        }
        $this->prepare_translated_components();
        return new Single_Select_Executor($statement, $this);
    }
    /**
     * @param DeleteStatement|UpdateStatement|SelectStatement $AST
     */
    protected function do_get_finalizer_with_compat($AST): Sql_Finalizer
    {
        // If it's not a Select, the TreeWalker ought to skip it, and just return the parent.
        // @see https://github.com/doctrine-extensions/DoctrineExtensions/issues/2013
        if (!$AST instanceof Select_Statement) {
            return parent::get_finalizer($AST);
        }
        $this->prepare_translated_components();
        return new Single_Select_Sql_Finalizer($this->create_sql_for_finalizer($AST));
    }
    protected function create_sql_for_finalizer(Select_Statement $select_statement): string
    {
        $result = parent::create_sql_for_finalizer($select_statement);
        if ([] === $this->translated_components) {
            return $result;
        }
        $hydration_mode = $this->get_query()->get_hydration_mode();
        if (Query::HYDRATE_OBJECT === $hydration_mode) {
            $this->get_query()->set_hydration_mode(self::HYDRATE_OBJECT_TRANSLATION);
            $this->get_entity_manager()->get_configuration()->add_custom_hydration_mode(self::HYDRATE_OBJECT_TRANSLATION, Object_Hydrator::class);
            $this->get_query()->set_hint(Query::HINT_REFRESH, true);
        } elseif (Query::HYDRATE_SIMPLEOBJECT === $hydration_mode) {
            $this->get_query()->set_hydration_mode(self::HYDRATE_SIMPLE_OBJECT_TRANSLATION);
            $this->get_entity_manager()->get_configuration()->add_custom_hydration_mode(self::HYDRATE_SIMPLE_OBJECT_TRANSLATION, Simple_Object_Hydrator::class);
            $this->get_query()->set_hint(Query::HINT_REFRESH, true);
        }
        return $result;
    }
    protected function do_walk_select_clause_with_compat(Select_Clause $select_clause): string
    {
        return $this->replace($this->replacements, parent::walk_select_clause($select_clause));
    }
    protected function do_walk_from_clause_with_compat(From_Clause $from_clause): string
    {
        return parent::walk_from_clause($from_clause) . $this->join_translations($from_clause);
    }
    protected function do_walk_where_clause_with_compat(?Where_Clause $where_clause): string
    {
        return $this->replace($this->replacements, parent::walk_where_clause($where_clause));
    }
    protected function do_walk_having_clause_with_compat(Having_Clause $having_clause): string
    {
        return $this->replace($this->replacements, parent::walk_having_clause($having_clause));
    }
    protected function do_walk_order_by_clause_with_compat(Order_By_Clause $order_by_clause): string
    {
        return $this->replace($this->replacements, parent::walk_order_by_clause($order_by_clause));
    }
    protected function do_walk_subselect_from_clause_with_compat(Subselect_From_Clause $subselect_from_clause): string
    {
        return parent::walk_subselect_from_clause($subselect_from_clause) . $this->join_translations($subselect_from_clause);
    }
    protected function do_walk_simple_select_clause_with_compat(Simple_Select_Clause $simple_select_clause): string
    {
        return $this->replace($this->replacements, parent::walk_simple_select_clause($simple_select_clause));
    }
    protected function do_walk_group_by_clause_with_compat(Group_By_Clause $group_by_clause): string
    {
        return $this->replace($this->replacements, parent::walk_group_by_clause($group_by_clause));
    }
    /**
     * Walks from clause, and creates translation joins
     * for the translated components
     *
     * @param FromClause|SubselectFromClause $from
     */
    private function join_translations(Node $from): string
    {
        $result = '';
        foreach ($from->identification_variable_declarations as $decl) {
            if ($decl->range_variable_declaration instanceof Range_Variable_Declaration) {
                if (isset($this->components[$decl->range_variable_declaration->alias_identification_variable])) {
                    $result .= $this->components[$decl->range_variable_declaration->alias_identification_variable];
                }
            }
            if (isset($decl->join_variable_declarations)) {
                foreach ($decl->join_variable_declarations as $join_decl) {
                    if (!$join_decl->join instanceof Join) {
                        continue;
                    }
                    if (!isset($this->components[$join_decl->join->alias_identification_variable])) {
                        continue;
                    }
                    $result .= $this->components[$join_decl->join->alias_identification_variable];
                }
            } else {
                // based on new changes
                foreach ($decl->joins as $join) {
                    if (!$join instanceof Join) {
                        continue;
                    }
                    if (!isset($this->components[$join->join_association_declaration->alias_identification_variable])) {
                        continue;
                    }
                    $result .= $this->components[$join->join_association_declaration->alias_identification_variable];
                }
            }
        }
        return $result;
    }
    /**
     * Creates a left join list for translations
     * on used query components
     *
     * @todo: make it cleaner
     */
    private function prepare_translated_components(): void
    {
        $q = $this->get_query();
        $locale = $q->get_hint(Translatable_Listener::HINT_TRANSLATABLE_LOCALE);
        if (!$locale) {
            // use from listener
            $locale = $this->listener->get_listener_locale();
        }
        $default_locale = $this->listener->get_default_locale();
        if ($locale === $default_locale && !$this->listener->get_persist_default_locale_translation()) {
            // Skip preparation as there's no need to translate anything
            return;
        }
        $em = $this->get_entity_manager();
        $ea = new Translatable_Event_Adapter();
        $ea->set_entity_manager($em);
        $quote_strategy = $em->get_configuration()->get_quote_strategy();
        $join_strategy = $q->get_hint(Translatable_Listener::HINT_INNER_JOIN) ? 'INNER' : 'LEFT';
        foreach ($this->translated_components as $dql_alias => $comp) {
            /** @var ClassMetadata<object> $meta */
            $meta = $comp['metadata'];
            $config = $this->listener->get_configuration($em, $meta->get_name());
            $trans_class = $this->listener->get_translation_class($ea, $meta->get_name());
            $trans_meta = $em->get_class_metadata($trans_class);
            $trans_table = $quote_strategy->get_table_name($trans_meta, $this->platform);
            foreach ($config['fields'] as $field) {
                $comp_tbl_alias = $this->walk_identification_variable($dql_alias, $field);
                $tbl_alias = $this->get_sql_table_alias('trans' . $comp_tbl_alias . $field);
                $sql = " {$join_strategy} JOIN " . $trans_table . ' ' . $tbl_alias;
                $sql .= ' ON ' . $tbl_alias . '.' . $quote_strategy->get_column_name('locale', $trans_meta, $this->platform) . ' = ' . $this->conn->quote($locale);
                $sql .= ' AND ' . $tbl_alias . '.' . $quote_strategy->get_column_name('field', $trans_meta, $this->platform) . ' = ' . $this->conn->quote($field);
                $identifier = $meta->get_single_identifier_field_name();
                $id_col_name = $quote_strategy->get_column_name($identifier, $meta, $this->platform);
                if ($ea->uses_personal_translation($trans_class)) {
                    $sql .= ' AND ' . $tbl_alias . '.' . $trans_meta->get_single_association_join_column_name('object') . ' = ' . $comp_tbl_alias . '.' . $id_col_name;
                } else {
                    $sql .= ' AND ' . $tbl_alias . '.' . $quote_strategy->get_column_name('objectClass', $trans_meta, $this->platform) . ' = ' . $this->conn->quote($config['useObjectClass']);
                    $mapping_fk = $trans_meta->get_field_mapping('foreignKey');
                    $mapping_pk = $meta->get_field_mapping($identifier);
                    $fk_col_name = $this->get_casted_foreign_key($comp_tbl_alias . '.' . $id_col_name, $mapping_fk->type ?? $mapping_fk['type'], $mapping_pk->type ?? $mapping_pk['type']);
                    $sql .= ' AND ' . $tbl_alias . '.' . $quote_strategy->get_column_name('foreignKey', $trans_meta, $this->platform) . ' = ' . $fk_col_name;
                }
                isset($this->components[$dql_alias]) ? $this->components[$dql_alias] .= $sql : $this->components[$dql_alias] = $sql;
                $original_field = $comp_tbl_alias . '.' . $quote_strategy->get_column_name($field, $meta, $this->platform);
                $substitute_field = $tbl_alias . '.' . $quote_strategy->get_column_name('content', $trans_meta, $this->platform);
                // Treat translation as original field type
                $field_mapping = $meta->get_field_mapping($field);
                if ($this->platform instanceof Abstract_My_Sql_Platform && in_array($field_mapping->type ?? $field_mapping['type'], ['decimal'], true) || !$this->platform instanceof Abstract_My_Sql_Platform && !in_array($field_mapping->type ?? $field_mapping['type'], ['datetime', 'datetimetz', 'date', 'time'], true)) {
                    $type = Type::get_type($field_mapping->type ?? $field_mapping['type']);
                    // In ORM 2.x, $fieldMapping is an array. In ORM 3.x, it's a data object. Always cast to an array for compatibility across versions.
                    $substitute_field = 'CAST(' . $substitute_field . ' AS ' . $type->get_sql_declaration((array) $field_mapping, $this->platform) . ')';
                }
                // Fallback to original if was asked for
                if ($this->needs_fallback() && (!isset($config['fallback'][$field]) || $config['fallback'][$field]) || !$this->needs_fallback() && isset($config['fallback'][$field]) && $config['fallback'][$field]) {
                    $substitute_field = 'COALESCE(' . $substitute_field . ', ' . $original_field . ')';
                }
                $this->replacements[$original_field] = $substitute_field;
            }
        }
    }
    /**
     * Checks if translation fallbacks are needed
     */
    private function needs_fallback(): bool
    {
        $q = $this->get_query();
        $fallback = $q->get_hint(Translatable_Listener::HINT_FALLBACK);
        if (false === $fallback) {
            // non overrided
            $fallback = $this->listener->get_translation_fallback();
        }
        // applies fallbacks to scalar hydration as well
        return (bool) $fallback;
    }
    /**
     * Search for translated components in the select clause
     *
     * @param array<string, array<string, ClassMetadata<object>>> $queryComponents
     *
     * @phpstan-param array<string, array{metadata: ClassMetadata<object>}> $queryComponents
     */
    private function extract_translated_components(array $query_components): void
    {
        $em = $this->get_entity_manager();
        foreach ($query_components as $alias => $comp) {
            if (!isset($comp['metadata'])) {
                continue;
            }
            $meta = $comp['metadata'];
            $config = $this->listener->get_configuration($em, $meta->get_name());
            if ($config && isset($config['fields'])) {
                $this->translated_components[$alias] = $comp;
            }
        }
    }
    /**
     * Get the currently used TranslatableListener
     *
     * @throws RuntimeException if listener is not found
     */
    private function get_translatable_listener(): Translatable_Listener
    {
        $em = $this->get_entity_manager();
        foreach ($em->get_event_manager()->get_all_listeners() as $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof Translatable_Listener) {
                    return $listener;
                }
            }
        }
        throw new RuntimeException('The translation listener could not be found');
    }
    /**
     * Replaces given sql $str with required
     * results
     *
     * @param array<string, string> $repl
     */
    private function replace(array $repl, string $str): string
    {
        foreach ($repl as $target => $result) {
            $str = preg_replace_callback('/(\s|\()(' . $target . ')(,?)(\s|\)|$)/smi', static fn(array $m): string => $m[1] . $result . $m[3] . $m[4], $str);
        }
        return $str;
    }
    /**
     * Casts a foreign key if needed
     *
     * @NOTE: personal translations manages that for themselves.
     *
     * @param string $component a column with an alias to cast
     * @param string $typeFK    translation table foreign key type
     * @param string $typePK    primary key type which references translation table
     *
     * @return string modified $component if needed
     */
    private function get_casted_foreign_key(string $component, string $type_fk, string $type_pk): string
    {
        // the keys are of same type
        if ($type_fk === $type_pk) {
            return $component;
        }
        // try to look at postgres casting
        if ($this->platform instanceof Postgre_Sql_Platform) {
            switch ($type_fk) {
                case 'string':
                case 'guid':
                    // need to cast to VARCHAR
                    $component .= '::VARCHAR';
                    break;
            }
        }
        // @TODO may add the same thing for MySQL for performance to match index
        return $component;
    }
}