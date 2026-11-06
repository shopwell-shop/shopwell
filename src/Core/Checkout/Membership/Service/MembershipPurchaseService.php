<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Service;

use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseEntity;
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
use Shopwell\Core\System\Currency\CurrencyAmountConverter;
use Shopwell\Core\System\Payment\Routing\PaymentContext;

/**
 * The memberships a customer buys.
 *
 * A purchase has two rows with different meanings: the purchase is what the customer bought - a tier, a
 * duration and a price that were copied from the plan - and the payment order of the gateway is how it
 * is collected. The purchase is activated exactly once, keyed on its own row, see
 * {@see \Shopwell\Core\Checkout\Membership\Purchase\MembershipPurchaseStore}.
 *
 * @final
 */
#[Package('checkout')]
class MembershipPurchaseService
{
    /**
     * @internal
     *
     * @param EntityRepository<MembershipPurchaseCollection> $purchaseRepository
     */
    public function __construct(
        private readonly EntityRepository $purchaseRepository,
        private readonly MembershipPlanService $plans,
        private readonly StorefrontPaymentService $payments,
        private readonly PurchaseAttach $attach,
    ) {
    }

    /**
     * Writes the purchase and opens the payment for it.
     *
     * @throws MembershipException
     *
     * @return array{id: string, amount: float, currency: string, payment_no: string, channel: string, params: array<string, mixed>}
     */
    public function purchase(
        string $customerId,
        string $planId,
        string $method,
        string $currencyId,
        PaymentContext $paymentContext,
        ?string $returnUrl,
        ?string $clientIp,
        Context $context,
    ): array {
        $plan = $this->plans->findPurchasable($planId, $currencyId, $context);

        $currency = strtoupper($paymentContext->currency);
        $this->payments->resolveMethod($method, $paymentContext);
        $minorAmount = CurrencyAmountConverter::fromFloat($plan->getPrice(), $currency);
        $amount = CurrencyAmountConverter::toFloat($minorAmount, $currency);
        $id = Uuid::randomHex();

        $this->purchaseRepository->create([[
            'id' => $id,
            'customerId' => $customerId,
            'membershipPlanId' => $plan->getId(),
            'loyaltyTierId' => $plan->getLoyaltyTierId(),
            'planName' => $plan->getName(),
            'amount' => $amount,
            'currencyId' => $currencyId,
            'currency' => $currency,
            'durationDays' => $plan->getDurationDays(),
            'state' => MembershipPurchaseEntity::STATE_OPEN,
        ]], $context);

        try {
            $payment = $this->payments->open(
                outTradeNo: $id,
                method: $method,
                amount: $minorAmount,
                subject: $plan->getName() ?? 'Membership',
                attach: $this->attach->forPurchase($id),
                returnUrl: $returnUrl,
                clientIp: $clientIp,
                paymentContext: $paymentContext,
            );
        } catch (\Throwable $exception) {
            // the attempt stays visible instead of disappearing silently
            $this->purchaseRepository->update([[
                'id' => $id,
                'state' => MembershipPurchaseEntity::STATE_FAILED,
            ]], $context);

            throw $exception;
        }

        $this->purchaseRepository->update([[
            'id' => $id,
            'paymentNo' => $payment['payment_no'],
            'channelTechnicalName' => $payment['channel'],
        ]], $context);

        return ['id' => $id, 'amount' => $amount, 'currency' => $currency] + $payment;
    }

    /**
     * The last memberships a customer bought, newest first.
     *
     * @return list<MembershipPurchaseEntity>
     */
    public function recentPurchases(string $customerId, int $limit, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);

        $purchases = [];

        foreach ($this->purchaseRepository->search($criteria, $context)->getEntities() as $purchase) {
            if ($purchase instanceof MembershipPurchaseEntity) {
                $purchases[] = $purchase;
            }
        }

        return $purchases;
    }
}
