<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipOrder\MembershipOrderCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipOrder\MembershipOrderEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\MembershipPlanCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Gateway\Money;

/**
 * The memberships a customer buys.
 *
 * A purchase has two rows with different meanings: the order is what the customer bought - a tier, a
 * duration and a price that were copied from the plan - and the payment order of the gateway is how it
 * is collected. The order is activated exactly once, keyed on its own row, so a payment that is
 * reported twice cannot extend a membership twice.
 *
 * The plan is only the offer. Prices, names and durations are copied when the order is written, which
 * keeps the history readable after a plan was edited or removed.
 *
 * @final
 */
#[Package('checkout')]
class MembershipService
{
    /**
     * The marker that tells a paid payment order apart from other payments of the shop.
     */
    final public const ATTACH_PREFIX = 'membership_order:';

    /**
     * @internal
     *
     * @param EntityRepository<MembershipPlanCollection> $planRepository
     * @param EntityRepository<MembershipOrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $planRepository,
        private readonly EntityRepository $orderRepository,
        private readonly LoyaltyLedger $loyaltyLedger,
        private readonly StorefrontPaymentService $payments,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * The plans a customer of this currency can buy, cheapest first.
     *
     * @return list<array<string, mixed>>
     */
    public function plans(string $currencyId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true),
            new EqualsFilter('currencyId', $currencyId)
        );
        $criteria->addAssociation('loyaltyTier');
        $criteria->addSorting(new FieldSorting('price', FieldSorting::ASCENDING));

        $plans = [];

        foreach ($this->planRepository->search($criteria, $context)->getEntities() as $plan) {
            if ($plan instanceof MembershipPlanEntity) {
                $plans[] = $this->presentPlan($plan);
            }
        }

        return $plans;
    }

    /**
     * The channels a membership can be paid with.
     *
     * A membership is collected by a payment like everything else the shop sells, so the offer is the
     * same one the recharge uses: a channel the platform cannot collect this currency with is not
     * offered at all, and a channel the shop did not open is not filtered out here but rejected by the
     * gateway, which is the only place that knows for sure.
     *
     * @return list<array{channel: string, label: string, provider: string, provider_label: string, flow: string}>
     */
    public function channels(string $currency, ?string $device): array
    {
        return $this->payments->channels($currency, $device);
    }

    /**
     * Writes the order and opens the payment for it.
     *
     * @throws LoyaltyException
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
        $plan = $this->loadPurchasablePlan($planId, $currencyId, $context);

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
                amount: $this->toMinor($amount, $currency),
                currency: $currency,
                subject: $plan->getName() ?? 'Membership',
                attach: self::ATTACH_PREFIX . $id,
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
     * @return list<array<string, mixed>>
     */
    public function orders(string $customerId, int $limit, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);

        $orders = [];

        foreach ($this->orderRepository->search($criteria, $context)->getEntities() as $order) {
            if ($order instanceof MembershipOrderEntity) {
                $orders[] = $this->presentOrder($order);
            }
        }

        return $orders;
    }

    /**
     * Activates a paid membership exactly once.
     *
     * The order row is locked, so two notifications of the same payment cannot both pass the state
     * check, and the membership is written in the very same transaction: either the customer has the
     * tier and the order says it was activated, or neither happened. A payment whose amount does not
     * match the order is never activated - it is marked as failed and stays visible for the operator.
     *
     * @param int $paidAmount the amount the channel confirmed, in the smallest unit of the currency
     *
     * @return bool whether this call activated the order
     */
    public function settle(string $orderId, int $paidAmount, string $paymentNo, Context $context): bool
    {
        return (bool) $this->connection->transactional(function () use ($orderId, $paidAmount, $paymentNo, $context): bool {
            $order = $this->lockOrder($orderId);

            if ($order === null || $order['state'] === MembershipOrderEntity::STATE_ACTIVATED) {
                return false;
            }

            $now = $this->clock->now();

            // a tier that was removed while the payment was open cannot be granted, and an amount the
            // customer did not agree to must never buy a membership
            if ($order['loyaltyTierId'] === null || $this->toMinor($order['amount'], $order['currency']) !== $paidAmount) {
                $this->markFailed($orderId, $paymentNo, $now, $context);

                return false;
            }

            $period = $this->loyaltyLedger->activateMembership(
                $order['customerId'],
                $order['loyaltyTierId'],
                $order['durationDays'],
                $context
            );

            $this->orderRepository->update([[
                'id' => $orderId,
                'state' => MembershipOrderEntity::STATE_ACTIVATED,
                'paymentNo' => $paymentNo,
                'paidAt' => $now,
                'activatedAt' => $now,
                'startsAt' => $period['startsAt'],
                'expiresAt' => $period['expiresAt'],
            ]], $context);

            return true;
        });
    }

    /**
     * @throws LoyaltyException
     */
    private function loadPurchasablePlan(string $planId, string $currencyId, Context $context): MembershipPlanEntity
    {
        $plan = $this->planRepository->search(new Criteria([$planId]), $context)->getEntities()->first();

        if (!$plan instanceof MembershipPlanEntity) {
            throw LoyaltyException::planNotFound($planId);
        }

        if (!$plan->isActive() || $plan->getCurrencyId() !== $currencyId) {
            throw LoyaltyException::planNotPurchasable($planId);
        }

        return $plan;
    }

    private function markFailed(string $orderId, string $paymentNo, \DateTimeInterface $now, Context $context): void
    {
        $this->orderRepository->update([[
            'id' => $orderId,
            'state' => MembershipOrderEntity::STATE_FAILED,
            'paymentNo' => $paymentNo,
            'paidAt' => $now,
        ]], $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPlan(MembershipPlanEntity $plan): array
    {
        $tier = $plan->getLoyaltyTier();

        return [
            'id' => $plan->getId(),
            'technicalName' => $plan->getTechnicalName(),
            'name' => $plan->getName(),
            'description' => $plan->getDescription(),
            'price' => $plan->getPrice(),
            'durationDays' => $plan->getDurationDays(),
            'currencyId' => $plan->getCurrencyId(),
            'tier' => $tier instanceof LoyaltyTierEntity ? [
                'id' => $tier->getId(),
                'name' => $tier->getName(),
                'level' => $tier->getLevel(),
                'pointsMultiplier' => $tier->getPointsMultiplier(),
                'discountPercent' => $tier->getDiscountPercent(),
                'freeShipping' => $tier->getFreeShipping(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOrder(MembershipOrderEntity $order): array
    {
        return [
            'id' => $order->getId(),
            'planName' => $order->getPlanName(),
            'amount' => $order->getAmount(),
            'currency' => $order->getCurrency(),
            'durationDays' => $order->getDurationDays(),
            'state' => $order->getState(),
            'startsAt' => $order->getStartsAt()?->format(\DATE_ATOM),
            'expiresAt' => $order->getExpiresAt()?->format(\DATE_ATOM),
            'createdAt' => $order->getCreatedAt()?->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array{id: string, customerId: string, loyaltyTierId: ?string, durationDays: int, amount: float, currency: string, state: string}|null
     */
    private function lockOrder(string $orderId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, LOWER(HEX(`customer_id`)) AS `customer_id`,
                    LOWER(HEX(`loyalty_tier_id`)) AS `loyalty_tier_id`, `duration_days`, `amount`, `currency`, `state`
             FROM `membership_order`
             WHERE `id` = :id
             FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($orderId)],
            ['id' => ParameterType::BINARY]
        );

        if (!\is_array($row)) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'customerId' => (string) $row['customer_id'],
            'loyaltyTierId' => $row['loyalty_tier_id'] === null ? null : (string) $row['loyalty_tier_id'],
            'durationDays' => (int) $row['duration_days'],
            'amount' => (float) $row['amount'],
            'currency' => strtoupper((string) $row['currency']),
            'state' => (string) $row['state'],
        ];
    }

    private function toMinor(float $amount, string $currency): int
    {
        return Money::toMinor(number_format($amount, Money::exponent($currency), '.', ''), $currency);
    }
}
