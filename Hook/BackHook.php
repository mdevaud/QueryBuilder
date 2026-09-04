<?php

declare(strict_types=1);

namespace QueryBuilder\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class BackHook extends BaseHook
{
    public static function getSubscribedHooks(): array
    {
        return [
            'main.in-top-menu-items' => [
                'type' => 'back',
                'method' => 'onMainInTopMenuItems',
            ],
        ];
    }

    public function onMainInTopMenuItems(HookRenderEvent $event): void
    {
        $event->add(
            $this->render('query-builder/hook-in-top-menu-items.html', $event->getTemplateVars())
        );
    }
}
