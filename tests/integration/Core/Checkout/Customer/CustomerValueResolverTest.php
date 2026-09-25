<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\CustomerValueResolver;
use Shopwell\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\SalesChannelRequest;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Integration\Traits\CustomerTestTrait;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerValueResolverTest extends TestCase
{
    use CustomerTestTrait;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<CurrencyCollection>
     */
    private EntityRepository $currencyRepository;

    private AccountService $accountService;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->currencyRepository = static::getContainer()->get('currency.repository');

        $this->createTestSalesChannel();

        $this->accountService = static::getContainer()->get(AccountService::class);
        /** @var AbstractSalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    #[DataProvider('loginRequiredAnnotationData')]
    public function testCustomerResolver(bool $loginRequired, bool $context, bool $pass): void
    {
        $resolver = static::getContainer()->get(CustomerValueResolver::class);

        $salesChannelResolver = static::getContainer()->get(SalesChannelRequestContextResolver::class);

        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->attributes->set('_route', 'test-route');

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $this->loginCustomer());

        if ($loginRequired) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED, $loginRequired);
        }

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);

        if ($context) {
            $salesChannelResolver->resolve($request);
        }

        $exception = null;

        try {
            $generator = $resolver->resolve($request, new ArgumentMetadata('', CustomerEntity::class, false, false, ''));
            if ($generator instanceof \Traversable) {
                iterator_to_array($generator);
            }
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($pass) {
            static::assertNull($exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
        } else {
            if (!Feature::isActive('v6.8.0.0')) {
                static::assertInstanceOf(\RuntimeException::class, $exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
            } else {
                static::assertInstanceOf(CustomerException::class, $exception);
            }
        }
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: bool}>
     */
    public static function loginRequiredAnnotationData(): array
    {
        return [
            'Success Case' => [
                true, // loginRequired
                true, // context
                true, // pass
            ],
            'Missing annotation LoginRequired' => [
                false,
                true,
                false,
            ],
            'Missing sales-channel context' => [
                false,
                false,
                false,
            ],
        ];
    }

    private function loginCustomer(): string
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        try {
            return $this->accountService->loginByCredentials($email, 'shopwell', $this->salesChannelContext);
        } catch (BadCredentialsException) {
            // nth
        }

        return '';
    }

    private function getCurrencyId(string $isoCode): ?string
    {
        $currency = $this->currencyRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', $isoCode)),
            Context::createDefaultContext()
        )->getEntities()->first();

        return $currency?->getId();
    }

    private function createTestSalesChannel(): void
    {
        $usdCurrencyId = $this->getCurrencyId('USD');

        $this->createSalesChannel([
            'id' => $this->ids->create('sales-channel'),
            'domains' => [
                [
                    'id' => $this->ids->get('eur-domain'),
                    'url' => 'http://test.store/en-eur',
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                ],
                [
                    'id' => $this->ids->get('usd-domain'),
                    'url' => 'http://test.store/en-usd',
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => $usdCurrencyId,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                ],
            ],
        ]);
    }
}
