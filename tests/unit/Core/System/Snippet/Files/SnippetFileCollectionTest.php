<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Files;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Files\SnippetFileCollection;
use Shopwell\Core\System\Snippet\SnippetException;
use Shopwell\Tests\Unit\Core\System\Snippet\Mock\MockSnippetFile;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetFileCollection::class)]
class SnippetFileCollectionTest extends TestCase
{
    public function testGet(): void
    {
        $collection = $this->getCollection();

        $result_en_GB = $collection->get('storefront.en-GB');
        $result_zh_CN = $collection->get('storefront.zh-CN');
        $result_NA = $collection->get('not.available');

        static::assertNotNull($result_en_GB);
        static::assertNotNull($result_zh_CN);
        static::assertSame('en-GB', $result_en_GB->getIso());
        static::assertSame('zh-CN', $result_zh_CN->getIso());
        static::assertNull($result_NA);
    }

    public function testGetIsoList(): void
    {
        $isoList = $this->getCollection()->getIsoList();

        static::assertCount(2, $isoList);
        static::assertContains('zh-CN', $isoList);
        static::assertContains('en-GB', $isoList);
    }

    public function testGetLanguageFilesByIso(): void
    {
        $collection = $this->getCollection();

        $result_en_GB = $collection->getSnippetFilesByIso('en-GB');
        $result_zh_CN = $collection->getSnippetFilesByIso('zh-CN');
        $result_empty = $collection->getSnippetFilesByIso('na-NA');
        $result_empty_two = $collection->getSnippetFilesByIso('');

        static::assertCount(1, $result_en_GB);
        static::assertCount(2, $result_zh_CN);
        static::assertCount(0, $result_empty);
        static::assertCount(0, $result_empty_two);

        static::assertSame('en-GB', $result_en_GB[0]->getIso());
        static::assertSame('zh-CN', $result_zh_CN[0]->getIso());
        static::assertEmpty($result_empty);
        static::assertEmpty($result_empty_two);
    }

    public function testGetBaseFileByIsoExpectException(): void
    {
        $collection = $this->getCollection();

        $this->expectExceptionObject(SnippetException::snippetFileNotRegistered('zh-SG'));

        $collection->getBaseFileByIso('zh-SG');
    }

    public function testGetBaseFileByIso(): void
    {
        $collection = $this->getCollection();

        $result_en_GB = $collection->getBaseFileByIso('en-GB');
        $result_zh_CN = $collection->getBaseFileByIso('zh-CN');

        static::assertSame('en-GB', $result_en_GB->getIso());
        static::assertTrue($result_en_GB->isBase());
        static::assertSame('zh-CN', $result_zh_CN->getIso());
        static::assertTrue($result_zh_CN->isBase());
    }

    public function testToArray(): void
    {
        $result = $this->getCollection()->toArray();

        static::assertCount(3, $result);

        $resultZh = array_filter($result, static fn (array $item) => $item['iso'] === 'zh-CN');

        $resultEn = array_filter($result, static fn (array $item) => $item['iso'] === 'en-GB');

        static::assertCount(2, $resultZh);
        static::assertCount(1, $resultEn);
    }

    public function testGetSnippetFilesWithLocaleFallbackForNonRegionalLocale(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.zh', 'zh', '{}', true, 'SwagPlugin'));

        $result = $collection->getSnippetFilesWithLocaleFallback('zh');
        static::assertCount(1, $result);
        static::assertSame('zh', $result[0]->getIso());
    }

    public function testGetSnippetFilesWithLocaleFallbackDoesNotIncludeAgnosticLanguage(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('agnostic.zh', 'zh', '{}', true, 'SwagPlugin'));

        $result = $collection->getSnippetFilesWithLocaleFallback('zh-SG');

        static::assertEmpty($result);
    }

    public function testGetSnippetFilesWithLocaleFallbackFallsBackToCanonicalForm(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.nl-NL', 'nl-NL', '{}', true, 'SwagPlugin'));

        $result = $collection->getSnippetFilesWithLocaleFallback('nl-BE');

        static::assertCount(1, $result);
        static::assertSame('nl-NL', $result[0]->getIso());
    }

    public function testGetSnippetFilesWithLocaleFallbackUsesStaticMapForEnglish(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.en-GB', 'en-GB', '{}', true, 'SwagPayPal'));

        $result = $collection->getSnippetFilesWithLocaleFallback('en-AU');

        static::assertCount(1, $result);
        static::assertSame('en-GB', $result[0]->getIso());
    }

    public function testGetSnippetFilesWithLocaleFallbackUsesStaticMapForChinese(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.zh-CN', 'zh-CN', '{}', true, 'SwagPayPal'));

        $result = $collection->getSnippetFilesWithLocaleFallback('zh-SG');

        static::assertCount(1, $result);
        static::assertSame('zh-CN', $result[0]->getIso());
    }

    public function testGetSnippetFilesWithLocaleFallbackNoFallbackForNonCanonicalLanguage(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.da-DK', 'da-DK', '{}', true, 'SwagPlugin'));

        $result = $collection->getSnippetFilesWithLocaleFallback('da-GL');

        static::assertEmpty($result);
    }

    public function testGetSnippetFilesWithLocaleFallbackReturnsEmptyForUnknownLocale(): void
    {
        $collection = $this->getCollection();

        $result = $collection->getSnippetFilesWithLocaleFallback('fr-FR');

        static::assertEmpty($result);
    }

    public function testGetSnippetFilesWithLocaleFallbackCombinesBothPriorityLevels(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('canonical.zh-CN', 'zh-CN', '{}', false, 'SwagPlugin'));
        $collection->add(new MockSnippetFile('country.zh-SG', 'zh-SG', '{}', false, 'SwagPlugin'));

        $result = $collection->getSnippetFilesWithLocaleFallback('zh-SG');

        static::assertCount(2, $result);
        static::assertSame('zh-CN', $result[0]->getIso());
        static::assertSame('zh-SG', $result[1]->getIso());
    }

    public function testGetSnippetFilesWithLocaleFallbackDoesNotDoubleCountCanonicalLocale(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.zh-CN', 'zh-CN', '{}', true, 'SwagPlugin'));

        $result = $collection->getSnippetFilesWithLocaleFallback('zh-CN');

        static::assertCount(1, $result);
        static::assertSame('zh-CN', $result[0]->getIso());
    }

    public function testGetByName(): void
    {
        $collection = $this->getCollection();

        $found = $collection->getByName('storefront.zh-CN');

        static::assertNotNull($found);
        static::assertSame('storefront.zh-CN', $found->getName());
        static::assertNull($collection->getByName('not.available'));
    }

    public function testGetFilesArraySplitsByBaseFlag(): void
    {
        $collection = $this->getCollection();

        $baseFiles = $collection->getFilesArray();
        $extensionFiles = $collection->getFilesArray(false);

        static::assertCount(2, $baseFiles);
        static::assertCount(1, $extensionFiles);
        foreach ($baseFiles as $file) {
            static::assertTrue($file['isBase']);
        }
        foreach ($extensionFiles as $file) {
            static::assertFalse($file['isBase']);
        }
    }

    public function testSetAndRemoveUseTheGivenKey(): void
    {
        $file = new MockSnippetFile('storefront.en-GB', 'en-GB');

        $collection = new SnippetFileCollection();
        $collection->set('my-key', $file);

        static::assertSame($file, $collection->get('my-key'));

        $collection->remove('my-key');

        static::assertNull($collection->get('my-key'));
    }

    public function testClearDropsAllFiles(): void
    {
        $collection = $this->getCollection();

        $collection->clear();

        static::assertCount(0, $collection);
    }

    public function testHasFileForPathMatchesOnlyExistingCollectionFiles(): void
    {
        $existingFile = new MockSnippetFile('storefront.zh', 'zh-CN');

        $collection = new SnippetFileCollection();
        $collection->add($existingFile);

        static::assertTrue($collection->hasFileForPath($existingFile->getPath()));
        static::assertFalse($collection->hasFileForPath(__FILE__));
        static::assertFalse($collection->hasFileForPath('/does/not/exist.json'));
    }

    public function testGetApiAlias(): void
    {
        static::assertSame('snippet_file_collection', (new SnippetFileCollection())->getApiAlias());
    }

    private function getCollection(): SnippetFileCollection
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.zh-CN', 'zh-CN', '{}', true, 'SwagPlugin'));
        $collection->add(new MockSnippetFile('storefront.zh-CN_extension', 'zh-CN', '{}', false, 'SwagPlugin'));
        $collection->add(new MockSnippetFile('storefront.en-GB', 'en-GB', '{}', true));

        return $collection;
    }
}
