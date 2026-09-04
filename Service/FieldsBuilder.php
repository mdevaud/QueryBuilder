<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use Propel\Runtime\Propel;
use QueryBuilder\Dictionary\FieldDefinition;
use QueryBuilder\Dictionary\Operators;
use QueryBuilder\Enum\Context;
use Thelia\Log\Tlog;

/**
 * Builds the "fields" configuration consumed by the react-querybuilder editor
 * in the back-office, for a given rule context.
 */
final readonly class FieldsBuilder
{
    //Beyond this size a select is unusable: fall back to the free input
    private const MAX_VALUES = 300;

    public function __construct(
        private DataDictionary $dataDictionary,
    ) {
    }

    public function buildForContext(Context $context, string $locale = 'fr_FR'): array
    {
        $fields = [];

        foreach ($this->dataDictionary->getFields($context) as $field) {
            $fields[] = $this->buildField($field, $locale);
        }

        usort($fields, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $fields;
    }

    private function buildField(FieldDefinition $field, string $locale): array
    {
        $built = [
            'name' => $field->code,
            'label' => $field->label,
            'operators' => array_map(
                static fn (string $operator): array => [
                    'name' => $operator,
                    'label' => Operators::LABELS[$operator] ?? $operator,
                ],
                Operators::forField($field)
            ),
        ];

        switch ($field->type) {
            case FieldDefinition::TYPE_NUMBER:
                $built['inputType'] = 'number';
                break;
            case FieldDefinition::TYPE_DATE:
                $built['inputType'] = 'date';
                break;
            case FieldDefinition::TYPE_DATETIME:
                $built['inputType'] = 'datetime-local';
                break;
            case FieldDefinition::TYPE_BOOLEAN:
                $built['valueEditorType'] = 'select';
                $built['values'] = [
                    ['name' => 'true', 'label' => 'Oui'],
                    ['name' => 'false', 'label' => 'Non'],
                ];
                $built['defaultValue'] = 'false';
                break;
        }

        if ($field->valuesQuery !== null && ($values = $this->fetchValues($field, $locale)) !== null) {
            //The editor JS switches the value input to a select/multiselect when values are present
            $built['values'] = $values;
        }

        return $built;
    }

    /**
     * Runs the values_query of the field ("value" + optional "label" and "group"
     * columns). A "group" column switches the editor select to <optgroup> sections,
     * in SQL row order. Returns null (free input fallback) on SQL error or
     * oversized result: the editor must stay usable even when a referential
     * misbehaves.
     *
     * @return array<int, array{name: string, label: string}>|array<int, array{label: string, options: array<int, array{name: string, label: string}>}>|null
     */
    private function fetchValues(FieldDefinition $field, string $locale): ?array
    {
        try {
            $statement = Propel::getConnection()->prepare((string) $field->valuesQuery);

            if (str_contains((string) $field->valuesQuery, ':locale')) {
                $statement->bindValue(':locale', $locale);
            }

            $statement->execute();
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('QueryBuilder: values_query failed, falling back to free input', [
                'field' => $field->code,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (\count($rows) > self::MAX_VALUES) {
            Tlog::getInstance()->addWarning(sprintf(
                'QueryBuilder: values_query of field "%s" returned %d rows (max %d), falling back to free input.',
                $field->code,
                \count($rows),
                self::MAX_VALUES
            ));

            return null;
        }

        $values = [];
        $groups = [];
        $grouped = $rows !== [] && \array_key_exists('group', $rows[0]);

        foreach ($rows as $row) {
            if (!\array_key_exists('value', $row)) {
                Tlog::getInstance()->error('QueryBuilder: values_query must expose a "value" column', [
                    'field' => $field->code,
                ]);

                return null;
            }

            $value = (string) $row['value'];
            $option = ['name' => $value, 'label' => (string) ($row['label'] ?? $value)];

            if ($grouped) {
                $groups[(string) ($row['group'] ?? '')][] = $option;
                continue;
            }

            $values[] = $option;
        }

        if ($grouped) {
            $sections = [];

            foreach ($groups as $groupLabel => $groupOptions) {
                $sections[] = ['label' => (string) $groupLabel, 'options' => $groupOptions];
            }

            return $sections;
        }

        return $values;
    }
}
