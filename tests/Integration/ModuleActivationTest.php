<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use QueryBuilder\Model\QueryBuilderRuleQuery;
use QueryBuilder\QueryBuilder;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;

/**
 * The module activates and deactivates on a fresh shop without error, and a
 * second activation leaves the schema alone (postActivation is idempotent).
 *
 * Prerequisites: a shop test database (bin/test-prepare) where the module is
 * registered (module:activate QueryBuilder, or a back-office module list refresh).
 */
final class ModuleActivationTest extends IntegrationTestCase
{
    private const TABLES = ['query_builder_rule', 'query_builder_action', 'query_builder_suggestion'];

    //The activation commits its own transaction (DDL): no surrounding transaction
    protected bool $useTransaction = false;

    #[Test]
    public function theModuleActivatesAndDeactivatesOnAFreshShop(): void
    {
        $module = ModuleQuery::create()->findOneByCode(QueryBuilder::getModuleCode());

        if ($module === null) {
            self::markTestSkipped('The QueryBuilder module is not registered in the test database.');
        }

        $instance = $module->createInstance();

        if ((int) $module->getActivate() === BaseModule::IS_ACTIVATED) {
            $instance->deActivate($module);
        }

        $instance->activate($module);
        self::assertSame(BaseModule::IS_ACTIVATED, (int) $this->reloadModule()->getActivate());
        $this->assertTablesExist();
        self::assertSame(0, QueryBuilderRuleQuery::create()->count(), 'a fresh activation ships no rule');

        $instance->deActivate($this->reloadModule());
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, (int) $this->reloadModule()->getActivate());
        $this->assertTablesExist('the rule tables survive a deactivation');

        //Second activation: the SQL is not replayed on an initialized shop
        $instance->activate($this->reloadModule());
        self::assertSame(BaseModule::IS_ACTIVATED, (int) $this->reloadModule()->getActivate());
        $this->assertTablesExist();
    }

    private function assertTablesExist(string $message = ''): void
    {
        $connection = $this->getPropelConnection();

        foreach (self::TABLES as $table) {
            $statement = $connection->query(sprintf("SHOW TABLES LIKE '%s'", $table));

            self::assertNotFalse($statement->fetch(), $message !== '' ? $message : sprintf('table %s is missing', $table));
        }
    }

    private function reloadModule(): \Thelia\Model\Module
    {
        $module = ModuleQuery::create()->findOneByCode(QueryBuilder::getModuleCode());
        self::assertNotNull($module);

        return $module;
    }
}
