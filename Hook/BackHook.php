<?php

declare(strict_types=1);

namespace QueryBuilder\Hook;

use QueryBuilder\QueryBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\ParserResolver;
use Thelia\Tools\URL;

/**
 * Back-office integration: the entry in the tools menu, and the editor assets
 * (built in templates/backOffice/default-twig/assets/dist) on the module pages only.
 */
class BackHook extends BaseHook
{
    private const ADMIN_PATH_PREFIX = '/admin/query_builder';

    //Constructor injection: a #[Required] property would stay null on a module hook
    public function __construct(
        private readonly SecurityContext $securityContext,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'main.top-menu-tools' => [
                ['type' => 'back', 'method' => 'onMainTopMenuTools'],
            ],
            'main.head-css' => [
                ['type' => 'back', 'method' => 'onMainHeadCss'],
            ],
            'main.footer-js' => [
                ['type' => 'back', 'method' => 'onMainFooterJs'],
            ],
        ];
    }

    public function onMainTopMenuTools(HookRenderBlockEvent $event): void
    {
        //The controllers answer 403 to an administrator without the module right: no entry for them.
        //The right is the one granted on the module itself, not the "admin.module" resource, which
        //guards the module management screens and is not held by a profile limited to this module.
        if (!$this->securityContext->isGranted(['ADMIN'], [], [QueryBuilder::getModuleCode()], [AccessManager::VIEW])) {
            return;
        }

        $event->add([
            'id' => 'tools_menu_query_builder',
            'class' => '',
            'url' => URL::getInstance()->absoluteUrl(self::ADMIN_PATH_PREFIX),
            'title' => $this->trans('Query Builder', [], QueryBuilder::DOMAIN_NAME),
        ]);
    }

    public function onMainHeadCss(HookRenderEvent $event): void
    {
        if (!$this->isModulePage()) {
            return;
        }

        $event->add($this->addCSS('assets/dist/query-builder-admin.css'));
    }

    public function onMainFooterJs(HookRenderEvent $event): void
    {
        if (!$this->isModulePage()) {
            return;
        }

        $event->add($this->addJS('assets/dist/query-builder-admin.js', ['defer' => 'defer']));
    }

    private function isModulePage(): bool
    {
        return str_starts_with($this->getRequest()?->getPathInfo() ?? '', self::ADMIN_PATH_PREFIX);
    }
}
