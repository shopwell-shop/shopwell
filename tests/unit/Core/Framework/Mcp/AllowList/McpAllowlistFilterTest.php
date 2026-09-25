<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\AllowList;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\AllowList\McpAllowlistFilter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpAllowlistFilter::class)]
class McpAllowlistFilterTest extends TestCase
{
    private McpAllowlistFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new McpAllowlistFilter();
    }

    // ── Tools ────────────────────────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function toolCallDeniedProvider(): iterable
    {
        yield 'tool in allowlist is not denied' => ['shopwell-entity-search', ['shopwell-entity-search', 'shopwell-entity-read'], false];
        yield 'tool not in allowlist is denied' => ['shopwell-entity-delete', ['shopwell-entity-search'], true];
        yield 'empty allowlist denies everything' => ['any-tool', [], true];
        yield 'exact name match required' => ['shopwell-entity', ['shopwell-entity-search'], true];
    }

    /**
     * @param list<string> $allowlist
     */
    #[DataProvider('toolCallDeniedProvider')]
    public function testIsToolCallDenied(string $toolName, array $allowlist, bool $expectedDenied): void
    {
        static::assertSame($expectedDenied, $this->filter->isToolCallDenied($toolName, $allowlist));
    }

    // ── Resources ────────────────────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function resourceReadDeniedProvider(): iterable
    {
        yield 'resource in allowlist is not denied' => ['shopwell://entities', ['shopwell://entities', 'shopwell://currencies'], false];
        yield 'resource not in allowlist is denied' => ['shopwell://state-machines', ['shopwell://entities'], true];
        yield 'empty allowlist denies everything' => ['shopwell://entities', [], true];
        yield 'tool-result URI is never denied even with empty allowlist' => ['shopwell://tool-result/abc123', [], false];
        yield 'tool-result URI is never denied when not in allowlist' => ['shopwell://tool-result/xyz', ['shopwell://entities'], false];
    }

    /**
     * @param list<string> $allowlist
     */
    #[DataProvider('resourceReadDeniedProvider')]
    public function testIsResourceReadDenied(string $uri, array $allowlist, bool $expectedDenied): void
    {
        static::assertSame($expectedDenied, $this->filter->isResourceReadDenied($uri, $allowlist));
    }

    // ── Prompts ──────────────────────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function promptGetDeniedProvider(): iterable
    {
        yield 'prompt in allowlist is not denied' => ['shopwell-context', ['shopwell-context'], false];
        yield 'prompt not in allowlist is denied' => ['other-prompt', ['shopwell-context'], true];
        yield 'empty allowlist denies everything' => ['shopwell-context', [], true];
    }

    /**
     * @param list<string> $allowlist
     */
    #[DataProvider('promptGetDeniedProvider')]
    public function testIsPromptGetDenied(string $promptName, array $allowlist, bool $expectedDenied): void
    {
        static::assertSame($expectedDenied, $this->filter->isPromptGetDenied($promptName, $allowlist));
    }
}
