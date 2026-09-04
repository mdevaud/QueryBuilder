<?php

declare(strict_types=1);

namespace QueryBuilder\Command;

use QueryBuilder\Query\RuntimeContext;
use QueryBuilder\Service\RuleEngine;
use QueryBuilder\Service\RuntimeContextFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Command\ContainerAwareCommand;

/**
 * Debug command: executes the active rules bound to a hook and prints the
 * results of their actions.
 */
class RunCommand extends ContainerAwareCommand
{
    public function __construct(
        private readonly RuleEngine $ruleEngine,
        private readonly RuntimeContextFactory $runtimeContextFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('querybuilder:run')
            ->setDescription('Execute the active QueryBuilder rules bound to a hook')
            ->addArgument('hook', InputArgument::REQUIRED, 'Hook code (ex: product.top)')
            ->addOption('customer', null, InputOption::VALUE_REQUIRED, 'Customer id')
            ->addOption('product', null, InputOption::VALUE_REQUIRED, 'Product id')
            ->addOption('cart-products', null, InputOption::VALUE_REQUIRED, 'Comma-separated product ids of the cart')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale', 'fr_FR');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cartProducts = (string) $input->getOption('cart-products');

        $runtimeContext = $this->runtimeContextFactory->withProviderParameters(new RuntimeContext(
            customerId: $input->getOption('customer') !== null ? (int) $input->getOption('customer') : null,
            productId: $input->getOption('product') !== null ? (int) $input->getOption('product') : null,
            cartProductIds: $cartProducts !== ''
                ? array_map('intval', explode(',', $cartProducts))
                : [],
            locale: (string) $input->getOption('locale'),
        ));

        $executedActions = $this->ruleEngine->executeHook((string) $input->getArgument('hook'), $runtimeContext);

        if ($executedActions === []) {
            $output->writeln('<comment>No rule matched this hook in the given context.</comment>');

            return self::SUCCESS;
        }

        foreach ($executedActions as $executedAction) {
            $output->writeln(sprintf(
                '<info>Rule "%s" → action "%s" (%s, %s)</info>',
                $executedAction->rule->getName(),
                $executedAction->action->getName(),
                $executedAction->action->getCode(),
                $executedAction->type
            ));

            if ($executedAction->result->productIds !== []) {
                $output->writeln('  products: ' . implode(', ', $executedAction->result->productIds));
            }

            if ($executedAction->result->html !== null) {
                $output->writeln('  html: ' . substr($executedAction->result->html, 0, 200));
            }
        }

        return self::SUCCESS;
    }
}
