<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Struct\LintedTranslationFileStruct;
use Shopwell\Core\System\Snippet\Struct\TranslationFile;
use Shopwell\Core\System\Snippet\Struct\TranslationFileCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LintedTranslationFileStruct::class)]
class LintedTranslationFileStructTest extends TestCase
{
    public function testGetDomainCollectionTreatsCustomDomainsAsStorefront(): void
    {
        $storefront = self::file('zh-CN.json', 'storefront');
        $custom = self::file('zh-CN.json', 'swag-cms-extensions');
        $messages = self::file('zh-CN.base.json', 'messages');

        $struct = new LintedTranslationFileStruct(new TranslationFileCollection([$storefront, $custom, $messages]));

        static::assertSame(
            [$storefront, $custom],
            array_values($struct->getDomainCollection('storefront')->getElements())
        );
        static::assertSame(
            [$messages],
            array_values($struct->getDomainCollection('messages')->getElements())
        );
    }

    public function testCompleteAndSpecificCollectionsReturnTheConstructorArguments(): void
    {
        $complete = new TranslationFileCollection([self::file('zh-CN.json', 'storefront')]);
        $specific = new TranslationFileCollection([self::file('zh-SG.json', 'storefront')]);

        $struct = new LintedTranslationFileStruct($complete, $specific);

        static::assertSame($complete, $struct->getCompleteCollection());
        static::assertSame($specific, $struct->getSpecificCollection());
    }

    public function testFixableAndFixingCollectionsCollectAddedFiles(): void
    {
        $struct = new LintedTranslationFileStruct();

        $fixable = self::file('zh.json', 'storefront');
        $fixed = self::file('en.json', 'storefront');

        $struct->addFixableFile($fixable);
        $struct->addToFixingCollection($fixed);

        static::assertSame([$fixable], array_values($struct->getFixableFiles()->getElements()));
        static::assertSame([$fixed], array_values($struct->getFixingCollection()->getElements()));
    }

    private static function file(string $filename, string $domain): TranslationFile
    {
        return new TranslationFile(
            filename: $filename,
            path: 'path/to/file',
            domain: $domain,
            locale: 'zh-CN',
            language: 'zh',
        );
    }
}
