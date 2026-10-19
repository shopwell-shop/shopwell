<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Service\SmsLoginService;
use Shopwell\Core\DevOps\Environment\EnvironmentHelper;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizerInterface;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The throttle of the answer to a code.
 *
 * It lives in a class of its own because the test environment turns the rate limiter off: the
 * compiler pass wraps every factory into one that never limits, and the pass only runs while the
 * container is compiled. The cases below therefore need a kernel that was compiled without it, which
 * is why the class boots one of its own.
 *
 * Every case uses a number of its own, so the budget of one case is not spent by another one.
 *
 * @internal
 */
#[Package('checkout')]
class SmsLoginRateLimiterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private const CODE = '654321';

    /**
     * The requests the limiter accepts per number within a minute, see the configured limits.
     */
    private const BUDGET = 5;

    private static bool $kernelBooted = false;

    public static function setUpBeforeClass(): void
    {
        DisableRateLimiterCompilerPass::disableNoLimit();
    }

    public static function tearDownAfterClass(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();

        // shut down only: the next class boots its kernel lazily inside a test context, which
        // recompiles the container with the rate limiter restored
        KernelLifecycleManager::ensureKernelShutdown();
        self::$kernelBooted = false;
    }

    protected function setUp(): void
    {
        // the compiler pass decides at compile time, so this class needs a freshly compiled kernel.
        // It is booted here and not in setUpBeforeClass(): a deprecation that a kernel boot triggers
        // in a static context has no test case on the call stack and breaks the event system
        if (!self::$kernelBooted) {
            KernelLifecycleManager::bootKernel(true, Uuid::randomHex());
            self::$kernelBooted = true;
        }

        /** @var AdapterInterface $pool */
        $pool = static::getContainer()->get('cache.rate_limiter');
        $pool->clear();
    }

    public function testAFreshCodeIsRefusedOnceTheNumberIsThrottled(): void
    {
        $phone = $this->randomPhone();
        $customer = $this->createCustomer($phone);
        $this->storeCode($phone, self::CODE);

        $browser = $this->createBrowser();

        for ($attempt = 1; $attempt <= self::BUDGET; ++$attempt) {
            static::assertInstanceOf(RedirectResponse::class, $this->requestLogin($browser, $phone, '000000'));
        }

        // a code that is valid and was never answered before does not get through either: the number
        // spent the budget of the limiter, not the budget of a code
        $this->storeCode($phone, self::CODE);
        $response = $this->requestLogin($browser, $phone, self::CODE);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('/account/login', $response->getTargetUrl());
        static::assertNull(
            $this->reloadCustomer($customer->getId())?->getLastLogin(),
            'a throttled caller is not logged in, however good the code is'
        );
    }

    public function testAThrottledCallerReadsTheSameMessageAsForAWrongCode(): void
    {
        $phone = $this->randomPhone();
        $this->createCustomer($phone);
        $this->storeCode($phone, self::CODE);

        $browser = $this->createBrowser();

        for ($attempt = 1; $attempt <= self::BUDGET; ++$attempt) {
            $this->requestLogin($browser, $phone, '000000');
        }

        $this->storeCode($phone, self::CODE);
        $response = $this->requestLogin($browser, $phone, self::CODE);

        static::assertInstanceOf(RedirectResponse::class, $response);

        // the message of a wrong code, not the one of a request for a code: the caller must not learn
        // from the answer that it was throttled, or the shop tells it which numbers exist
        $page = $this->followRedirect($browser, $response);

        static::assertStringContainsString('The verification code is invalid or has expired', $page);
        static::assertStringNotContainsString('Too many requests', $page);
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
        return '+49152' . str_pad((string) random_int(0, 9999999), 7, '0', \STR_PAD_LEFT);
    }

    private function requestLogin(KernelBrowser $browser, string $phone, string $code): Response
    {
        $browser->request('POST', $this->storefrontUrl('/account/login/sms'), [
            'phone' => $phone,
            'code' => $code,
        ]);

        return $browser->getResponse();
    }

    /**
     * Walks the redirect the controller answered, the way the customer's browser does: the message
     * only shows up on the page behind the redirect.
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

    private function storeCode(string $phone, string $code): void
    {
        $normalizer = static::getContainer()->get(PhoneNumberNormalizerInterface::class);
        $service = static::getContainer()->get(SmsLoginService::class);

        $service->storeCode($normalizer->normalize($phone), $code, $this->getSalesChannelId());
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
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'test12345',
            'name' => 'Max Mustermann',
            'phoneNumber' => $phoneNumber,
            'active' => true,
            'salutationId' => $this->getValidSalutationId(),
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
