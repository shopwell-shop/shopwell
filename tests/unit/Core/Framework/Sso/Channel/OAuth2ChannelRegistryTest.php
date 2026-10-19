<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Channel\OAuth2ChannelRegistry;
use Shopwell\Core\Framework\Sso\SsoException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2ChannelRegistry::class)]
class OAuth2ChannelRegistryTest extends TestCase
{
    public function testIndexesTheChannelsByTechnicalName(): void
    {
        $google = new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google');
        $wechat = new OAuth2Channel('wechat', 'generic', 'sso.oauth2.channel.wechat');

        $registry = new OAuth2ChannelRegistry([$google, $wechat]);

        static::assertTrue($registry->has('google'));
        static::assertTrue($registry->has('wechat'));
        static::assertFalse($registry->has('github'));
        static::assertSame($google, $registry->get('google'));
        static::assertSame(['google' => $google, 'wechat' => $wechat], $registry->all());
    }

    public function testWorksWithoutAnyChannel(): void
    {
        $registry = new OAuth2ChannelRegistry([]);

        static::assertFalse($registry->has('google'));
        static::assertSame([], $registry->all());
    }

    public function testThrowsForAChannelThePlatformDoesNotOffer(): void
    {
        $registry = new OAuth2ChannelRegistry([]);

        $this->expectExceptionObject(SsoException::oauth2ProviderNotAvailable('github'));

        $registry->get('github');
    }
}
