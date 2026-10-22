<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Storefront\Test\Controller\StorefrontControllerTestBehaviour;

/**
 * Guards the switch that an administrator uses to take the login by sms off the login page.
 *
 * The form is only hidden client side, so the guard of the two routes is covered by the
 * SmsLoginControllerTest - this case only looks at what the customer is offered.
 *
 * @internal
 */
#[Package('checkout')]
class SmsLoginTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private const LOGIN_ENABLED_CONFIG_KEY = 'core.loginRegistration.loginEnabled';

    /**
     * The switch is written for the whole installation, so it has to be dropped again even when a
     * case fails - the cache of the configuration is dropped along with it.
     */
    protected function tearDown(): void
    {
        static::getContainer()->get(SystemConfigService::class)->delete(self::LOGIN_ENABLED_CONFIG_KEY);

        parent::tearDown();
    }

    /**
     * A missing value must not take the second way into an account away from a fresh installation.
     */
    public function testTheLoginPageOffersTheSmsFormByDefault(): void
    {
        $html = $this->getLoginPage();

        static::assertSame(1, substr_count($html, 'id="loginSmsPhone"'), 'the login page must ask for the number once');
        static::assertStringContainsString('class="login-sms-form"', $html);
        static::assertStringContainsString('action="/account/login/sms"', $html);
        static::assertStringContainsString('formaction="/account/login/sms/code"', $html);
    }

    public function testTheLoginPageHidesTheSmsFormWhenTheLoginIsSwitchedOff(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set(self::LOGIN_ENABLED_CONFIG_KEY, false);

        $html = $this->getLoginPage();

        static::assertSame(0, substr_count($html, 'loginSmsPhone'), 'a switched off login must not ask for a number');
        static::assertSame(0, substr_count($html, 'loginSmsCode'));
        static::assertStringNotContainsString('login-sms-form', $html);
        static::assertStringNotContainsString('action="/account/login/sms"', $html);

        // the login with the password is untouched
        static::assertStringContainsString('action="/account/login"', $html);
        static::assertStringContainsString('name="password"', $html);
    }

    public function testTheLoginPageOffersTheSmsFormAgainWhenTheLoginIsSwitchedOn(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set(self::LOGIN_ENABLED_CONFIG_KEY, true);

        $html = $this->getLoginPage();

        static::assertSame(1, substr_count($html, 'id="loginSmsPhone"'));
        static::assertStringContainsString('class="login-sms-form"', $html);
    }

    private function getLoginPage(): string
    {
        $response = $this->request('GET', '/account/login', []);

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return (string) $response->getContent();
    }
}
