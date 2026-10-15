<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Wallet\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class WalletRouteTest extends TestCase
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
        $this->browser->request('GET', '/store-api/wallet');

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
    }

    public function testReturnsWalletsAndTransactions(): void
    {
        $customerId = $this->login($this->browser);

        $context = Context::createDefaultContext();
        $repository = static::getContainer()->get('customer_wallet.repository');

        // the wallet is opened with a zero balance when the customer is written, so the test only
        // tops it up instead of opening a second wallet that the unique key would reject
        $walletId = $repository->searchIds($this->criteriaFor($customerId), $context)->firstId();
        static::assertNotNull($walletId);

        $repository->update([
            [
                'id' => $walletId,
                'balance' => 50.0,
            ],
        ], $context);

        static::getContainer()->get('customer_wallet_transaction.repository')->create([
            [
                'customerWalletId' => $walletId,
                'type' => WalletService::TRANSACTION_TYPE_TOP_UP,
                'amount' => 50.0,
                'balanceAfter' => 50.0,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $context);

        $this->browser->request('GET', '/store-api/wallet');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $response['wallets']);
        static::assertSame(Defaults::CURRENCY, $response['wallets'][0]['currencyId']);
        static::assertEquals(50.0, $response['wallets'][0]['balance']);
        static::assertCount(1, $response['transactions']);
        static::assertSame(WalletService::TRANSACTION_TYPE_TOP_UP, $response['transactions'][0]['type']);
        static::assertEquals(50.0, $response['transactions'][0]['amount']);
    }

    private function criteriaFor(string $customerId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customerId', $customerId),
            new EqualsFilter('currencyId', Defaults::CURRENCY)
        );
        $criteria->setLimit(1);

        return $criteria;
    }
}
