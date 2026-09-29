<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Struct\FixableTranslationFileCollection;
use Shopwell\Core\System\Snippet\Struct\TranslationFile;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FixableTranslationFileCollection::class)]
class FixableTranslationFileCollectionTest extends TestCase
{
    public function testAddGroupsFilesByTheirAgnosticPath(): void
    {
        $chinese = self::file('zh-CN.json', 'zh-CN', 'zh');
        $singapore = self::file('zh-SG.json', 'zh-SG', 'zh');

        $collection = new FixableTranslationFileCollection();
        $collection->add($chinese);
        $collection->add($singapore);

        static::assertCount(2, $collection);
        static::assertSame(
            ['path/to/file/storefront.zh.json' => ['zh-CN' => $chinese, 'zh-SG' => $singapore]],
            $collection->getMapping()
        );
    }

    public function testSetGroupsTheFileByItsAgnosticPath(): void
    {
        $english = self::file('en-GB.json', 'en-GB', 'en');

        $collection = new FixableTranslationFileCollection();
        $collection->set('custom-key', $english);

        static::assertSame($english, $collection->get('custom-key'));
        static::assertSame(
            ['path/to/file/storefront.en.json' => ['en-GB' => $english]],
            $collection->getMapping()
        );
    }

    private static function file(string $filename, string $locale, string $language): TranslationFile
    {
        return new TranslationFile(
            filename: $filename,
            path: 'path/to/file',
            domain: 'storefront',
            locale: $locale,
            language: $language,
        );
    }
}
