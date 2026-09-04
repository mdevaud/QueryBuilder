<?php

declare(strict_types=1);

namespace QueryBuilder\Query;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Extension point: any module can expose extra runtime placeholders to the
 * dictionary expressions (ex: :customer_typology_id provided by the project).
 * Implementations are autoconfigured through this tag.
 */
#[AutoconfigureTag(RuntimeParameterProviderInterface::TAG)]
interface RuntimeParameterProviderInterface
{
    public const TAG = 'querybuilder.runtime_parameter_provider';

    /** @return array<string, mixed> placeholder name (without colon) => value */
    public function provide(RuntimeContext $baseContext): array;
}
