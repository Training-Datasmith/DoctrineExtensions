<?php

declare (strict_types=1);
/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Gedmo\Tool\ORM\Walker;

use Doctrine\ORM\Query\AST\Delete_Clause;
use Doctrine\ORM\Query\AST\Delete_Statement;
use Doctrine\ORM\Query\AST\From_Clause;
use Doctrine\ORM\Query\AST\Group_By_Clause;
use Doctrine\ORM\Query\AST\Having_Clause;
use Doctrine\ORM\Query\AST\Order_By_Clause;
use Doctrine\ORM\Query\AST\Select_Clause;
use Doctrine\ORM\Query\AST\Select_Statement;
use Doctrine\ORM\Query\AST\Simple_Select_Clause;
use Doctrine\ORM\Query\AST\Subselect_From_Clause;
use Doctrine\ORM\Query\AST\Update_Statement;
use Doctrine\ORM\Query\AST\Where_Clause;
use Doctrine\ORM\Query\Exec\Abstract_Sql_Executor;
use Doctrine\ORM\Query\Exec\Sql_Finalizer;
use Doctrine\ORM\Query\Sql_Walker;
/**
 * Helper trait to address compatibility issues between ORM 2.x and 3.x.
 *
 * @mixin SqlWalker
 *
 * @internal
 */
trait Sql_Walker_Compat_For_Orm3
{
    /**
     * Gets an executor that can be used to execute the result of this walker.
     */
    public function get_executor(Select_Statement|Update_Statement|Delete_Statement $statement): Abstract_Sql_Executor
    {
        return $this->do_get_executor_with_compat($statement);
    }
    public function get_finalizer(Delete_Statement|Update_Statement|Select_Statement $AST): Sql_Finalizer
    {
        return $this->do_get_finalizer_with_compat($AST);
    }
    /**
     * Walks down a SelectClause AST node, thereby generating the appropriate SQL.
     */
    public function walk_select_clause(Select_Clause $select_clause): string
    {
        return $this->do_walk_select_clause_with_compat($select_clause);
    }
    /**
     * Walks down a FromClause AST node, thereby generating the appropriate SQL.
     */
    public function walk_from_clause(From_Clause $from_clause): string
    {
        return $this->do_walk_from_clause_with_compat($from_clause);
    }
    /**
     * Walks down a OrderByClause AST node, thereby generating the appropriate SQL.
     */
    public function walk_order_by_clause(Order_By_Clause $order_by_clause): string
    {
        return $this->do_walk_order_by_clause_with_compat($order_by_clause);
    }
    /**
     * Walks down a HavingClause AST node, thereby generating the appropriate SQL.
     */
    public function walk_having_clause(Having_Clause $having_clause): string
    {
        return $this->do_walk_having_clause_with_compat($having_clause);
    }
    /**
     * Walks down a SubselectFromClause AST node, thereby generating the appropriate SQL.
     */
    public function walk_subselect_from_clause(Subselect_From_Clause $subselect_from_clause): string
    {
        return $this->do_walk_subselect_from_clause_with_compat($subselect_from_clause);
    }
    /**
     * Walks down a SimpleSelectClause AST node, thereby generating the appropriate SQL.
     */
    public function walk_simple_select_clause(Simple_Select_Clause $simple_select_clause): string
    {
        return $this->do_walk_simple_select_clause_with_compat($simple_select_clause);
    }
    /**
     * Walks down a GroupByClause AST node, thereby generating the appropriate SQL.
     */
    public function walk_group_by_clause(Group_By_Clause $group_by_clause): string
    {
        return $this->do_walk_group_by_clause_with_compat($group_by_clause);
    }
    /**
     * Walks down a DeleteClause AST node, thereby generating the appropriate SQL.
     */
    public function walk_delete_clause(Delete_Clause $delete_clause): string
    {
        return $this->do_walk_delete_clause_with_compat($delete_clause);
    }
    /**
     * Walks down a WhereClause AST node, thereby generating the appropriate SQL.
     *
     * WhereClause or not, the appropriate discriminator sql is added.
     */
    public function walk_where_clause(?Where_Clause $where_clause): string
    {
        return $this->do_walk_where_clause_with_compat($where_clause);
    }
    /**
     * Gets an executor that can be used to execute the result of this walker.
     *
     * @param SelectStatement|UpdateStatement|DeleteStatement $statement
     */
    protected function do_get_executor_with_compat($statement): Abstract_Sql_Executor
    {
        return parent::get_executor($statement);
    }
    /**
     * @param DeleteStatement|UpdateStatement|SelectStatement $AST
     */
    protected function do_get_finalizer_with_compat($AST): Sql_Finalizer
    {
        return parent::get_finalizer($AST);
    }
    protected function do_walk_select_clause_with_compat(Select_Clause $select_clause): string
    {
        return parent::walk_select_clause($select_clause);
    }
    protected function do_walk_from_clause_with_compat(From_Clause $from_clause): string
    {
        return parent::walk_from_clause($from_clause);
    }
    protected function do_walk_order_by_clause_with_compat(Order_By_Clause $order_by_clause): string
    {
        return parent::walk_order_by_clause($order_by_clause);
    }
    protected function do_walk_having_clause_with_compat(Having_Clause $having_clause): string
    {
        return parent::walk_having_clause($having_clause);
    }
    protected function do_walk_subselect_from_clause_with_compat(Subselect_From_Clause $subselect_from_clause): string
    {
        return parent::walk_subselect_from_clause($subselect_from_clause);
    }
    protected function do_walk_simple_select_clause_with_compat(Simple_Select_Clause $simple_select_clause): string
    {
        return parent::walk_simple_select_clause($simple_select_clause);
    }
    protected function do_walk_group_by_clause_with_compat(Group_By_Clause $group_by_clause): string
    {
        return parent::walk_group_by_clause($group_by_clause);
    }
    protected function do_walk_delete_clause_with_compat(Delete_Clause $delete_clause): string
    {
        return parent::walk_delete_clause($delete_clause);
    }
    protected function do_walk_where_clause_with_compat(?Where_Clause $where_clause): string
    {
        return parent::walk_where_clause($where_clause);
    }
}