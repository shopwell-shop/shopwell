<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;

/**
 * Resolves which interface, which merchant account and which channel parameters one payment runs with.
 *
 * There are two independent authorizations, and both are checked:
 *
 *  1. the app is authorized for the payment interface - that is the merchant account, and the account
 *     source of that authorization says whose it is: the one of the app itself or the one of another
 *     app it inherits, the tenant of the platform in particular,
 *  2. the app has the channel enabled - being allowed to collect through alipay does not mean the app
 *     offers every alipay product to its users.
 *
 * The channel parameters follow the account source on purpose: a platform appid next to an app merchant
 * id would be rejected by the interface anyway, so the two must never be mixed.
 *
 * The account source is resolved along the chain of `sourceAppId`s and stops at the tenant of the
 * platform, whose account is the platform level `payment_provider_config`. The chain of an app that
 * owns its account ends at its own authorization, which carries the credentials the app typed.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentConfigResolver
{
    /**
     * How many hops the inheritance chain of an account source may have.
     *
     * The resolver detects circles by remembering the apps it visited, so the limit is not what keeps
     * it from running forever - it keeps a chain that is technically acyclic but absurdly deep from
     * turning one payment into dozens of queries.
     */
    private const MAX_ACCOUNT_SOURCE_HOPS = 8;

    /**
     * @param EntityRepository<PaymentAppProviderCollection> $appProviderRepository
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigRepository
     * @param EntityRepository<PaymentAppChannelCollection> $appChannelRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     */
    public function __construct(
        private readonly PaymentProviderRegistry $providerRegistry,
        private readonly PaymentChannelRegistry $channelRegistry,
        private readonly EntityRepository $appProviderRepository,
        private readonly EntityRepository $providerConfigRepository,
        private readonly EntityRepository $appChannelRepository,
        private readonly EntityRepository $channelConfigRepository,
        private readonly PaymentCredentialCipher $cipher,
    ) {
    }

    /**
     * @param array<string, mixed> $runtimeParams the parameters the app handed in for this very payment
     *
     * @throws PaymentException
     */
    public function resolve(
        PaymentAppEntity $app,
        string $channelTechnicalName,
        string $currency,
        ?string $device = null,
        array $runtimeParams = []
    ): ResolvedPaymentConfig {
        $channel = $this->channelRegistry->get($channelTechnicalName);

        if (!$channel->supportsCurrency($currency)) {
            throw PaymentException::currencyNotSupported($channelTechnicalName, $currency);
        }

        if (!$channel->supportsDevice($device)) {
            throw PaymentException::deviceNotSupported($channelTechnicalName, (string) $device);
        }

        $channel->validateRuntimeParams($runtimeParams);

        $provider = $this->providerRegistry->get($channel->providerTechnicalName);

        // whose merchant account is charged is checked before the channel is: an app that is not
        // authorized for the interface at all should not learn that a channel of it is switched off
        $account = $this->resolveAccount($app, $provider->technicalName);

        // the channel of the app is what the app offers to its own users, so it is always the channel
        // of the app the payment is made for - never the one of the app the account is inherited from
        $appChannel = $this->loadAppChannel($app->id, $channelTechnicalName);

        if (!$appChannel instanceof PaymentAppChannelEntity || !$appChannel->enabled) {
            throw PaymentException::channelNotEnabled($channelTechnicalName, $app->technicalName);
        }

        // A channel the platform does not offer cannot be collected through at all, no matter whose
        // merchant account is charged - which appid, for instance, a wechat channel runs on is decided
        // by the platform and not by the app.
        $platformChannel = $this->loadChannelConfig($provider->technicalName, $channelTechnicalName);

        if (!$platformChannel instanceof PaymentChannelConfigEntity || !$platformChannel->enabled) {
            throw PaymentException::channelDisabled($channelTechnicalName);
        }

        $platformProvider = null;
        $usesPlatformAccount = $this->isPlatformAccount($account['account']);

        if ($usesPlatformAccount) {
            $platformProvider = $this->loadProviderConfig($provider->technicalName);

            if (!$platformProvider instanceof PaymentProviderConfigEntity || !$platformProvider->enabled) {
                throw PaymentException::providerNotConfigured($provider->technicalName);
            }

            $credentials = $this->cipher->decrypt($platformProvider->credentials);
            $params = $this->cipher->decrypt($platformChannel->params);
        } else {
            $credentials = $this->cipher->decrypt($account['authorization']->credentials);
            $params = $this->cipher->decrypt($this->loadAccountParams($account['account'], $channelTechnicalName));
        }

        if ($credentials === []) {
            throw PaymentException::providerNotConfigured($provider->technicalName);
        }

        return new ResolvedPaymentConfig(
            provider: $provider,
            channel: $channel,
            credentials: $credentials,
            params: $params,
            runtimeParams: $runtimeParams,
            settings: $this->mergeSettings($platformProvider, $platformChannel, $account['start'], $appChannel),
            sourceAppId: $account['account']->id === $app->id ? null : $account['account']->id,
            usesPlatformAccount: $usesPlatformAccount,
        );
    }

    /**
     * The merchant credentials of an interface as they apply to one app.
     *
     * A payout notification is signed by the interface and not by a channel, but whose merchant
     * account signed it still follows the account source of the app: an app that collects on an
     * account of its own uses its own keys, one that inherits the account of another app - the
     * platform tenant in particular - is verified with the keys of that app. Verifying with the wrong
     * ones would reject every single notification.
     *
     * This is the provider half of {@see resolve()} without a channel - the notification endpoint has
     * no channel, and the body that names the payout is not read before the signature is verified.
     *
     * @throws PaymentException
     *
     * @return array<string, mixed> the decrypted credentials of the interface
     */
    public function resolveProviderCredentials(PaymentAppEntity $app, string $providerTechnicalName): array
    {
        $provider = $this->providerRegistry->get($providerTechnicalName);

        $account = $this->resolveAccount($app, $provider->technicalName);

        if ($this->isPlatformAccount($account['account'])) {
            $platformProvider = $this->loadProviderConfig($provider->technicalName);

            if (!$platformProvider instanceof PaymentProviderConfigEntity || !$platformProvider->enabled) {
                throw PaymentException::providerNotConfigured($provider->technicalName);
            }

            $credentials = $this->cipher->decrypt($platformProvider->credentials);
        } else {
            $credentials = $this->cipher->decrypt($account['authorization']->credentials);
        }

        if ($credentials === []) {
            throw PaymentException::providerNotConfigured($provider->technicalName);
        }

        return $credentials;
    }

    /**
     * The channels an app may actually use, so the app can build its own payment selection without
     * guessing which of the channels of the platform are enabled for it.
     *
     * A channel that is enabled but whose interface is not configured is deliberately still listed: the
     * app learns that it is not usable yet when it creates the payment order, which is the only place
     * the credentials are needed.
     *
     * @return list<array<string, mixed>>
     */
    public function enabledFor(PaymentAppEntity $app): array
    {
        $context = Context::createDefaultContext();

        $providerCriteria = new Criteria();
        $providerCriteria->addFilter(new EqualsFilter('paymentAppId', $app->id), new EqualsFilter('enabled', true));

        $sources = [];
        foreach ($this->appProviderRepository->search($providerCriteria, $context)->getEntities() as $authorization) {
            if ($authorization instanceof PaymentAppProviderEntity) {
                $sources[$authorization->providerTechnicalName] = $authorization->sourceAppId;
            }
        }

        if ($sources === []) {
            return [];
        }

        $channelCriteria = new Criteria();
        $channelCriteria->addFilter(new EqualsFilter('paymentAppId', $app->id), new EqualsFilter('enabled', true));

        $channels = [];
        foreach ($this->appChannelRepository->search($channelCriteria, $context)->getEntities() as $assignment) {
            if (!$assignment instanceof PaymentAppChannelEntity) {
                continue;
            }

            // the channel of an extension may still be stored here while the extension is not installed
            // (anymore), which is not an error for the app
            if (!$this->channelRegistry->has($assignment->channelTechnicalName)) {
                continue;
            }

            $channel = $this->channelRegistry->get($assignment->channelTechnicalName);

            if (!\array_key_exists($channel->providerTechnicalName, $sources)) {
                // the interface itself is not authorized, so the channel is not usable either
                continue;
            }

            $channels[] = $this->presentChannel($channel, $sources[$channel->providerTechnicalName]);
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentChannel(PaymentChannel $channel, ?string $sourceAppId): array
    {
        $provider = $this->providerRegistry->get($channel->providerTechnicalName);

        return [
            'channel' => $channel->technicalName,
            'channel_label' => $channel->label,
            'provider' => $provider->technicalName,
            'provider_label' => $provider->label,
            'flow' => $channel->flow->value,
            'currencies' => $channel->currencies,
            'devices' => $channel->devices,
            'runtime_params' => $channel->runtimeParams,
            'source_app_id' => $sourceAppId,
            'inherited' => $sourceAppId !== null,
        ];
    }

    /**
     * Walks the account source chain of one interface, and is the single place both {@see resolve()}
     * and {@see resolveProviderCredentials()} take their credentials from.
     *
     * The chain starts at the authorization of the app the payment is made for. An authorization with
     * a `sourceAppId` does not collect on its own account but on the one of the app it names, so the
     * walk continues there with the authorization of *that* app for the same interface. The tenant of
     * the platform is the end of every chain: it collects on the account that is configured on the
     * platform level, and a source of its own would be a second root the model does not have.
     *
     * @throws PaymentException when an authorization of the chain is missing or switched off, when the
     *                          chain runs in a circle, or when it names an app that does not exist
     *
     * @return array{account: PaymentAppEntity, authorization: PaymentAppProviderEntity, start: PaymentAppProviderEntity}
     *                                                                                                                    the app whose merchant account is charged, the authorization that carries the credentials
     *                                                                                                                    (its own, or the one that is ignored because the account is the platform one), and the
     *                                                                                                                    authorization of the app the payment is made for
     */
    private function resolveAccount(PaymentAppEntity $app, string $providerTechnicalName): array
    {
        $visited = [$app->id => true];
        $current = $app;

        $start = $this->requireAuthorization($app, $providerTechnicalName);
        $authorization = $start;
        $hops = 0;

        while (!$this->isPlatformAccount($current)) {
            $sourceAppId = $authorization->sourceAppId;

            if ($sourceAppId === null) {
                break;
            }

            if (isset($visited[$sourceAppId]) || $hops >= self::MAX_ACCOUNT_SOURCE_HOPS) {
                throw PaymentException::providerInheritanceCycle($providerTechnicalName, $app->technicalName);
            }

            $source = $authorization->sourceApp;

            if (!$source instanceof PaymentAppEntity) {
                throw PaymentException::providerSourceAppMissing($providerTechnicalName, $current->technicalName, $sourceAppId);
            }

            $visited[$sourceAppId] = true;
            ++$hops;

            $current = $source;
            $authorization = $this->requireAuthorization($current, $providerTechnicalName);
        }

        return ['account' => $current, 'authorization' => $authorization, 'start' => $start];
    }

    /**
     * @throws PaymentException when the app is not authorized for the interface, or the authorization
     *                          is switched off
     */
    private function requireAuthorization(PaymentAppEntity $app, string $providerTechnicalName): PaymentAppProviderEntity
    {
        $authorization = $this->loadAppProvider($app->id, $providerTechnicalName);

        if (!$authorization instanceof PaymentAppProviderEntity || !$authorization->enabled) {
            throw PaymentException::providerNotEnabled($providerTechnicalName, $app->technicalName);
        }

        return $authorization;
    }

    /**
     * The tenant of the platform is the one app that collects on the platform level configuration, its
     * authorizations never carry credentials of their own.
     *
     * `type` is a column of the row and therefore always set on an entity the repository loaded. The
     * `isset()` covers the half built entities a fixture passes in - and answers `false` for them on
     * purpose: an app of unknown type is a merchant app and never collects on the platform account by
     * accident, which is the safe direction for a decision about whose money is moved.
     */
    private function isPlatformAccount(PaymentAppEntity $app): bool
    {
        return isset($app->type) && $app->type === PaymentAppEntity::TYPE_PLATFORM;
    }

    /**
     * The channel parameters of the app whose account is charged. They belong to that app and not to
     * the one the payment is made for, for the very reason the credentials do: the appid of a wechat
     * channel is part of the merchant configuration and does not fit a foreign merchant id.
     *
     * @return array<string, mixed>|null the sealed parameters, `null` when the app never stored any
     */
    private function loadAccountParams(PaymentAppEntity $app, string $channelTechnicalName): ?array
    {
        $appChannel = $this->loadAppChannel($app->id, $channelTechnicalName);

        return $appChannel instanceof PaymentAppChannelEntity ? $appChannel->params : null;
    }

    /**
     * The platform settings are the defaults, the app may override them.
     *
     * @return array<string, mixed>
     */
    private function mergeSettings(
        ?PaymentProviderConfigEntity $platformProvider,
        ?PaymentChannelConfigEntity $platformChannel,
        PaymentAppProviderEntity $appProvider,
        PaymentAppChannelEntity $appChannel
    ): array {
        $platform = array_merge(
            $platformProvider->settings ?? [],
            $platformChannel->settings ?? []
        );

        $app = array_merge(
            $appProvider->settings ?? [],
            $appChannel->settings ?? []
        );

        return array_merge($platform, $app);
    }

    private function loadProviderConfig(string $providerTechnicalName): ?PaymentProviderConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('providerTechnicalName', $providerTechnicalName));
        $criteria->setLimit(1);

        $config = $this->providerConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $config instanceof PaymentProviderConfigEntity ? $config : null;
    }

    private function loadChannelConfig(string $providerTechnicalName, string $channelTechnicalName): ?PaymentChannelConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('providerTechnicalName', $providerTechnicalName),
            new EqualsFilter('channelTechnicalName', $channelTechnicalName)
        );
        $criteria->setLimit(1);

        $config = $this->channelConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $config instanceof PaymentChannelConfigEntity ? $config : null;
    }

    private function loadAppProvider(string $appId, string $providerTechnicalName): ?PaymentAppProviderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $appId),
            new EqualsFilter('providerTechnicalName', $providerTechnicalName)
        );
        // the account source chain is walked over the source apps themselves, so the association is
        // part of every read of an authorization
        $criteria->addAssociation('sourceApp');
        $criteria->setLimit(1);

        $provider = $this->appProviderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $provider instanceof PaymentAppProviderEntity ? $provider : null;
    }

    private function loadAppChannel(string $appId, string $channelTechnicalName): ?PaymentAppChannelEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $appId),
            new EqualsFilter('channelTechnicalName', $channelTechnicalName)
        );
        $criteria->setLimit(1);

        $channel = $this->appChannelRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $channel instanceof PaymentAppChannelEntity ? $channel : null;
    }
}
