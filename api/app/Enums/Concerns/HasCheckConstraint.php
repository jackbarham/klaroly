<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for the string-backed enums that back varchar columns.
 *
 * The check constraint on each column is generated from the enum, so the
 * database and the PHP code cannot drift apart.
 */
trait HasCheckConstraint
{
    /**
     * Every backing value, in declaration order.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * The SQL that adds a check constraint limiting a column to these values.
     *
     * The constraint is named <table>_<column>_check so it can be dropped by
     * name when the enum is widened later.
     */
    public static function checkConstraintSql(string $table, string $column): string
    {
        $quoted = array_map(fn (string $value) => "'".$value."'", self::values());

        return sprintf(
            'alter table %s add constraint %s_%s_check check (%s in (%s))',
            $table,
            $table,
            $column,
            $column,
            implode(', ', $quoted),
        );
    }
}
