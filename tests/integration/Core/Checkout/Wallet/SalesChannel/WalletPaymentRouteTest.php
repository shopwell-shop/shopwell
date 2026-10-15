<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Wallet\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class WalletPaymentRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testRequiresLogin(): void
    {
        $this->browser->request('POST', '/store-api/wallet/payment', ['amount' => 10]);

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->login($this->browser);

        $this->browser->request('POST', '/store-api/wallet/payment', ['amount' => -5]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame(WalletException::WALLET_INVALID_AMOUNT_CODE, $response['errors'][0]['code']);
    }

    public function testRejectsAmountAboveBalance(): void
    {
        $customerId = $this->login($this->browser);

        $this->setBalance($customerId, 10.0);

        $this->browser->request('POST', '/store-api/wallet/payment', ['amount' => 100]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame(WalletException::WALLET_INSUFFICIENT_BALANCE_CODE, $response['errors'][0]['code']);
    }

    public function testAcceptsAmountWithinBalance(): void
    {
        $customerId = $this->login($this->browser);

        $this->setBalance($customerId, 100.0);

        $this->browser->request('POST', '/store-api/wallet/payment', ['amount' => 50]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('price', $response);
    }

    public function testZeroAmountClearsThePayment(): void
    {
        $this->login($this->browser);

        $this->browser->request('POST', '/store-api/wallet/payment', ['amount' => 0]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('price', $response);
    }

    /**
     * The wallet of a customer is opened with a zero balance when the customer is written. The test
     * tops it up to the amount it wants to work with instead of opening a second wallet, which the
     * unique key on customer and currency would reject.
     */
    private function setBalance(string $customerId, float $balance): void
    {
        $repository = static::getContainer()->get('customer_wallet.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customerId', $customerId),
            new EqualsFilter('currencyId', Defaults::CURRENCY)
        );
        $criteria->setLimit(1);

        $walletId = $repository->searchIds($criteria, $context)->firstId();

        if ($walletId !== null) {
            $repository->update([['id' => $walletId, 'balance' => $balance]], $context);

            return;
        }

        $repository->create([[
            'id' => Uuid::randomHex(),
            'customerId' => $customerId,
            'currencyId' => Defaults::CURRENCY,
            'balance' => $balance,
        ]], $context);
    }
}
