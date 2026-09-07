<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use QueryBuilder\QueryBuilder;
use Thelia\Core\Translation\Translator;

/**
 * Translated labels handed to the back-office editor script (condition counter,
 * readable summary of the tree, hook toolbar), so the JavaScript carries no wording.
 */
final readonly class EditorLabels
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        return [
            'counter' => [
                'none' => $this->trans('no condition'),
                'one' => $this->trans('1 condition'),
                'many' => $this->trans('%count% conditions'),
            ],
            'hooks' => [
                'count' => $this->trans('%checked% checked out of %total%'),
            ],
            'confirmContextChange' => $this->trans('Changing the context drops the conditions on fields the new context does not offer. Continue?'),
            'groups' => [
                'and' => $this->trans('All of the following conditions'),
                'or' => $this->trans('At least one of the following conditions'),
                'andNot' => $this->trans('None of the following conditions'),
                'orNot' => $this->trans('Not "at least one" of the following conditions'),
            ],
            'combinators' => [
                'and' => $this->trans('AND'),
                'or' => $this->trans('OR'),
            ],
            'join' => [
                'and' => $this->trans('and'),
                'or' => $this->trans('or'),
            ],
            'values' => [
                'true' => $this->trans('yes'),
                'false' => $this->trans('no'),
                'missing' => $this->trans('value to fill in'),
                'unknownField' => $this->trans('field unavailable in this context'),
            ],
            'operators' => [
                '=' => $this->trans('is equal to'),
                '!=' => $this->trans('is not equal to'),
                '<' => $this->trans('is less than'),
                '<=' => $this->trans('is less than or equal to'),
                '>' => $this->trans('is greater than'),
                '>=' => $this->trans('is greater than or equal to'),
                'contains' => $this->trans('contains'),
                'doesNotContain' => $this->trans('does not contain'),
                'beginsWith' => $this->trans('begins with'),
                'endsWith' => $this->trans('ends with'),
                'between' => $this->trans('is between'),
                'notBetween' => $this->trans('is not between'),
                'in' => $this->trans('is one of'),
                'notIn' => $this->trans('is none of'),
                'null' => $this->trans('is empty'),
                'notNull' => $this->trans('is not empty'),
            ],
        ];
    }

    private function trans(string $id): string
    {
        return Translator::getInstance()->trans($id, [], QueryBuilder::DOMAIN_NAME);
    }
}
