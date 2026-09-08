<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Core\Template\TemplateHelperInterface;
use Twig\Environment;

/**
 * Renders the product list of a display action on a front theme hook.
 *
 * The module ships a default template on its Twig namespace; the active front
 * theme overrides it by shipping its own copy under modules/QueryBuilder/.
 */
final readonly class ProductListRenderer
{
    private const MODULE_TEMPLATE = '@QueryBuilderModule/frontOffice/default/QueryBuilder/product-list.html.twig';

    //Thelia convention for a theme override of a module template: templates/frontOffice/<theme>/modules/<Module>/...
    private const THEME_OVERRIDE_TEMPLATE = 'frontOffice/%s/modules/QueryBuilder/product-list.html.twig';

    public function __construct(
        private Environment $twig,
        //No interface alias in the core container: the helper is only known by its service id
        #[Autowire(service: 'thelia.template_helper')]
        private TemplateHelperInterface $templateHelper,
    ) {
    }

    /** @param int[] $productIds */
    public function render(string $hookCode, string $ruleName, string $actionName, array $productIds): string
    {
        return $this->twig->render($this->resolveTemplate(), [
            'hook' => $hookCode,
            'rule_name' => $ruleName,
            'action_name' => $actionName,
            'product_ids' => array_values(array_map('intval', $productIds)),
        ]);
    }

    private function resolveTemplate(): string
    {
        $themeOverride = sprintf(self::THEME_OVERRIDE_TEMPLATE, $this->templateHelper->getActiveFrontTemplate()->getName());

        return $this->twig->getLoader()->exists($themeOverride) ? $themeOverride : self::MODULE_TEMPLATE;
    }
}
