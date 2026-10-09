<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Event\SalesChannelContextRestorerOrderCriteriaEvent;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
class SalesChannelContextRestorer
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $factory,
        private readonly CartRuleLoader $cartRuleLoader,
        private readonly OrderConverter $orderConverter,
        private readonly EntityRepository $orderRepository,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, load the order and use `OrderConverter::assembleSalesChannelContext()` instead. Pass the result to `CartRuleLoader::loadByCart()` if the rules have to be re-evaluated.
     *
     * @param array<string, string|array<string,bool>|null> $overrideOptions
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function restoreByOrder(string $orderId, Context $context, array $overrideOptions = []): SalesChannelContext
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', OrderConverter::class . '::assembleSalesChannelContext')
        );

        $order = $this->getOrderById($orderId, $context);
        if ($order === null) {
            throw SalesChannelException::orderNotFound($orderId);
        }

        if ($order->getOrderCustomer() === null) {
            throw SalesChannelException::missingAssociation('orderCustomer');
        }

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context, $overrideOptions);

        $cart = $this->orderConverter->convertToCart($order, $salesChannelContext->getContext());
        $this->cartRuleLoader->loadByCart(
            $salesChannelContext,
            $cart,
            new CartBehavior($salesChannelContext->getPermissions()),
            true
        );

        return $salesChannelContext;
    }

    /**
     * @param array<string> $overrideOptions
     *
     * @throws Exception
     */
    public function restoreByCustomer(string $customerId, Context $context, array $overrideOptions = []): SalesChannelContext
    {
        $customer = $this->connection->createQueryBuilder()
            ->select(
                'LOWER(HEX(language_id))',
                'LOWER(HEX(customer_group_id))',
                'LOWER(HEX(sales_channel_id))',
            )
            ->from('customer')
            ->where('id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($customerId))
            ->executeQuery()
            ->fetchAssociative();

        if (!$customer) {
            throw SalesChannelException::customerNotFoundByIdException($customerId);
        }

        [$languageId, $groupId, $salesChannelId] = array_values($customer);
        $options = [
            SalesChannelContextService::LANGUAGE_ID => $languageId,
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::CUSTOMER_GROUP_ID => $groupId,
            SalesChannelContextService::VERSION_ID => $context->getVersionId(),
        ];

        $options = array_merge($options, $overrideOptions);

        $token = Uuid::randomHex();
        $salesChannelContext = $this->factory->create(
            $token,
            $salesChannelId,
            $options
        );

        $this->cartRuleLoader->loadByToken($salesChannelContext, $token);
        $salesChannelContext->getContext()->addState(...$context->getStates());

        return $salesChannelContext;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getOrderById(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('primaryOrderTransaction')
            ->addAssociation('primaryOrderDelivery')
            ->addAssociation('lineItems')
            ->addAssociation('currency')
            ->addAssociation('deliveries')
            ->addAssociation('language.locale')
            ->addAssociation('orderCustomer.customer')
            ->addAssociation('billingAddress')
            ->addAssociation('transactions.stateMachineState');

        $event = Feature::silent(
            'v6.8.0.0',
            static fn (): SalesChannelContextRestorerOrderCriteriaEvent => new SalesChannelContextRestorerOrderCriteriaEvent($criteria, $context),
        );
        $this->eventDispatcher->dispatch($event);

        return $this->orderRepository->search($criteria, $context)->getEntities()->get($orderId);
    }
}
