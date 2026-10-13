<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Provider\WeChat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Provider\WeChat\WeChatResourceOwner;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WeChatResourceOwner::class)]
class WeChatResourceOwnerTest extends TestCase
{
    public function testPrefersTheUnionIdSoThatTheBindingSurvivesNewAppCredentials(): void
    {
        $owner = new WeChatResourceOwner(['unionid' => 'union-id', 'openid' => 'open-id']);

        static::assertSame('union-id', $owner->getId());
    }

    public function testFallsBackToTheOpenId(): void
    {
        $owner = new WeChatResourceOwner(['openid' => 'open-id']);

        static::assertSame('open-id', $owner->getId());
    }

    public function testHasNoIdWithoutBothFields(): void
    {
        $owner = new WeChatResourceOwner(['nickname' => 'Jane']);

        static::assertSame('', $owner->getId());
    }

    public function testReturnsTheRawResponse(): void
    {
        $response = ['unionid' => 'union-id', 'nickname' => 'Jane'];
        $owner = new WeChatResourceOwner($response);

        static::assertSame($response, $owner->toArray());
    }
}
