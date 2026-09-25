<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopwell\Core\Checkout\Order\Exception\PaymentMethodNotAvailableException;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderException;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\State;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\BuildValidationEvent;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\StateMachineException;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('checkout')]
class OrderService
{
    final public const CUSTOMER_COMMENT_KEY = 'customerComment';
    final public const AFFILIATE_CODE_KEY = 'affiliateCode';
    final public const CAMPAIGN_CODE_KEY = 'campaignCode';

    final public const ALLOWED_TRANSACTION_STATES = [
        OrderTransactionStates::STATE_OPEN,
        OrderTransactionStates::STATE_CANCELLED,
        OrderTransactionStates::STATE_REMINDED,
        OrderTransactionStates::STATE_FAILED,
        OrderTransactionStates::STATE_CHARGEBACK,
        OrderTransactionStates::STATE_UNCONFIRMED,
    ];

    /**
     * @internal
     *
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private readonly DataValidator $dataValidator,
        private readonly DataValidationFactoryInterface $orderValidationFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartService $cartService,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly StateMachineRegistry $stateMachineRegistry,
    ) {
    }

    /**
     * @throws ConstraintViolationException
     */
    public function createOrder(DataBag $data, SalesChannelContext $context): string
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        $isDownloadLineItem = $cart->getLineItems()->hasLineItemWithProductType(ProductDefinition::TYPE_DIGITAL);

        if (!Feature::isActive('v6.8.0.0')) {
            Feature::callSilentIfInactive('v6.8.0.0', static function () use ($cart, &$isDownloadLineItem): void {
                $isDownloadLineItem = $isDownloadLineItem || $cart->getLineItems()->hasLineItemWithState(State::IS_DOWNLOAD);
            });
        }

        $this->validateOrderData($data, $context, $isDownloadLineItem);

        $this->validateCart($cart, $context->getContext());

        return $this->cartService->order($cart, $context, $data->toRequestDataBag());
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderStateTransition(
        string $orderId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): StateMachineStateEntity {
        $stateFieldName = $data->getString('stateFieldName', 'stateId');
        $internalComment = $data->getString('internalComment') ?: null;

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order',
                $orderId,
                $transition,
                $stateFieldName,
                $internalComment,
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');

        if (!$toPlace) {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                throw StateMachineException::stateMachineStateNotFound('order', $transition); // @phpstan-ignore shopwell.domainException
            }
            throw OrderException::stateMachineStateNotFound('order', $transition);
        }

        return $toPlace;
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderTransactionStateTransition(
        string $orderTransactionId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): StateMachineStateEntity {
        $stateFieldName = $data->getString('stateFieldName', 'stateId');
        $internalComment = $data->getString('internalComment') ?: null;

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order_transaction',
                $orderTransactionId,
                $transition,
                $stateFieldName,
                $internalComment,
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');

        if (!$toPlace) {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                throw StateMachineException::stateMachineStateNotFound('order_transaction', $transition); // @phpstan-ignore shopwell.domainException
            }
            throw OrderException::stateMachineStateNotFound('order_transaction', $transition);
        }

        return $toPlace;
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderDeliveryStateTransition(
        string $orderDeliveryId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): StateMachineStateEntity {
        $stateFieldName = $data->getString('stateFieldName', 'stateId');
        $internalComment = $data->getString('internalComment') ?: null;

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order_delivery',
                $orderDeliveryId,
                $transition,
                $stateFieldName,
                $internalComment,
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');

        if (!$toPlace) {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                throw StateMachineException::stateMachineStateNotFound('order_delivery', $transition); // @phpstan-ignore shopwell.domainException
            }
            throw OrderException::stateMachineStateNotFound('order_delivery', $transition);
        }

        return $toPlace;
    }

    public function isPaymentChangeableByTransactionState(OrderEntity $order): bool
    {
        $state = $order->getPrimaryOrderTransaction()?->getStateMachineState()?->getTechnicalName();

        if (!Feature::isActive('v6.8.0.0')) {
            $state = $order->getTransactions()?->last()?->getStateMachineState()?->getTechnicalName();
        }

        if (!$state) {
            return true;
        }

        return \in_array($state, self::ALLOWED_TRANSACTION_STATES, true);
    }

    private function validateCart(Cart $cart, Context $context): void
    {
        $idsOfPaymentMethods = [];

        foreach ($cart->getTransactions() as $paymentMethod) {
            $idsOfPaymentMethods[] = $paymentMethod->getPaymentMethodId();
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true)
        );

        $paymentMethods = $this->paymentMethodRepository->searchIds($criteria, $context);

        if ($paymentMethods->getTotal() !== \count(array_unique($idsOfPaymentMethods))) {
            foreach ($cart->getTransactions() as $paymentMethod) {
                if (!\in_array($paymentMethod->getPaymentMethodId(), $paymentMethods->getIds(), true)) {
                    // @deprecated tag:v6.8.0 - remove this if block
                    if (!Feature::isActive('v6.8.0.0')) {
                        throw new PaymentMethodNotAvailableException($paymentMethod->getPaymentMethodId()); // @phpstan-ignore shopwell.domainException
                    }
                    throw OrderException::paymentMethodNotAvailable($paymentMethod->getPaymentMethodId());
                }
            }
        }
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateOrderData(
        ParameterBag $data,
        SalesChannelContext $context,
        bool $hasVirtualGoods
    ): void {
        $definition = $this->getOrderCreateValidationDefinition(new DataBag($data->all()), $context, $hasVirtualGoods);
        $violations = $this->dataValidator->getViolations($data->all(), $definition);

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $data->all());
        }
    }

    private function getOrderCreateValidationDefinition(
        DataBag $data,
        SalesChannelContext $context,
        bool $hasVirtualGoods
    ): DataValidationDefinition {
        $validation = $this->orderValidationFactory->create($context);

        if ($hasVirtualGoods) {
            $validation->add('revocation', new NotBlank());
        }

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }
}
