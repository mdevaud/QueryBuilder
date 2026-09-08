<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Core\Template\TemplateHelperInterface;
use Twig\Environment;

/**
 * Renders a front template of the module at a theme hook point.
 *
 * The module ships its defaults on its Twig namespace; the active front theme
 * overrides one by shipping its own copy under modules/QueryBuilder/.
 */
final readonly class FrontTemplateRenderer
{
    private const MODULE_TEMPLATE_DIRECTORY = '@QueryBuilderModule/frontOffice/default/QueryBuilder/';

    //Thelia convention for a theme override of a module template: templates/frontOffice/<theme>/modules/<Module>/...
    private const THEME_OVERRIDE_DIRECTORY = 'frontOffice/%s/modules/QueryBuilder/';

    public function __construct(
        private Environment $twig,
        //No interface alias in the core container: the helper is only known by its service id
        #[Autowire(service: 'thelia.template_helper')]
        private TemplateHelperInterface $templateHelper,
    ) {
    }

    /** @param array<string, mixed> $variables */
    public function render(string $templateName, array $variables): string
    {
        return $this->twig->render($this->resolveTemplate($templateName), $variables);
    }

    private function resolveTemplate(string $templateName): string
    {
        $themeOverride = sprintf(self::THEME_OVERRIDE_DIRECTORY, $this->templateHelper->getActiveFrontTemplate()->getName()) . $templateName;

        return $this->twig->getLoader()->exists($themeOverride)
            ? $themeOverride
            : self::MODULE_TEMPLATE_DIRECTORY . $templateName;
    }
}
