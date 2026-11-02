<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Service;

use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderEntity;
use Shopwell\Core\Checkout\Membership\MembershipException;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Gateway\Request\Money;

/**
 * The memberships a customer buys.
 *
 * A purchase has two rows with different meanings: the order is what the customer bought - a tier, a
 * duration and a price that were copied from the plan - and the payment order of the gateway is how it
 * is collected. The order is activated exactly once, keyed on its own row, see
 * {@see \Shopwell\Core\Checkout\Membership\Purchase\MembershipOrderPurchaseStore}.
 *
 * @final
 */
#[Package('checkout')]
class MembershipPurchaseService
{
    /**
     * @internal
     *
     * @param EntityRepository<MembershipOrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly MembershipPlanService $plans,
        private readonly StorefrontPaymentService $payments,
        private readonly PurchaseAttach $attach,
    ) {
    }

    /**
     * Writes the order and opens the payment for it.
     *
     * @throws MembershipException
     *
     * @return array{id: string, amount: float, currency: string, payment_no: string, channel: string, params: array<string, mixed>}
     */
    public function purchase(
        string $customerId,
        string $planId,
        string $channel,
        string $currencyId,
        string $currency,
        ?string $returnUrl,
        ?string $clientIp,
        ?string $device,
        Context $context,
    ): array {
        $plan = $this->plans->findPurchasable($planId, $currencyId, $context);

        $currency = strtoupper($currency);
        $amount = round($plan->getPrice(), Money::exponent($currency));
        $id = Uuid::randomHex();

        $this->orderRepository->create([[
            'id' => $id,
            'customerId' => $customerId,
            'membershipPlanId' => $plan->getId(),
            'loyaltyTierId' => $plan->getLoyaltyTierId(),
            'planName' => $plan->getName(),
            'amount' => $amount,
            'currencyId' => $currencyId,
            'currency' => $currency,
            'durationDays' => $plan->getDurationDays(),
            'state' => MembershipOrderEntity::STATE_OPEN,
        ]], $context);

        try {
            $payment = $this->payments->open(
                outTradeNo: $id,
                channel: $channel,
                amount: Money::fromFloat($amount, $currency),
                currency: $currency,
                subject: $plan->getName() ?? 'Membership',
                attach: $this->attach->forPurchase($id),
                returnUrl: $returnUrl,
                clientIp: $clientIp,
                device: $device,
            );
        } catch (\Throwable $exception) {
            // the attempt stays visible instead of disappearing silently
            $this->orderRepository->update([[
                'id' => $id,
                'state' => MembershipOrderEntity::STATE_FAILED,
            ]], $context);

            throw $exception;
        }

        $this->orderRepository->update([[
            'id' => $id,
            'paymentNo' => $payment['payment_no'],
            'channelTechnicalName' => $payment['channel'],
        ]], $context);

        return ['id' => $id, 'amount' => $amount, 'currency' => $currency] + $payment;
    }

    /**
     * The last memberships a customer bought, newest first.
     *
     * @return list<MembershipOrderEntity>
     */
    public function recentOrders(string $customerId, int $limit, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);

        $orders = [];

        foreach ($this->orderRepository->search($criteria, $context)->getEntities() as $order) {
            if ($order instanceof MembershipOrderEntity) {
                $orders[] = $order;
            }
        }

        return $orders;
    }
}
