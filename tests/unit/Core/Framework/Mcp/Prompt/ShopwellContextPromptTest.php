<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Prompt\ShopwellContextPrompt;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwellContextPrompt::class)]
class ShopwellContextPromptTest extends TestCase
{
    public function testInvokeReturnsMessagesWithRoleAndContent(): void
    {
        $prompt = new ShopwellContextPrompt();
        $result = ($prompt)();

        static::assertIsArray($result);
        static::assertNotEmpty($result);
        static::assertArrayHasKey('role', $result[0]);
        static::assertArrayHasKey('content', $result[0]);
        static::assertSame('user', $result[0]['role']);
    }

    public function testContentContainsKeyPhrases(): void
    {
        $prompt = new ShopwellContextPrompt();
        $result = ($prompt)();

        $content = $result[0]['content'];
        static::assertStringContainsString('Shopwell', $content);
        static::assertStringContainsString('entity', $content);
        static::assertStringContainsString('shopware-tool-search', $content);
        static::assertStringContainsString('shopware-toolsets-list', $content);
        static::assertStringContainsString('shopware-toolset-enable', $content);
        static::assertStringContainsString('allowlist and ACL permissions remain the security boundary', $content);
    }
}
