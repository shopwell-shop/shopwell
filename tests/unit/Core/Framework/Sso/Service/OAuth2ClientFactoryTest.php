<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Service;

use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use KnpU\OAuth2ClientBundle\DependencyInjection\KnpUOAuth2ClientExtension;
use KnpU\OAuth2ClientBundle\DependencyInjection\ProviderFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Channel\OAuth2ChannelRegistry;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2ClientEntity;
use Shopwell\Core\Framework\Sso\Service\OAuth2ClientFactory;
use Shopwell\Core\Framework\Sso\SsoException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2ClientFactory::class)]
class OAuth2ClientFactoryTest extends TestCase
{
    public function testBuildsTheClientOfTheBundleForTheConfiguredChannel(): void
    {
        $client = $this->createClientEntity('google');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('oauth2.callback', ['provider' => 'google'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://shop.test/oauth2/callback');

        $factory = $this->createFactory(
            new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google', defaultScopes: ['openid']),
            $urlGenerator
        );

        static::assertInstanceOf(GoogleClient::class, $factory->create($client, 'oauth2.callback'));
    }

    public function testUsesTheConfiguredScopesAndFallsBackToTheChannelDefaults(): void
    {
        $channel = new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google', defaultScopes: ['openid', 'profile']);

        $factory = $this->createFactory($channel, static::createStub(UrlGeneratorInterface::class));

        static::assertSame(['openid', 'profile'], $factory->getScopes($this->createClientEntity('google')));

        $configured = $this->createClientEntity('google');
        $configured->scopes = ['email'];

        static::assertSame(['email'], $factory->getScopes($configured));
    }

    public function testThrowsForAChannelWithoutCredentials(): void
    {
        $client = new OAuth2ClientEntity();
        $client->technicalName = 'google';

        $factory = $this->createFactory(
            new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google'),
            static::createStub(UrlGeneratorInterface::class)
        );

        $this->expectExceptionObject(SsoException::oauth2ProviderNotConfigured('google'));

        $factory->create($client, 'oauth2.callback');
    }

    public function testThrowsForAChannelThePlatformDoesNotOffer(): void
    {
        $factory = $this->createFactory(
            new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google'),
            static::createStub(UrlGeneratorInterface::class)
        );

        $this->expectExceptionObject(SsoException::oauth2ProviderNotAvailable('github'));

        $factory->create($this->createClientEntity('github'), 'oauth2.callback');
    }

    public function testAsksForASecretOnlyWhereTheProviderHasOne(): void
    {
        $factory = $this->createFactory(
            new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google'),
            static::createStub(UrlGeneratorInterface::class)
        );

        static::assertTrue($factory->needsClientSecret(new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google')));

        // apple signs the client secret out of its own key file, there is no static secret to store
        static::assertFalse($factory->needsClientSecret(new OAuth2Channel('apple', 'apple', 'sso.oauth2.channel.apple')));
    }

    public function testThrowsWhenARequiredSettingOfTheChannelIsNotConfigured(): void
    {
        $client = $this->createClientEntity('google');

        $factory = $this->createFactory($this->channelWithRequiredSetting(), static::createStub(UrlGeneratorInterface::class));

        $this->expectExceptionObject(SsoException::oauth2ProviderNotConfigured('google'));

        $factory->create($client, 'oauth2.callback');
    }

    public function testPassesOnTheSettingsTheChannelDeclares(): void
    {
        $client = $this->createClientEntity('google');
        $client->settings = ['hosted_domain' => 'shop.test'];

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('https://shop.test/oauth2/callback');

        $factory = $this->createFactory($this->channelWithRequiredSetting(), $urlGenerator);

        static::assertInstanceOf(GoogleClient::class, $factory->create($client, 'oauth2.callback'));
    }

    public function testLeavesOutSettingsOfAChannelThatDoesNotDeclareThem(): void
    {
        // `agentid` is a parameter of another channel type. Were the whole column passed on, the
        // configuration tree of google would reject the login over an unknown option - a channel that
        // drops a parameter in a newer version would keep breaking every login that way.
        $client = $this->createClientEntity('google');
        $client->settings = ['agentid' => '1000002'];

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('https://shop.test/oauth2/callback');

        $factory = $this->createFactory(
            new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google'),
            $urlGenerator
        );

        static::assertInstanceOf(GoogleClient::class, $factory->create($client, 'oauth2.callback'));
    }

    private function channelWithRequiredSetting(): OAuth2Channel
    {
        return new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google', settings: [
            [
                'name' => 'hosted_domain',
                'type' => 'text',
                'config' => ['label' => ['en-GB' => 'Hosted domain'], 'required' => true],
            ],
        ]);
    }

    private function createFactory(OAuth2Channel $channel, UrlGeneratorInterface $urlGenerator): OAuth2ClientFactory
    {
        return new OAuth2ClientFactory(
            new ProviderFactory($urlGenerator),
            new KnpUOAuth2ClientExtension(false),
            new OAuth2ChannelRegistry([$channel]),
            new RequestStack(),
        );
    }

    private function createClientEntity(string $technicalName): OAuth2ClientEntity
    {
        $client = new OAuth2ClientEntity();
        $client->technicalName = $technicalName;
        $client->clientId = 'client-id';
        $client->clientSecret = 'client-secret';

        return $client;
    }
}
