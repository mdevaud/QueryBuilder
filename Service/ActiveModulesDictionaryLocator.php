<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use QueryBuilder\Dictionary\DictionaryOverrideLocatorInterface;
use QueryBuilder\QueryBuilder;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Every active module may ship a Config/query_builder.yml: they are merged over
 * the base dictionary in module position order.
 */
final readonly class ActiveModulesDictionaryLocator implements DictionaryOverrideLocatorInterface
{
    public function locate(): array
    {
        $files = [];

        $modules = ModuleQuery::create()
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->filterByCode(QueryBuilder::getModuleCode(), Criteria::NOT_EQUAL)
            ->orderByPosition()
            ->find();

        foreach ($modules as $module) {
            //A Thelia 3 module lives either in local/modules or in vendor/thelia/modules (Composer)
            $file = $module->getAbsoluteBaseDir() . DS . 'Config' . DS . DataDictionary::DICTIONARY_FILENAME;

            if (is_file($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
