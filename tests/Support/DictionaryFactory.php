<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Support;

use QueryBuilder\Service\DataDictionary;

/** Builds dictionaries from the shipped base file plus YAML overrides written to temporary files. */
final class DictionaryFactory
{
    /** @var string[] */
    private static array $temporaryFiles = [];

    public static function base(): DataDictionary
    {
        return new DataDictionary(new StaticDictionaryOverrideLocator());
    }

    /** @param string ...$overrideYamls one YAML document per override file, merged in order */
    public static function withOverrides(string ...$overrideYamls): DataDictionary
    {
        $files = [];

        foreach ($overrideYamls as $yaml) {
            $file = tempnam(sys_get_temp_dir(), 'qb_dictionary_');
            file_put_contents($file, $yaml);
            self::$temporaryFiles[] = $file;
            $files[] = $file;
        }

        return new DataDictionary(new StaticDictionaryOverrideLocator($files));
    }

    public static function cleanUp(): void
    {
        foreach (self::$temporaryFiles as $file) {
            @unlink($file);
        }

        self::$temporaryFiles = [];
    }
}
