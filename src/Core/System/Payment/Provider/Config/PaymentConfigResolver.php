<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Config;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;

/**
 * Resolves explicit merchant grants for admission and bound accounts for historical execution.
 *
 * @internal
 *
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Account\PaymentAccountResolutionTest
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentConfigResolver
{
    /**
     * @param EntityRepository<PaymentAppAccountCollection> $appAccountRepository
     * @param EntityRepository<PaymentAppChannelCollection> $appChannelRepository
     * @param EntityRepository<PaymentAccountCollection> $accountRepository
     */
    public function __construct(
        private readonly PaymentProviderRegistry $providerRegistry,
        private readonly PaymentProductRegistry $channelRegistry,
        private readonly EntityRepository $appAccountRepository,
        private readonly EntityRepository $appChannelRepository,
        private readonly PaymentCredentialCipher $cipher,
        private readonly EntityRepository $accountRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $runtimeParams
     */
    public function resolve(PaymentAppEntity $app, string $channelTechnicalName, string $currency, ?string $device = null, array $runtimeParams = [], ?string $accountId = null): ResolvedPaymentConfig
    {
        $candidates = $this->resolveCandidates($app, $channelTechnicalName, $currency, $device, $runtimeParams, $accountId === null ? [] : [$accountId => [$channelTechnicalName => $runtimeParams]]);
        if ($accountId !== null) {
            foreach ($candidates as $candidate) {
                if ($candidate->accountId === $accountId) {
                    return $candidate;
                }
            }

            throw PaymentProviderException::accountNotAuthorized($accountId, $app->technicalName);
        }
        if (\count($candidates) !== 1) {
            throw PaymentProviderException::accountSelectionRequired($channelTechnicalName);
        }

        return $candidates[0];
    }

    /**
     * @param array<string, mixed> $runtimeParams
     * @param array<string, array<string, array<string, mixed>>> $accountRuntimeParams
     *
     * @return list<ResolvedPaymentConfig>
     */
    public function resolveCandidates(PaymentAppEntity $app, string $productName, string $currency, ?string $device, array $runtimeParams = [], array $accountRuntimeParams = []): array
    {
        $product = $this->channelRegistry->get($productName);
        if (!$product->supportsCurrency($currency)) {
            throw PaymentProviderException::currencyNotSupported($productName, $currency);
        }
        if (!$product->supportsDevice($device)) {
            throw PaymentProviderException::deviceNotSupported($productName, (string) $device);
        }
        $assignment = $this->loadAppChannel($app->id, $productName);
        if (!$assignment instanceof PaymentAppChannelEntity || !$assignment->enabled) {
            throw PaymentProviderException::channelNotEnabled($productName, $app->technicalName);
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $app->id), new EqualsFilter('enabled', true));
        $criteria->addAssociation('account');
        $criteria->addSorting(new FieldSorting('position'), new FieldSorting('accountId'));
        $candidates = [];
        foreach ($this->appAccountRepository->search($criteria, Context::createDefaultContext())->getEntities() as $grant) {
            $account = $grant->account;
            if (!$grant->enabled || !$account instanceof PaymentAccountEntity || !$account->active || $account->providerTechnicalName !== $product->providerTechnicalName || !\in_array($productName, $account->enabledProducts, true)) {
                continue;
            }
            $params = $runtimeParams;
            $specificParams = $accountRuntimeParams[$account->id][$productName] ?? [];
            $complete = true;
            foreach ($product->requiredRuntimeParams() as $name) {
                unset($params[$name]);
                $value = $specificParams[$name] ?? null;
                if (!\is_scalar($value) || trim((string) $value) === '') {
                    $complete = false;
                }
            }
            if (!$complete) {
                continue;
            }
            $params = array_merge($params, $specificParams);
            $product->validateRuntimeParams($params);
            $credentials = $this->cipher->decrypt($account->credentials);
            if ($credentials === []) {
                continue;
            }
            $candidates[] = new ResolvedPaymentConfig(
                provider: $this->providerRegistry->get($account->providerTechnicalName),
                product: $product,
                credentials: $credentials,
                params: $this->cipher->decrypt($account->productParams[$productName] ?? null),
                runtimeParams: $params,
                settings: array_merge($account->settings ?? [], $grant->settings ?? [], $assignment->settings ?? []),
                priority: $grant->position,
                accountId: $account->id,
                accountFingerprint: PaymentAccountLock::fingerprint($account),
            );
        }

        return $candidates;
    }

    public function resolveForOrder(PaymentAppEntity $app, PaymentOrderEntity $order): ResolvedPaymentConfig
    {
        if ($order->activeAttempt === null) {
            throw PaymentOrderException::orderNotPayable($order->paymentNo);
        }

        return $this->resolveForAttempt($app, $order, $order->activeAttempt);
    }

    public function resolveForAttempt(PaymentAppEntity $app, PaymentOrderEntity $order, PaymentAttemptEntity $attempt): ResolvedPaymentConfig
    {
        $account = $this->loadAccount($attempt->accountId);
        $product = $this->channelRegistry->get($attempt->channelTechnicalName);
        if ($account->providerTechnicalName !== $attempt->providerTechnicalName || $product->providerTechnicalName !== $account->providerTechnicalName || $attempt->paymentOrderId !== $order->id || $order->paymentAppId !== $app->id) {
            throw PaymentProviderException::providerNotConfigured($attempt->providerTechnicalName);
        }

        $params = $attempt->configuration['params'];
        $currentParams = $this->cipher->decrypt($account->productParams[$product->technicalName] ?? null);
        foreach ($product->params as $field) {
            if ($field->secret && \array_key_exists($field->name, $currentParams)) {
                $params[$field->name] = $currentParams[$field->name];
            }
        }

        return new ResolvedPaymentConfig(
            provider: $this->providerRegistry->get($account->providerTechnicalName),
            product: $product,
            credentials: $this->credentialsOfAccount($account),
            params: $params,
            runtimeParams: $attempt->configuration['runtimeParams'],
            settings: $attempt->configuration['settings'],
            accountId: $account->id,
        );
    }

    public function resolveForTransfer(PaymentAppEntity $app, PaymentTransferEntity $transfer): ResolvedPaymentConfig
    {
        if ($transfer->paymentAppId !== $app->id) {
            throw PaymentProviderException::accountNotAuthorized($transfer->accountId, $app->technicalName);
        }

        return $this->resolveBoundAccount($transfer->accountId, $transfer->provider, $transfer->channel, $transfer->channelParams ?? []);
    }

    public function resolveForSubscription(PaymentSubscriptionEntity $subscription): ResolvedPaymentConfig
    {
        return $this->resolveBoundAccount($subscription->accountId, $subscription->provider, $subscription->channel, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function accountCredentials(string $id, string $provider): array
    {
        $account = $this->loadAccount($id);
        if ($account->providerTechnicalName !== $provider) {
            throw PaymentProviderException::providerNotConfigured($provider);
        }

        return $this->credentialsOfAccount($account);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function enabledFor(PaymentAppEntity $app): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $app->id), new EqualsFilter('enabled', true));
        $criteria->addAssociation('account');
        $criteria->addSorting(new FieldSorting('position'), new FieldSorting('accountId'));
        $providers = [];
        foreach ($this->appAccountRepository->search($criteria, Context::createDefaultContext())->getEntities() as $grant) {
            if ($grant->enabled && $grant->account?->active) {
                $providers[$grant->account->providerTechnicalName] = array_unique([...($providers[$grant->account->providerTechnicalName] ?? []), ...$grant->account->enabledProducts]);
            }
        }
        $channels = [];
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $app->id), new EqualsFilter('enabled', true));
        foreach ($this->appChannelRepository->search($criteria, Context::createDefaultContext())->getEntities() as $assignment) {
            if (!$this->channelRegistry->has($assignment->channelTechnicalName)) {
                continue;
            }
            $product = $this->channelRegistry->get($assignment->channelTechnicalName);
            if (!$assignment->enabled || !\in_array($product->technicalName, $providers[$product->providerTechnicalName] ?? [], true)) {
                continue;
            }
            $channels[] = [
                'channel' => $product->technicalName,
                'provider' => $product->providerTechnicalName,
                'label' => $product->label,
                'interactions' => $product->interactions,
                'currencies' => $product->currencies,
                'devices' => $product->devices(),
                'runtime_params' => $product->runtimeParams,
            ];
        }

        return $channels;
    }

    public function loadAccount(string $id): PaymentAccountEntity
    {
        $account = $this->accountRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        if (!$account instanceof PaymentAccountEntity) {
            throw PaymentProviderException::accountNotFound($id);
        }

        return $account;
    }

    /**
     * @param array<string, mixed> $runtimeParams
     */
    private function resolveBoundAccount(string $id, string $provider, string $productName, array $runtimeParams): ResolvedPaymentConfig
    {
        $account = $this->loadAccount($id);
        $product = $this->channelRegistry->get($productName);
        if ($account->providerTechnicalName !== $provider || $product->providerTechnicalName !== $provider) {
            throw PaymentProviderException::providerNotConfigured($provider);
        }

        return new ResolvedPaymentConfig(
            provider: $this->providerRegistry->get($provider),
            product: $product,
            credentials: $this->credentialsOfAccount($account),
            params: $this->cipher->decrypt($account->productParams[$productName] ?? null),
            runtimeParams: $runtimeParams,
            settings: $account->settings ?? [],
            accountId: $id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function credentialsOfAccount(PaymentAccountEntity $account): array
    {
        $credentials = $this->cipher->decrypt($account->credentials);
        if ($credentials === []) {
            throw PaymentProviderException::providerNotConfigured($account->providerTechnicalName);
        }

        return $credentials;
    }

    private function loadAppChannel(string $appId, string $channel): ?PaymentAppChannelEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $appId), new EqualsFilter('channelTechnicalName', $channel));

        return $this->appChannelRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }
}
