<?php

declare(strict_types=1);

namespace QueryBuilder\Dictionary;

/**
 * Single source of truth for the operators allowed per field type, shared by
 * the back-office editor (FieldsBuilder) and the SQL generation (SqlBuilder whitelist).
 * Operator names follow the react-querybuilder convention; their labels come from
 * the query-builder-bundle editor.
 */
final class Operators
{
    public const BY_TYPE = [
        FieldDefinition::TYPE_TEXT => [
            '=', '!=', 'contains', 'doesNotContain', 'beginsWith', 'endsWith',
            'in', 'notIn', 'null', 'notNull',
        ],
        FieldDefinition::TYPE_NUMBER => [
            '=', '!=', '<', '<=', '>', '>=', 'between', 'notBetween', 'in', 'notIn', 'null', 'notNull',
        ],
        FieldDefinition::TYPE_DATE => [
            '=', '!=', '<', '<=', '>', '>=', 'between', 'notBetween', 'null', 'notNull',
        ],
        FieldDefinition::TYPE_DATETIME => [
            '=', '!=', '<', '<=', '>', '>=', 'between', 'notBetween', 'null', 'notNull',
        ],
        FieldDefinition::TYPE_BOOLEAN => [
            '=',
        ],
    ];


    private function __construct()
    {
    }

    /** @return string[] */
    public static function forField(FieldDefinition $field): array
    {
        $allowed = self::BY_TYPE[$field->type] ?? self::BY_TYPE[FieldDefinition::TYPE_TEXT];

        if ($field->operators === null) {
            return $allowed;
        }

        return array_values(array_intersect($field->operators, $allowed));
    }
}
