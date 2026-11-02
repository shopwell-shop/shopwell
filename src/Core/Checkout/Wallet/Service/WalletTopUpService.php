<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Service;

use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpEntity;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Gateway\Request\Money;

/**
 * The top-ups a customer pays online.
 *
 * Two rows are involved and they mean different things: the top-up is what the customer wants - an
 * amount in a currency - and the payment order of the gateway is how it is collected. Keeping them
 * apart is what makes the settlement safe: the payment may be reported more than once, the customer
 * may retry with another channel, and the credit still happens exactly once, decided on the top-up row
 * that is locked while it is credited - see {@see \Shopwell\Core\Checkout\Wallet\Purchase\WalletTopUpPurchaseStore}.
 *
 * The amount is always converted with the exponent of the currency, never with a fixed 100: a shop that
 * sells in JPY would otherwise charge the payer a hundredth of what it meant to.
 *
 * @final
 */
#[Package('checkout')]
class WalletTopUpService
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerWalletTopUpCollection> $topUpRepository
     */
    public function __construct(
        private readonly EntityRepository $topUpRepository,
        private readonly StorefrontPaymentService $payments,
        private readonly WalletSettings $settings,
        private readonly PurchaseAttach $attach,
    ) {
    }

    /**
     * What the storefront may offer: the amounts, the limits and the channels that can really be paid.
     *
     * @return array{min: float, max: float, presets: list<float>, channels: list<array<string, string>>}
     */
    public function options(string $currency, ?string $device, ?string $salesChannelId = null): array
    {
        return [
            'min' => $this->settings->minTopUpAmount($salesChannelId),
            'max' => $this->settings->maxTopUpAmount($salesChannelId),
            'presets' => $this->settings->topUpPresets($salesChannelId),
            'channels' => $this->payments->channels($currency, $device),
        ];
    }

    /**
     * Creates the top-up and opens the payment for it.
     *
     * @throws WalletException
     *
     * @return array{id: string, amount: float, currency: string, payment_no: string, channel: string, params: array<string, mixed>}
     */
    public function create(
        string $customerId,
        string $currencyId,
        string $currency,
        float $amount,
        string $channel,
        ?string $returnUrl,
        ?string $clientIp,
        ?string $device,
        Context $context,
        ?string $salesChannelId = null,
    ): array {
        if (!$this->settings->isEnabled($salesChannelId)) {
            throw WalletException::walletDisabled();
        }

        $currency = strtoupper($currency);
        $amount = round($amount, Money::exponent($currency));

        $this->assertAmount($amount, $salesChannelId);

        $id = Uuid::randomHex();

        $this->topUpRepository->create([[
            'id' => $id,
            'customerId' => $customerId,
            'currencyId' => $currencyId,
            'currency' => $currency,
            'amount' => $amount,
            'state' => CustomerWalletTopUpEntity::STATE_OPEN,
            'channelTechnicalName' => $channel,
        ]], $context);

        try {
            $payment = $this->payments->open(
                outTradeNo: $id,
                channel: $channel,
                amount: Money::fromFloat($amount, $currency),
                currency: $currency,
                subject: 'Wallet top-up',
                attach: $this->attach->forPurchase($id),
                returnUrl: $returnUrl,
                clientIp: $clientIp,
                device: $device,
            );
        } catch (\Throwable $exception) {
            // the attempt stays visible in the administration instead of disappearing silently
            $this->topUpRepository->update([[
                'id' => $id,
                'state' => CustomerWalletTopUpEntity::STATE_FAILED,
            ]], $context);

            throw $exception;
        }

        $this->topUpRepository->update([[
            'id' => $id,
            'paymentNo' => $payment['payment_no'],
            'channelTechnicalName' => $channel,
        ]], $context);

        return ['id' => $id, 'amount' => $amount, 'currency' => $currency] + $payment;
    }

    /**
     * The last top-ups of a customer, newest first.
     *
     * The account page shows them next to the ledger, so a payment that is still open - because the
     * payer closed the channel, or the notification has not arrived yet - stays visible instead of
     * looking like it never happened.
     *
     * @return list<CustomerWalletTopUpEntity>
     */
    public function recentTopUps(string $customerId, int $limit, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);

        $topUps = [];

        foreach ($this->topUpRepository->search($criteria, $context)->getEntities() as $topUp) {
            if ($topUp instanceof CustomerWalletTopUpEntity) {
                $topUps[] = $topUp;
            }
        }

        return $topUps;
    }

    /**
     * @throws WalletException
     */
    public function find(string $topUpId, string $customerId, Context $context): CustomerWalletTopUpEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('id', $topUpId),
            new EqualsFilter('customerId', $customerId)
        );
        $criteria->setLimit(1);

        $topUp = $this->topUpRepository->search($criteria, $context)->getEntities()->first();

        if (!$topUp instanceof CustomerWalletTopUpEntity) {
            throw WalletException::topUpNotFound($topUpId);
        }

        return $topUp;
    }

    /**
     * @throws WalletException
     */
    private function assertAmount(float $amount, ?string $salesChannelId): void
    {
        if ($amount <= 0.0) {
            throw WalletException::invalidAmount($amount);
        }

        $minimum = $this->settings->minTopUpAmount($salesChannelId);

        if ($minimum > 0.0 && $amount < $minimum) {
            throw WalletException::belowMinimumTopUp($amount, $minimum);
        }

        $maximum = $this->settings->maxTopUpAmount($salesChannelId);

        if ($maximum > 0.0 && $amount > $maximum) {
            throw WalletException::topUpAboveMaximum($amount, $maximum);
        }
    }
}
