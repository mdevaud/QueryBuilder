<?php

declare(strict_types=1);

namespace QueryBuilder\Command;

use QueryBuilder\Enum\Context;
use QueryBuilder\Service\DataDictionary;
use QueryBuilder\Service\FieldsBuilder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Command\ContainerAwareCommand;

/**
 * Debug command: dumps the merged data dictionary (joins + fields per context).
 */
class DictionaryCommand extends ContainerAwareCommand
{
    public function __construct(
        private readonly DataDictionary $dataDictionary,
        private readonly FieldsBuilder $fieldsBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('querybuilder:dictionary')
            ->setDescription('Dump the merged QueryBuilder data dictionary')
            ->addArgument('context', InputArgument::OPTIONAL, 'Filter fields by context (PRODUCT, CART, ORDER, CATEGORY, BRAND, GLOBAL)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $contextArgument = $input->getArgument('context');
        $context = $contextArgument !== null ? Context::from(strtoupper((string) $contextArgument)) : null;

        $output->writeln('<info>Joins</info>');
        foreach ($this->dataDictionary->getJoins() as $join) {
            $output->writeln(sprintf(
                '  %s %s ON %s.%s = %s.%s%s',
                $join->type,
                $join->table,
                $join->fromTable,
                $join->fromColumn,
                $join->table,
                $join->toColumn,
                $join->extraOn !== null ? ' AND ' . $join->extraOn : ''
            ));
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>Fields%s</info>', $context !== null ? ' (' . $context->value . ')' : ''));

        foreach ($this->dataDictionary->getFields($context) as $field) {
            $output->writeln(sprintf(
                '  %-40s %-10s %s',
                $field->code,
                $field->type,
                $field->column ?? '<expr> ' . $field->expression
            ));
        }

        if ($context !== null) {
            $output->writeln('');
            $output->writeln(sprintf('<info>Hooks (%s)</info>', $context->value));
            foreach ($this->dataDictionary->getHooks($context) as $hookCode => $hookLabel) {
                $output->writeln(sprintf('  %-40s %s', $hookCode, $hookLabel));
            }

            $output->writeln('');
            $output->writeln('<info>react-querybuilder fields JSON</info>');
            $output->writeln((string) json_encode($this->fieldsBuilder->buildForContext($context), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
