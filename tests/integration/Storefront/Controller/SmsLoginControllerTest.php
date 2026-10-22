<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Service\SmsLoginService;
use Shopwell\Core\DevOps\Environment\EnvironmentHelper;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Util\PhoneNumberMaskerInterface;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizerInterface;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The storefront side of the login by sms.
 *
 * Every case works on a number of its own, so a run does not depend on what another run left in the
 * database or in the cache of the client.
 *
 * @internal
 */
#[Package('checkout')]
class SmsLoginControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private const CODE = '123456';

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

    public function testLoginPageRendersTheSmsForm(): void
    {
        $browser = $this->createBrowser();
        $html = $this->visitLoginPage($browser);

        // the password login is untouched
        static::assertStringContainsString('action="/account/login"', $html);
        static::assertStringContainsString('name="password"', $html);

        // the number is typed once: one form, one phone field, one code field
        static::assertStringContainsString('class="login-sms-form"', $html);
        static::assertStringContainsString('action="/account/login/sms"', $html);
        static::assertSame(1, substr_count($html, 'name="phone"'), 'the login card must ask for the number once');
        static::assertSame(1, substr_count($html, 'name="code"'));

        // the second button of the same form asks for the code, so the form is not validated then
        static::assertStringContainsString('formaction="/account/login/sms/code"', $html);
        static::assertStringContainsString('formnovalidate', $html);

        // the code field is ready for a one time code of six digits
        static::assertStringContainsString('autocomplete="one-time-code"', $html);
        static::assertStringContainsString('inputmode="numeric"', $html);
        static::assertStringContainsString('pattern="\d{6}"', $html);
        static::assertStringContainsString('maxlength="6"', $html);
    }

    public function testCodeRequestAnswersLikeASuccessForAnUnknownNumber(): void
    {
        $phone = $this->randomPhone();
        $deliveriesBefore = $this->countDeliveries($this->mask($phone));

        $browser = $this->createBrowser();
        $response = $this->requestCode($browser, $phone);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('/account/login', $response->getTargetUrl());
        static::assertStringContainsString('phone=' . urlencode($phone), $response->getTargetUrl());

        // the message of a sent code, although nobody is registered for the number
        static::assertStringContainsString(
            'If the number is registered, the code was sent',
            $this->followRedirect($browser, $response)
        );

        static::assertSame($deliveriesBefore, $this->countDeliveries($this->mask($phone)), 'no message may be sent for an unknown number');
    }

    public function testCodeRequestSendsAMessageToTheNumberOfACustomer(): void
    {
        $phone = $this->randomPhone();
        $this->createCustomer($phone);
        $deliveriesBefore = $this->countDeliveries($this->mask($phone));

        $browser = $this->createBrowser();
        $response = $this->requestCode($browser, $phone);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame($deliveriesBefore + 1, $this->countDeliveries($this->mask($phone)));

        // the number is handed back so the customer does not have to type it twice: it is part of the
        // redirect and it is filled into both forms of the page
        static::assertStringContainsString('phone=' . urlencode($phone), $response->getTargetUrl());
        static::assertStringContainsString('value="' . $phone . '"', $this->followRedirect($browser, $response));
    }

    public function testCodeRequestIsThrottledWithinTheCooldown(): void
    {
        $phone = $this->randomPhone();
        $this->createCustomer($phone);
        $deliveriesBefore = $this->countDeliveries($this->mask($phone));

        $browser = $this->createBrowser();

        static::assertInstanceOf(RedirectResponse::class, $this->requestCode($browser, $phone));
        $response = $this->requestCode($browser, $phone);

        static::assertSame(
            $deliveriesBefore + 1,
            $this->countDeliveries($this->mask($phone)),
            'a second code must not be sent within the cooldown'
        );

        static::assertStringContainsString(
            'Too many requests. Please wait 60 seconds before trying again.',
            $this->followRedirect($browser, $response)
        );
    }

    public function testLoginWithTheCodeLogsTheCustomerIn(): void
    {
        $phone = $this->randomPhone();
        $customer = $this->createCustomer($phone);
        $this->storeCode($phone, self::CODE);

        $browser = $this->createBrowser();
        $response = $this->requestLogin($browser, $phone, self::CODE);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(302, $response->getStatusCode());
        static::assertStringContainsString('/account', $response->getTargetUrl());

        // the session of the browser is the one of the customer now
        static::assertSame(200, $this->followAccountPage($browser));
        static::assertNotNull($this->reloadCustomer($customer->getId())?->getLastLogin(), 'the customer was logged in');
    }

    public function testLoginWithTheCodeIsPossibleOnlyOnce(): void
    {
        $phone = $this->randomPhone();
        $this->createCustomer($phone);
        $this->storeCode($phone, self::CODE);

        $browser = $this->createBrowser();

        static::assertInstanceOf(RedirectResponse::class, $this->requestLogin($browser, $phone, self::CODE));
        static::assertSame(200, $this->followAccountPage($browser));

        $browser->request('GET', $this->storefrontUrl('/account/logout'));
        static::assertSame(302, $browser->getResponse()->getStatusCode());

        // the code was dropped the moment it matched
        $response = $this->requestLogin($browser, $phone, self::CODE);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('/account/login', $response->getTargetUrl());
        static::assertSame(302, $this->followAccountPage($browser));
    }

    public function testLoginWithAWrongCodeIsRefused(): void
    {
        $phone = $this->randomPhone();
        $this->createCustomer($phone);
        $this->storeCode($phone, self::CODE);

        $browser = $this->createBrowser();
        $response = $this->requestLogin($browser, $phone, '000000');

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('/account/login', $response->getTargetUrl());
        static::assertStringContainsString(
            'The verification code is invalid or has expired',
            $this->followRedirect($browser, $response)
        );

        static::assertSame(302, $this->followAccountPage($browser));
    }

    public function testLoginWithoutACodeIsRefused(): void
    {
        $phone = $this->randomPhone();
        $this->createCustomer($phone);

        $browser = $this->createBrowser();
        $response = $this->requestLogin($browser, $phone, self::CODE);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(302, $this->followAccountPage($browser));
    }

    public function testLoginDropsTheCodeAfterTheLastAttempt(): void
    {
        $phone = $this->randomPhone();
        $this->createCustomer($phone);
        $this->storeCode($phone, self::CODE);

        $browser = $this->createBrowser();

        for ($attempt = 1; $attempt <= SmsLoginService::MAX_ATTEMPTS; ++$attempt) {
            $this->requestLogin($browser, $phone, '000000');
        }

        // the attempts used the budget up, the right code does not help any more
        $response = $this->requestLogin($browser, $phone, self::CODE);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('/account/login', $response->getTargetUrl());
        static::assertSame(302, $this->followAccountPage($browser));
    }

    /**
     * A switched off login must not answer a code request at all: the answer is the message of the
     * switched off login, no quota is spent and no message leaves the shop.
     */
    public function testCodeRequestIsRefusedWhenTheLoginIsSwitchedOff(): void
    {
        $phone = $this->randomPhone();
        $this->createCustomer($phone);
        $deliveriesBefore = $this->countDeliveries($this->mask($phone));

        $this->switchLoginOff();

        $browser = $this->createBrowser();
        $response = $this->requestCode($browser, $phone);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('/account/login', $response->getTargetUrl());
        static::assertStringContainsString(
            'The login with an sms verification code is not enabled.',
            $this->followRedirect($browser, $response)
        );

        static::assertSame(
            $deliveriesBefore,
            $this->countDeliveries($this->mask($phone)),
            'a switched off login must not send a code'
        );
    }

    /**
     * The form is hidden, but the route stays reachable for whoever posts it - the code that would
     * log the customer in must not be accepted by a switched off login.
     */
    public function testLoginIsRefusedWhenTheLoginIsSwitchedOff(): void
    {
        $phone = $this->randomPhone();
        $customer = $this->createCustomer($phone);
        $this->storeCode($phone, self::CODE);

        $this->switchLoginOff();

        $browser = $this->createBrowser();
        $response = $this->requestLogin($browser, $phone, self::CODE);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('/account/login', $response->getTargetUrl());
        static::assertStringContainsString(
            'The login with an sms verification code is not enabled.',
            $this->followRedirect($browser, $response)
        );

        // the right code does not help either, the second way into the account stays closed
        static::assertSame(302, $this->followAccountPage($browser));
        static::assertNull($this->reloadCustomer($customer->getId())?->getLastLogin(), 'the customer was not logged in');
    }

    /**
     * A quota that a refused request may not spend: the request after switching the login on again
     * still sends the code, so the refused one never reached the limiter.
     */
    public function testARefusedCodeRequestDoesNotSpendTheQuota(): void
    {
        $phone = $this->randomPhone();
        $this->createCustomer($phone);
        $deliveriesBefore = $this->countDeliveries($this->mask($phone));

        $this->switchLoginOff();

        $browser = $this->createBrowser();
        $this->requestCode($browser, $phone);

        $this->switchLoginOn();

        $response = $this->requestCode($browser, $phone);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(
            $deliveriesBefore + 1,
            $this->countDeliveries($this->mask($phone)),
            'the refused request must not have spent the cooldown of the number'
        );
    }

    private function switchLoginOff(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set(self::LOGIN_ENABLED_CONFIG_KEY, false);
    }

    private function switchLoginOn(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set(self::LOGIN_ENABLED_CONFIG_KEY, true);
    }

    private function createBrowser(): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());

        // the client of the kernel is shared by all tests of the process, so a session an earlier
        // test left behind is dropped before this one starts
        $browser->request('GET', $this->storefrontUrl('/account/logout'));

        return $browser;
    }

    private function storefrontUrl(string $path): string
    {
        return EnvironmentHelper::getVariable('APP_URL') . $path;
    }

    /**
     * A number no other run of this test has registered before.
     */
    private function randomPhone(): string
    {
        return '+49151' . str_pad((string) random_int(0, 99999999), 8, '0', \STR_PAD_LEFT);
    }

    private function mask(string $phone): string
    {
        $normalizer = static::getContainer()->get(PhoneNumberNormalizerInterface::class);
        $masker = static::getContainer()->get(PhoneNumberMaskerInterface::class);

        return $masker->mask($normalizer->normalize($phone));
    }

    private function requestCode(KernelBrowser $browser, string $phone): Response
    {
        $browser->request('POST', $this->storefrontUrl('/account/login/sms/code'), [
            'phone' => $phone,
            'redirectTo' => 'frontend.account.home.page',
        ]);

        return $browser->getResponse();
    }

    private function requestLogin(KernelBrowser $browser, string $phone, string $code): Response
    {
        $browser->request('POST', $this->storefrontUrl('/account/login/sms'), [
            'phone' => $phone,
            'code' => $code,
        ]);

        return $browser->getResponse();
    }

    private function visitLoginPage(KernelBrowser $browser): string
    {
        $browser->request('GET', $this->storefrontUrl('/account/login'));

        return (string) $browser->getResponse()->getContent();
    }

    /**
     * Walks the redirect the controller answered, the way the customer's browser does - a flash
     * message and the number carried in the query only show up on the page behind the redirect.
     */
    private function followRedirect(KernelBrowser $browser, Response $response): string
    {
        static::assertInstanceOf(RedirectResponse::class, $response);

        $target = $response->getTargetUrl();
        if (str_starts_with($target, '/')) {
            $target = $this->storefrontUrl($target);
        }

        $browser->request('GET', $target);

        return (string) $browser->getResponse()->getContent();
    }

    private function followAccountPage(KernelBrowser $browser): int
    {
        $browser->request('GET', $this->storefrontUrl('/account'));

        return $browser->getResponse()->getStatusCode();
    }

    private function storeCode(string $phone, string $code): void
    {
        $normalizer = static::getContainer()->get(PhoneNumberNormalizerInterface::class);
        $service = static::getContainer()->get(SmsLoginService::class);

        $service->storeCode($normalizer->normalize($phone), $code, $this->getSalesChannelId());
    }

    private function countDeliveries(string $maskedPhone): int
    {
        $connection = static::getContainer()->get(Connection::class);

        return (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `sms_delivery` WHERE `phone_masked` = :maskedPhone',
            ['maskedPhone' => $maskedPhone]
        );
    }

    private function createCustomer(string $phoneNumber): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'gender' => $this->getValidGender(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'test12345',
            'name' => 'Max Mustermann',
            'phoneNumber' => $phoneNumber,
            'active' => true,
            'gender' => $this->getValidGender(),
            'customerNumber' => 'SW' . Uuid::randomHex(),
            'guest' => false,
        ];

        /** @var EntityRepository<CustomerCollection> $repository */
        $repository = static::getContainer()->get('customer.repository');
        $repository->create([$customer], Context::createDefaultContext());

        return $this->reloadCustomer($customerId) ?? throw new \RuntimeException('The customer was not created.');
    }

    private function reloadCustomer(string $customerId): ?CustomerEntity
    {
        /** @var EntityRepository<CustomerCollection> $repository */
        $repository = static::getContainer()->get('customer.repository');

        return $repository->search(new Criteria([$customerId]), Context::createDefaultContext())->getEntities()->first();
    }
}
