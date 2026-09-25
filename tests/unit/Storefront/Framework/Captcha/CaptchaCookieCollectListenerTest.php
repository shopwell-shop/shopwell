<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopwell\Core\Content\Cookie\Service\CookieProvider;
use Shopwell\Core\Content\Cookie\Struct\CookieGroup;
use Shopwell\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopwell\Storefront\Framework\Captcha\CaptchaCookieCollectListener;
use Shopwell\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CaptchaCookieCollectListener::class)]
class CaptchaCookieCollectListenerTest extends TestCase
{
    private const CONFIG_KEY = 'core.basicInformation.activeCaptchasV2.' . GoogleReCaptchaV2::CAPTCHA_NAME . '.isActive';

    private StaticSystemConfigService $systemConfigService;

    private CaptchaCookieCollectListener $listener;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService([self::CONFIG_KEY => true]);
        $this->listener = new CaptchaCookieCollectListener($this->systemConfigService);
    }

    public function testCaptchaConfigNotActive(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, false);

        $cookieGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        $cookieGroup->isRequired = true;

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([$cookieGroup]),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $captchaCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED)?->getEntries()?->get('_GRECAPTCHA');
        static::assertNull($captchaCookie);
    }

    public function testRequiredCookieGroupNotPresent(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([new CookieGroup('test')]),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        static::assertCount(1, $event->cookieGroupCollection);
    }

    public function testCaptchaCookieIsAdded(): void
    {
        $cookieGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        $cookieGroup->isRequired = true;

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([$cookieGroup]),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $captchaCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED)?->getEntries()?->get('_GRECAPTCHA');
        static::assertNotNull($captchaCookie);
    }
}
