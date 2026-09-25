<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\DevOps\StaticAnalyze\Danger\Rules\ShopwellYamlConfigSchemaHint;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopwell\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwellYamlConfigSchemaHint::class)]
class ShopwellYamlConfigSchemaHintTest extends TestCase
{
    /**
     * @param list<string> $touchedFiles
     */
    #[TestDox('Warns when shopwell.yaml changes without a config-schema.json change')]
    #[DataProvider('touchedFilesProvider')]
    public function testConfigSchemaSync(array $touchedFiles, bool $expectWarning): void
    {
        $files = array_map(static fn (string $name): StubFile => new StubFile($name), $touchedFiles);
        $context = new Context(new StubPlatform(new StubPullRequest($files)));

        (new ShopwellYamlConfigSchemaHint())($context);

        static::assertSame($expectWarning, $context->hasWarnings());
        if ($expectWarning) {
            static::assertStringContainsString('config-schema.json', $context->getWarnings()[0]);
        }
    }

    public static function touchedFilesProvider(): \Generator
    {
        yield 'shopwell.yaml without schema update warns' => [['config/packages/shopwell.yaml'], true];
        yield 'shopwell.yaml with schema update passes' => [['config/packages/shopwell.yaml', 'config-schema.json'], false];
        yield 'schema-only change passes' => [['config-schema.json'], false];
        yield 'unrelated yaml change passes' => [['config/packages/framework.yaml'], false];
    }
}
