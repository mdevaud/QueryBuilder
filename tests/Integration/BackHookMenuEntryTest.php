<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use QueryBuilder\Hook\BackHook;
use QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Hook\Fragment;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Admin;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProfileModule;
use Thelia\Test\IntegrationTestCase;

/**
 * The Tools menu entry follows the right granted on the module itself: an
 * administrator whose profile is granted on the module sees it, whatever the
 * rest of the profile (in particular the "admin.module" resource, which guards
 * the module management screens and not the screens of a given module).
 *
 * Prerequisites: a shop test database where the module is registered.
 */
final class BackHookMenuEntryTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        $this->session()->clearAdminUser();

        parent::tearDown();
    }

    #[Test]
    public function theEntryShowsForAnAdministratorGrantedOnTheModuleOnly(): void
    {
        $admin = $this->createFixtureFactory()->restrictedAdmin([AdminResources::TOOLS => [AccessManager::VIEW]]);
        $this->grantModule($admin, [AccessManager::VIEW]);

        self::assertSame(['tools_menu_query_builder'], $this->renderedEntryIds($admin));
    }

    #[Test]
    public function theEntryStaysHiddenFromAnAdministratorWithoutTheModuleRight(): void
    {
        $admin = $this->createFixtureFactory()->restrictedAdmin([
            AdminResources::TOOLS => [AccessManager::VIEW],
            AdminResources::MODULE => [AccessManager::VIEW],
        ]);

        self::assertSame([], $this->renderedEntryIds($admin));
    }

    #[Test]
    public function theEntryStaysHiddenFromAnAdministratorGrantedOnTheModuleWithoutView(): void
    {
        $admin = $this->createFixtureFactory()->restrictedAdmin([AdminResources::TOOLS => [AccessManager::VIEW]]);
        $this->grantModule($admin, [AccessManager::UPDATE]);

        self::assertSame([], $this->renderedEntryIds($admin));
    }

    /**
     * @param list<string> $accesses
     */
    private function grantModule(Admin $admin, array $accesses): void
    {
        $module = ModuleQuery::create()->findOneByCode(QueryBuilder::getModuleCode());

        if ($module === null) {
            self::markTestSkipped('The QueryBuilder module is not registered in the test database.');
        }

        $accessManager = new AccessManager(0);
        $accessManager->build($accesses);

        (new ProfileModule())
            ->setProfileId($admin->getProfileId())
            ->setModuleId($module->getId())
            ->setAccess($accessManager->getAccessValue())
            ->save($this->getPropelConnection());
    }

    /**
     * @return list<string>
     */
    private function renderedEntryIds(Admin $admin): array
    {
        $this->session()->setAdminUser($admin);

        $hook = new BackHook(new SecurityContext($this->requestStack()));
        $event = new HookRenderBlockEvent('main.top-menu-tools', [], ['id', 'class', 'url', 'title']);
        $hook->onMainTopMenuTools($event);

        return array_map(static fn (Fragment $fragment): string => (string) $fragment->get('id'), iterator_to_array($event->get(), false));
    }

    private function requestStack(): RequestStack
    {
        return static::getContainer()->get('request_stack');
    }

    private function session(): Session
    {
        $session = $this->requestStack()->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
