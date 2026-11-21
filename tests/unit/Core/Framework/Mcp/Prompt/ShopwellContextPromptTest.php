<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Prompt;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Prompt\ShopwellContextPrompt;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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
        static::assertNotCount(0, $result);
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
        static::assertStringContainsString('shopwell-tool-search', $content);
        static::assertStringContainsString('shopwell-toolsets-list', $content);
        static::assertStringContainsString('shopwell-toolset-enable', $content);
        static::assertStringContainsString('allowlist and ACL permissions remain the security boundary', $content);
    }

    public function testHandshakeRequestKeepsTheToolsetSessionGuidance(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/_mcp', 'POST', content: '{"jsonrpc":"2.0","id":1,"method":"prompts/get","params":{"name":"shopwell-context"}}'));

        static::assertSame((new ShopwellContextPrompt())(), (new ShopwellContextPrompt($requestStack))());
    }

    public function testStatelessEraOnlyNamesToolsTheEraOffers(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/_mcp', 'POST', content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'shopwell-context',
                '_meta' => [RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value],
            ],
        ], \JSON_THROW_ON_ERROR)));

        $content = (new ShopwellContextPrompt($requestStack))()[0]['content'];
        $handshake = (new ShopwellContextPrompt())()[0]['content'];

        static::assertStringNotContainsString('shopwell-toolsets-list', $content);
        static::assertStringNotContainsString('shopwell-toolset-enable', $content);
        static::assertStringNotContainsString('enable the matching toolset', $content);
        static::assertStringContainsString('shopwell-tool-search', $content);
        static::assertStringContainsString('?toolsets=', $content);
        static::assertStringContainsString('allowlist and ACL permissions remain the security boundary', $content);
        // only the discovery parts differ
        static::assertSame(strstr($handshake, '## Key concepts'), strstr($content, '## Key concepts'));
    }
}
