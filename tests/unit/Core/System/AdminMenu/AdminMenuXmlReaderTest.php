<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\UtilException;
use Shopwell\Core\System\AdminMenu\AdminMenuException;
use Shopwell\Core\System\AdminMenu\AdminMenuXmlReader;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(AdminMenuXmlReader::class)]
class AdminMenuXmlReaderTest extends TestCase
{
    public function testReadsAllMenuMetadataAndTypes(): void
    {
        $definitions = (new AdminMenuXmlReader())->read(__DIR__ . '/_fixtures/valid-menu.xml');

        static::assertCount(5, $definitions);
        static::assertSame('System', $definitions[0]['title']);
        static::assertSame('系统', $definitions[0]['translations']['zh-CN']['title']);
        static::assertSame('sw-system', $definitions[1]['parent']);
        static::assertSame(20, $definitions[1]['position']);
        static::assertSame('example.viewer', $definitions[1]['privilege']);
        static::assertSame('regular-briefcase', $definitions[1]['icon']);
        static::assertSame('#123456', $definitions[1]['color']);
        static::assertSame('general', $definitions[1]['groupName']);
        static::assertArrayHasKey('customFields', $definitions[1]['translations']['zh-CN']);
        static::assertSame('岗位说明', $definitions[1]['translations']['zh-CN']['customFields']['example_hint']);
        static::assertSame(['compact' => true, 'order' => 2, 'tags' => ['a', 'b']], $definitions[1]['translations']['zh-CN']['customFields']['example_options']);
        static::assertSame(['folder', 'menu', 'link', 'group', 'hidden'], array_column($definitions, 'type'));
        static::assertSame('https://example.com/help', $definitions[2]['linkTarget']);
        static::assertNull($definitions[0]['privilege']);
        static::assertNull($definitions[0]['parent']);
    }

    #[DataProvider('invalidFiles')]
    public function testRejectsInvalidPresentConfiguration(string $file): void
    {
        try {
            (new AdminMenuXmlReader())->read(__DIR__ . '/_fixtures/' . $file);
            static::fail('Invalid XML must fail instead of hiding the menu.');
        } catch (UtilException $exception) {
            static::assertStringContainsString($file, $exception->getMessage());
        }
    }

    #[DataProvider('invalidDefinitions')]
    public function testRejectsAmbiguousDefinitionsWithFileContext(string $file): void
    {
        try {
            (new AdminMenuXmlReader())->read(__DIR__ . '/_fixtures/' . $file);
            static::fail('Ambiguous menu definitions must be rejected.');
        } catch (AdminMenuException $exception) {
            static::assertStringContainsString($file, $exception->getMessage());
            static::assertStringContainsString('example', $exception->getMessage());
        }
    }

    public static function invalidDefinitions(): \Generator
    {
        yield 'duplicate title locale' => ['duplicate-title.xml'];
        yield 'malformed JSON value' => ['invalid-json.xml'];
        yield 'duplicate custom field name' => ['duplicate-field.xml'];
        yield 'custom fields without a title in that locale' => ['unknown-field-locale.xml'];
        yield 'nested explicit parent' => ['conflicting-parent.xml'];
        yield 'link without a target' => ['missing-link.xml'];
        yield 'target on a page' => ['unexpected-link.xml'];
        yield 'duplicate custom field locale' => ['duplicate-field-locale.xml'];
    }

    public static function invalidFiles(): \Generator
    {
        yield 'non-numeric position' => ['invalid-position.xml'];
        yield 'unknown field' => ['unknown-attribute.xml'];
        yield 'missing title' => ['missing-title.xml'];
        yield 'unsafe link' => ['invalid-link.xml'];
        yield 'empty identity' => ['empty-key.xml'];
        yield 'missing file' => ['not-found.xml'];
    }
}
