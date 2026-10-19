<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\Login;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2IdentityEntity;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginService;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Account\Login\AccountLoginPage;
use Shopwell\Storefront\Page\Account\Login\AccountLoginPageLoadedEvent;
use Shopwell\Storefront\Page\Account\Login\OAuth2LoginPageSubscriber;
use Shopwell\Storefront\Page\Account\Profile\AccountProfilePage;
use Shopwell\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2LoginPageSubscriber::class)]
class OAuth2LoginPageSubscriberTest extends TestCase
{
    public function testSubscribesToTheLoginAndTheProfilePage(): void
    {
        static::assertSame(
            [
                AccountLoginPageLoadedEvent::class => 'addChannels',
                AccountProfilePageLoadedEvent::class => 'addCustomerBindings',
            ],
            OAuth2LoginPageSubscriber::getSubscribedEvents()
        );
    }

    public function testAddsTheEnabledChannelsToTheLoginPage(): void
    {
        $page = new AccountLoginPage();
        $event = new AccountLoginPageLoadedEvent($page, Generator::generateSalesChannelContext(), new Request());

        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('getEnabledChannels')
            ->willReturn([
                new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google', 'google'),
                new OAuth2Channel('wechat', 'generic', 'sso.oauth2.channel.wechat', 'wechat'),
            ]);

        (new OAuth2LoginPageSubscriber($loginService))->addChannels($event);

        $extension = $page->getExtension('oauth2Login');

        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame([
            ['technicalName' => 'google', 'labelSnippet' => 'sso.oauth2.channel.google', 'icon' => 'google'],
            ['technicalName' => 'wechat', 'labelSnippet' => 'sso.oauth2.channel.wechat', 'icon' => 'wechat'],
        ], $extension->get('channels'));
    }

    public function testAddsNoChannelsToTheLoginPageWhenNoneIsEnabled(): void
    {
        $page = new AccountLoginPage();
        $event = new AccountLoginPageLoadedEvent($page, Generator::generateSalesChannelContext(), new Request());

        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())->method('getEnabledChannels')->willReturn([]);

        (new OAuth2LoginPageSubscriber($loginService))->addChannels($event);

        $extension = $page->getExtension('oauth2Login');

        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame([], $extension->get('channels'));
    }

    public function testAddsTheBindingsOfTheCustomerToTheProfilePage(): void
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-id');

        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => $customer]);

        $identity = new OAuth2IdentityEntity();
        $identity->technicalName = 'google';

        $page = new AccountProfilePage();
        $event = new AccountProfilePageLoadedEvent($page, $context, new Request());

        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('getCustomerIdentities')
            ->with('customer-id', static::isInstanceOf(Context::class))
            ->willReturn(['google' => $identity]);
        $loginService->expects($this->once())
            ->method('getEnabledChannels')
            ->willReturn([new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google', 'google')]);

        (new OAuth2LoginPageSubscriber($loginService))->addCustomerBindings($event);

        $extension = $page->getExtension('oauth2Login');

        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame(['google'], $extension->get('linked'));
    }

    public function testAddsNoBindingsToTheProfilePageForAGuest(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);

        $page = new AccountProfilePage();
        $event = new AccountProfilePageLoadedEvent($page, $context, new Request());

        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->never())->method('getCustomerIdentities');
        $loginService->expects($this->once())->method('getEnabledChannels')->willReturn([]);

        (new OAuth2LoginPageSubscriber($loginService))->addCustomerBindings($event);

        $extension = $page->getExtension('oauth2Login');

        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame([], $extension->get('linked'));
    }
}
