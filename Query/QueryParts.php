<?php

declare(strict_types=1);

namespace QueryBuilder\Query;

/**
 * Mutable collector used while compiling a condition tree: JOIN clauses,
 * WHERE fragments and bound parameters.
 */
final class QueryParts
{
    /** @var array<string, string> JOIN clauses keyed by joined table name */
    private array $joins = [];

    /** @var string[] */
    private array $where = [];

    /** @var array<string, mixed> parameter name (without colon) => value */
    private array $parameters = [];

    private int $parameterCounter = 0;

    public function addJoin(string $table, string $joinClause): void
    {
        $this->joins[$table] ??= $joinClause;
    }

    public function addWhere(string $clause): void
    {
        $this->where[] = $clause;
    }

    public function addParameter(string $name, mixed $value): void
    {
        $this->parameters[$name] = $value;
    }

    /** Registers a value under a generated unique name and returns its placeholder (with colon). */
    public function bindValue(mixed $value): string
    {
        $name = 'qb_' . $this->parameterCounter++;
        $this->parameters[$name] = $value;

        return ':' . $name;
    }

    /** @return string[] */
    public function getJoins(): array
    {
        return array_values($this->joins);
    }

    /** @return string[] */
    public function getWhere(): array
    {
        return $this->where;
    }

    /** @return array<string, mixed> */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
