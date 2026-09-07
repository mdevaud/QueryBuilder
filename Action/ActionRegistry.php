<?php

declare(strict_types=1);

namespace QueryBuilder\Action;

use QueryBuilder\Enum\Context;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ActionRegistry
{
    /** @param iterable<ActionInterface> $actions */
    public function __construct(
        #[AutowireIterator(ActionInterface::TAG)]
        private iterable $actions,
    ) {
    }

    public function get(string $code): ?ActionInterface
    {
        foreach ($this->actions as $action) {
            if ($action::getCode() === $code) {
                return $action;
            }
        }

        return null;
    }

    /** @return array<string, ActionInterface> keyed by action code */
    public function forContext(Context $context): array
    {
        $actions = [];

        foreach ($this->actions as $action) {
            $supportedContexts = $action::getSupportedContexts();

            if ($supportedContexts === [] || \in_array($context, $supportedContexts, true)) {
                $actions[$action::getCode()] = $action;
            }
        }

        return $actions;
    }
}
