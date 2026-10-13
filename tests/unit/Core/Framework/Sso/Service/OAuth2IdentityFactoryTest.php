<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Service;

use League\OAuth2\Client\Provider\GenericResourceOwner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Provider\WeChat\WeChatResourceOwner;
use Shopwell\Core\Framework\Sso\Service\OAuth2IdentityFactory;
use Shopwell\Core\Framework\Sso\SsoException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2IdentityFactory::class)]
class OAuth2IdentityFactoryTest extends TestCase
{
    public function testReadsTheFieldsThePlatformsCommonlyReturn(): void
    {
        $owner = new GenericResourceOwner([
            'id' => 'sub-123',
            'email' => 'customer@example.com',
            'email_verified' => true,
            'name' => 'Jane Doe',
            'picture' => 'https://example.com/avatar.png',
        ], 'id');

        $identity = (new OAuth2IdentityFactory())->fromResourceOwner($this->channel(), $owner);

        static::assertSame('google', $identity->getTechnicalName());
        static::assertSame('sub-123', $identity->getProviderUserId());
        static::assertSame('customer@example.com', $identity->getEmail());
        static::assertTrue($identity->isEmailVerified());
        static::assertSame('Jane Doe', $identity->getNickname());
        static::assertSame('https://example.com/avatar.png', $identity->getAvatar());
    }

    public function testFallsBackToTheChannelWhenThePlatformDoesNotTellWhetherTheEmailIsVerified(): void
    {
        $owner = new GenericResourceOwner(['id' => 'sub-123', 'email' => 'customer@example.com'], 'id');

        $identity = (new OAuth2IdentityFactory())->fromResourceOwner($this->channel(), $owner);

        static::assertTrue($identity->isEmailVerified());
    }

    public function testUsesTheIdOfTheResourceOwnerOfTheChannel(): void
    {
        $owner = new WeChatResourceOwner(['unionid' => 'union-id', 'openid' => 'open-id', 'headimgurl' => 'https://example.com/head.png']);

        $wechat = new OAuth2Channel('wechat', 'generic', 'sso.oauth2.channel.wechat');

        $identity = (new OAuth2IdentityFactory())->fromResourceOwner($wechat, $owner);

        static::assertSame('wechat', $identity->getTechnicalName());
        static::assertSame('union-id', $identity->getProviderUserId());
        static::assertSame('https://example.com/head.png', $identity->getAvatar());
        static::assertFalse($identity->isEmailVerified());
    }

    public function testThrowsWhenThePlatformReturnsNoUserId(): void
    {
        $owner = new GenericResourceOwner([], 'id');

        $this->expectExceptionObject(SsoException::oauth2IdentityMissing('google'));

        (new OAuth2IdentityFactory())->fromResourceOwner($this->channel(), $owner);
    }

    private function channel(): OAuth2Channel
    {
        return new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google', providesVerifiedEmail: true);
    }
}
