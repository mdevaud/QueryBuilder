<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use QueryBuilder\Action\ActionInterface;
use QueryBuilder\Action\DisplayProductsListAction;
use QueryBuilder\Enum\Context;
use QueryBuilder\Model\QueryBuilderAction;
use QueryBuilder\Model\QueryBuilderRule;
use QueryBuilder\Query\RuntimeContext;
use QueryBuilder\QueryBuilder;
use QueryBuilder\Service\HookResultPresenter;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Each display action of the hook payload carries its description, a free label for the front.
 *
 * Prerequisites: a shop test database (bin/test-prepare) where the module is
 * activated (module:activate QueryBuilder).
 */
final class HookPayloadTest extends ActionIntegrationTestCase
{
    private const HOOK = 'home.top';

    protected function setUp(): void
    {
        parent::setUp();

        $module = ModuleQuery::create()->findOneByCode(QueryBuilder::getModuleCode());

        if ($module === null || (int) $module->getActivate() !== BaseModule::IS_ACTIVATED) {
            self::markTestSkipped('The QueryBuilder module is not activated in the test database.');
        }
    }

    #[Test]
    public function aDisplayActionExposesItsDescription(): void
    {
        $productReference = $this->displayRule('Our favourites of the week');

        $action = $this->presentedAction($productReference);

        self::assertSame('Our favourites of the week', $action['description']);
    }

    #[Test]
    public function anEmptyDescriptionIsExposedAsNull(): void
    {
        $productReference = $this->displayRule('');

        $action = $this->presentedAction($productReference);

        self::assertArrayHasKey('description', $action);
        self::assertNull($action['description']);
    }

    /** @return array{rule: ?string, action: ?string, description: ?string, product_ids: int[], offers: array} */
    private function presentedAction(string $productReference): array
    {
        $result = $this->getService(HookResultPresenter::class)->present(self::HOOK, new RuntimeContext(), withOffers: false);

        $actions = array_values(array_filter(
            $result['actions'],
            static fn (array $action): bool => $action['action'] === 'Display action ' . $productReference,
        ));
        self::assertCount(1, $actions);
        self::assertNotSame([], $actions[0]['product_ids']);

        return $actions[0];
    }

    private function displayRule(string $description): string
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());

        $rule = (new QueryBuilderRule())
            ->setName('Display rule under test')
            ->setContext(Context::GLOBAL_SCOPE->value)
            ->setHooks(json_encode([self::HOOK], \JSON_THROW_ON_ERROR))
            ->setActivate(1);
        $rule->save();

        (new QueryBuilderAction())
            ->setRuleId($rule->getId())
            ->setName('Display action ' . $product->getRef())
            ->setDescription($description)
            ->setCode(DisplayProductsListAction::getCode())
            ->setType(ActionInterface::TYPE_DISPLAY)
            ->setConditionTree(json_encode(
                ['combinator' => 'and', 'rules' => [['field' => 'product_ref', 'operator' => '=', 'value' => $product->getRef()]]],
                \JSON_THROW_ON_ERROR
            ))
            ->setParameters(json_encode(['limit' => 1], \JSON_THROW_ON_ERROR))
            ->setActivate(1)
            ->save();

        return (string) $product->getRef();
    }
}
