<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionEntity;
use Shopwell\Core\Checkout\Wallet\Extension\WalletRouteExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class WalletRoute
{
    private const DEFAULT_LIMIT = 25;

    private const MAX_LIMIT = 100;

    /**
     * @internal
     *
     * @param EntityRepository<CustomerWalletCollection> $walletRepository
     * @param EntityRepository<CustomerWalletTransactionCollection> $walletTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $walletRepository,
        private readonly EntityRepository $walletTransactionRepository,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/wallet',
        name: 'store-api.wallet',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_GET]
    )]
    public function load(Request $request, SalesChannelContext $context, CustomerEntity $customer): WalletRouteResponse
    {
        return $this->extensions->publish(
            name: WalletRouteExtension::NAME,
            extension: new WalletRouteExtension($request, $context, $customer),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, SalesChannelContext $context, CustomerEntity $customer): WalletRouteResponse
    {
        $wallets = $this->loadWallets($customer->getId(), $context);

        $walletIds = array_map(static fn (array $wallet): string => (string) $wallet['id'], $wallets);

        /** @var array<string, mixed> $data */
        $data = [
            'wallets' => array_map(
                static fn (array $wallet): array => [
                    'currencyId' => $wallet['currencyId'],
                    'balance' => $wallet['balance'],
                    'lockedBalance' => $wallet['lockedBalance'],
                ],
                $wallets
            ),
            'transactions' => $this->loadTransactions($walletIds, $request, $context),
        ];

        return new WalletRouteResponse(new ArrayStruct($data));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadWallets(string $customerId, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        $wallets = [];

        foreach ($this->walletRepository->search($criteria, $context->getContext())->getEntities() as $wallet) {
            if (!$wallet instanceof CustomerWalletEntity) {
                continue;
            }

            $wallets[] = [
                'id' => $wallet->getId(),
                'currencyId' => $wallet->getCurrencyId(),
                'balance' => $wallet->getBalance(),
                'lockedBalance' => $wallet->getLockedBalance(),
            ];
        }

        return $wallets;
    }

    /**
     * @param list<string> $walletIds
     *
     * @return list<array<string, mixed>>
     */
    private function loadTransactions(array $walletIds, Request $request, SalesChannelContext $context): array
    {
        if ($walletIds === []) {
            return [];
        }

        $limit = min(max($request->query->getInt('limit', self::DEFAULT_LIMIT), 1), self::MAX_LIMIT);
        $page = max($request->query->getInt('page', 1), 1);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('customerWalletId', $walletIds));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);
        $criteria->setOffset(($page - 1) * $limit);

        $transactions = [];

        foreach ($this->walletTransactionRepository->search($criteria, $context->getContext())->getEntities() as $transaction) {
            if (!$transaction instanceof CustomerWalletTransactionEntity) {
                continue;
            }

            $transactions[] = [
                'id' => $transaction->getId(),
                'type' => $transaction->getType(),
                'amount' => $transaction->getAmount(),
                'balanceAfter' => $transaction->getBalanceAfter(),
                'currencyId' => $transaction->getCurrencyId(),
                'orderId' => $transaction->getOrderId(),
                'externalReference' => $transaction->getExternalReference(),
                'comment' => $transaction->getComment(),
                'createdAt' => $transaction->getCreatedAt()?->format(\DATE_ATOM),
            ];
        }

        return $transactions;
    }
}
