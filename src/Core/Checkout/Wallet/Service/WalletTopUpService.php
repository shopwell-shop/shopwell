<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
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
use Shopwell\Core\System\Payment\Gateway\Money;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * The top-ups a customer pays online.
 *
 * Two rows are involved and they mean different things: the top-up is what the customer wants - an
 * amount in a currency - and the payment order of the gateway is how it is collected. Keeping them
 * apart is what makes the settlement safe: the payment may be reported more than once, the customer
 * may retry with another channel, and the credit still happens exactly once, decided on the top-up row
 * that is locked while it is credited.
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
     * The marker that tells a paid payment order apart from other payments of the shop.
     */
    final public const ATTACH_PREFIX = 'wallet_top_up:';

    final public const CONFIG_KEY_MAX_TOP_UP_AMOUNT = 'core.wallet.maxTopUpAmount';

    final public const CONFIG_KEY_PRESETS = 'core.wallet.topUpPresets';

    private const DEFAULT_PRESETS = '100,200,500,1000,2000';

    /**
     * @internal
     *
     * @param EntityRepository<CustomerWalletTopUpCollection> $topUpRepository
     */
    public function __construct(
        private readonly EntityRepository $topUpRepository,
        private readonly WalletService $walletService,
        private readonly StorefrontPaymentService $payments,
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * What the storefront may offer: the amounts, the limits and the channels that can really be paid.
     *
     * @return array{min: float, max: float, presets: list<float>, channels: list<array<string, string>>}
     */
    public function options(string $currency, ?string $device): array
    {
        return [
            'min' => $this->systemConfigService->getFloat(WalletService::CONFIG_KEY_MIN_TOP_UP_AMOUNT),
            'max' => $this->systemConfigService->getFloat(self::CONFIG_KEY_MAX_TOP_UP_AMOUNT),
            'presets' => $this->presets(),
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
    ): array {
        $currency = strtoupper($currency);
        $amount = round($amount, Money::exponent($currency));

        $this->assertAmount($amount);

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
                amount: $this->toMinor($amount, $currency),
                currency: $currency,
                subject: 'Wallet top-up',
                attach: self::ATTACH_PREFIX . $id,
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
     * @return list<array<string, mixed>>
     */
    public function recent(string $customerId, int $limit, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);

        $topUps = [];

        foreach ($this->topUpRepository->search($criteria, $context)->getEntities() as $topUp) {
            if (!$topUp instanceof CustomerWalletTopUpEntity) {
                continue;
            }

            $topUps[] = $this->present($topUp);
        }

        return $topUps;
    }

    /**
     * @throws WalletException
     *
     * @return array<string, mixed>
     */
    public function status(string $topUpId, string $customerId, Context $context): array
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

        return $this->present($topUp);
    }

    /**
     * Credits a paid top-up exactly once.
     *
     * The row is locked, so two notifications of the same payment cannot both pass the state check, and
     * the credit is booked in the very same transaction: either the customer has the money and the
     * top-up says it was credited, or neither happened. A payment whose amount does not match the
     * top-up is never credited - it is marked as failed and stays visible for the operator.
     *
     * @param int $paidAmount the amount the channel confirmed, in the smallest unit of the currency
     *
     * @return bool whether this call credited the top-up
     */
    public function settle(string $topUpId, int $paidAmount, string $paymentNo, Context $context): bool
    {
        return (bool) $this->connection->transactional(function () use ($topUpId, $paidAmount, $paymentNo, $context): bool {
            $topUp = $this->lockTopUp($topUpId);

            if ($topUp === null || $topUp['state'] === CustomerWalletTopUpEntity::STATE_CREDITED) {
                return false;
            }

            $now = $this->clock->now();

            if ($this->toMinor($topUp['amount'], $topUp['currency']) !== $paidAmount) {
                $this->topUpRepository->update([[
                    'id' => $topUpId,
                    'state' => CustomerWalletTopUpEntity::STATE_FAILED,
                    'paymentNo' => $paymentNo,
                    'paidAt' => $now,
                ]], $context);

                return false;
            }

            $this->walletService->creditPaidTopUp(
                $topUp['customerId'],
                $topUp['currencyId'],
                $topUp['amount'],
                $topUpId,
                $context
            );

            $this->topUpRepository->update([[
                'id' => $topUpId,
                'state' => CustomerWalletTopUpEntity::STATE_CREDITED,
                'paymentNo' => $paymentNo,
                'paidAt' => $now,
                'creditedAt' => $now,
            ]], $context);

            return true;
        });
    }

    /**
     * Settles a top-up without waiting for a channel, for development and tests.
     *
     * It goes through the very settlement a real payment uses - the ledger, the idempotency and the
     * state of the top-up are exercised, only the notification is skipped.
     *
     * @throws WalletException
     *
     * @return array<string, mixed>
     */
    public function simulate(string $topUpId, string $customerId, Context $context): array
    {
        $status = $this->status($topUpId, $customerId, $context);

        $this->settle(
            $topUpId,
            $this->toMinor((float) $status['amount'], (string) $status['currency']),
            'simulated-' . substr($topUpId, 0, 12),
            $context
        );

        return $this->status($topUpId, $customerId, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CustomerWalletTopUpEntity $topUp): array
    {
        return [
            'id' => $topUp->getId(),
            'amount' => $topUp->getAmount(),
            'currency' => $topUp->getCurrency(),
            'state' => $topUp->getState(),
            'paymentNo' => $topUp->getPaymentNo(),
            'channel' => $topUp->getChannelTechnicalName(),
            'creditedAt' => $topUp->getCreditedAt()?->format(\DATE_ATOM),
            'createdAt' => $topUp->getCreatedAt()?->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array{id: string, customerId: string, currencyId: string, currency: string, amount: float, state: string}|null
     */
    private function lockTopUp(string $topUpId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, LOWER(HEX(`customer_id`)) AS `customer_id`,
                    LOWER(HEX(`currency_id`)) AS `currency_id`, `currency`, `amount`, `state`
             FROM `customer_wallet_top_up`
             WHERE `id` = :id
             FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($topUpId)],
            ['id' => ParameterType::BINARY]
        );

        if (!\is_array($row)) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'customerId' => (string) $row['customer_id'],
            'currencyId' => (string) $row['currency_id'],
            'currency' => strtoupper((string) $row['currency']),
            'amount' => (float) $row['amount'],
            'state' => (string) $row['state'],
        ];
    }

    /**
     * @throws WalletException
     */
    private function assertAmount(float $amount): void
    {
        if ($amount <= 0.0) {
            throw WalletException::invalidAmount($amount);
        }

        $minimum = $this->systemConfigService->getFloat(WalletService::CONFIG_KEY_MIN_TOP_UP_AMOUNT);

        if ($minimum > 0.0 && $amount < $minimum) {
            throw WalletException::belowMinimumTopUp($amount, $minimum);
        }

        $maximum = $this->systemConfigService->getFloat(self::CONFIG_KEY_MAX_TOP_UP_AMOUNT);

        if ($maximum > 0.0 && $amount > $maximum) {
            throw WalletException::topUpAboveMaximum($amount, $maximum);
        }
    }

    /**
     * @return list<float>
     */
    private function presets(): array
    {
        $configured = $this->systemConfigService->getString(self::CONFIG_KEY_PRESETS);

        if (trim($configured) === '') {
            $configured = self::DEFAULT_PRESETS;
        }

        $presets = [];

        foreach (explode(',', $configured) as $value) {
            $value = trim($value);

            if ($value === '' || !is_numeric($value) || (float) $value <= 0.0) {
                continue;
            }

            $presets[] = (float) $value;
        }

        $presets = array_values(array_unique($presets));
        sort($presets);

        return $presets;
    }

    private function toMinor(float $amount, string $currency): int
    {
        return Money::toMinor(number_format($amount, Money::exponent($currency), '.', ''), $currency);
    }
}
