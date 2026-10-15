<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * Writes an app and everything that belongs to it: the app itself, its secret, the interfaces it is
 * authorized for and the channels it offers.
 *
 * An app always has a secret, but only the one that is typed (or generated) in this call is ever
 * handed back - the stored one cannot be read again, it is sealed with the same cipher the
 * application verifies the signatures with.
 *
 * Which payments an app may collect is two decisions, and both are made here:
 *
 *  1. the app is authorized for a payment interface, and `mode` says whose merchant account is
 *     charged - the one of the platform or the one the app brings itself,
 *  2. the app has individual channels of that interface enabled - being allowed to collect through
 *     alipay does not mean the app offers every alipay product to its users.
 *
 * A channel can only be enabled for an interface the app is authorized for: the interface carries
 * the credentials, and a channel without them cannot be charged at all.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentAppProvisioner
{
    private const GENERATED_SECRET_BYTES = 32;

    /**
     * @param EntityRepository<PaymentAppCollection> $appRepository
     * @param EntityRepository<PaymentAppProviderCollection> $appProviderRepository
     * @param EntityRepository<PaymentAppChannelCollection> $appChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly EntityRepository $appProviderRepository,
        private readonly EntityRepository $appChannelRepository,
        private readonly PaymentCredentialCipher $cipher,
        private readonly PaymentProviderRegistry $providerRegistry,
        private readonly PaymentChannelRegistry $channelRegistry,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Creates or updates an app from the already decoded payload of the administration.
     *
     * @param array<string, mixed> $payload
     *
     * @throws PaymentException
     *
     * @return array{app: PaymentAppEntity, secret: string, generated: bool} the plain secret is only
     *                                                                       set when one was typed or generated in this call - whoever receives it is the only one
     *                                                                       who ever sees it
     */
    public function save(array $payload): array
    {
        $id = \is_string($payload['id'] ?? null) && $payload['id'] !== '' ? $payload['id'] : null;
        $app = $id === null ? null : $this->loadApp($id);

        $technicalName = \is_string($payload['technicalName'] ?? null) ? trim($payload['technicalName']) : '';

        if ($technicalName === '') {
            throw PaymentException::missingParameter('technicalName');
        }

        $write = [
            'id' => $app->id ?? Uuid::randomHex(),
            'technicalName' => $technicalName,
            'name' => \is_string($payload['name'] ?? null) ? $payload['name'] : '',
            'active' => (bool) ($payload['active'] ?? false),
            'notifyUrl' => $this->nullableString($payload['notifyUrl'] ?? null),
            'returnUrl' => $this->nullableString($payload['returnUrl'] ?? null),
            'allowedIps' => $this->allowedIps($payload['allowedIps'] ?? null),
            'rateLimit' => \is_numeric($payload['rateLimit'] ?? null) ? (int) $payload['rateLimit'] : null,
        ];

        // an app always has a secret, but only the one that is typed (or generated) in this call is
        // ever handed back - the stored one cannot be read again
        $secret = \is_string($payload['appSecret'] ?? null) ? trim($payload['appSecret']) : '';
        $generated = false;

        if ($secret !== '') {
            // a typed secret that differs from the stored one is a rotation: the previous secret is
            // kept and accepted for a grace window, so the callers that still sign with it pass while
            // the app updates - see PaymentAppAuthenticator::SECRET_ROTATION_GRACE
            if ($app !== null && $this->cipher->decryptValue($app->appSecret) !== $secret) {
                $write['previousAppSecret'] = $app->appSecret;
                $write['secretRotatedAt'] = $this->clock->now();
            }

            $write['appSecret'] = $this->cipher->encryptValue($secret);
        } elseif ($app === null) {
            $secret = $this->generateSecret();
            $generated = true;
            $write['appSecret'] = $this->cipher->encryptValue($secret);
        }

        $this->appRepository->upsert([$write], Context::createDefaultContext());

        $providers = $this->syncProviders($write['id'], $payload['providers'] ?? null);
        $this->syncChannels($write['id'], $payload['channels'] ?? null, $providers);

        $stored = $this->loadApp($write['id']);
        \assert($stored instanceof PaymentAppEntity);

        return ['app' => $stored, 'secret' => $secret, 'generated' => $generated];
    }

    /**
     * The interfaces of the app are the interfaces it may collect through, so the payload is the full
     * list: an interface that is not in it does not belong to the app anymore.
     *
     * @return array<string, PaymentAppProviderEntity>
     */
    private function syncProviders(string $appId, mixed $incoming): array
    {
        $existing = $this->loadProviders($appId);

        if (!\is_array($incoming)) {
            return $existing;
        }

        $context = Context::createDefaultContext();

        $write = [];
        $keep = [];

        foreach ($incoming as $provider) {
            if (!\is_array($provider)) {
                continue;
            }

            $technicalName = \is_string($provider['providerTechnicalName'] ?? null) ? $provider['providerTechnicalName'] : '';

            if ($technicalName === '') {
                throw PaymentException::missingParameter('providerTechnicalName');
            }

            // an interface the shop does not know cannot be enabled for an app
            $this->providerRegistry->get($technicalName);

            $current = $existing[$technicalName] ?? null;
            $keep[] = $technicalName;

            $row = [
                'id' => $current->id ?? Uuid::randomHex(),
                'paymentAppId' => $appId,
                'providerTechnicalName' => $technicalName,
                'enabled' => (bool) ($provider['enabled'] ?? false),
                'mode' => $this->mode($provider['mode'] ?? null),
                'settings' => array_merge($current->settings ?? [], $this->mapOf($provider['settings'] ?? null)),
            ];

            $credentials = $this->mergeValues($current === null ? [] : $this->cipher->decrypt($current->credentials), $provider['credentials'] ?? null);

            if ($credentials !== []) {
                $row['credentials'] = $this->cipher->encrypt($credentials);
            }

            $write[] = $row;
        }

        $remove = [];
        foreach ($existing as $technicalName => $row) {
            if (!\in_array($technicalName, $keep, true)) {
                $remove[] = ['id' => $row->id];
            }
        }

        if ($remove !== []) {
            $this->appProviderRepository->delete($remove, $context);
        }

        if ($write !== []) {
            $this->appProviderRepository->upsert($write, $context);
        }

        return $this->loadProviders($appId);
    }

    /**
     * The channels of the app are the channels it offers to its own users. A channel is only written
     * when the interface behind it is authorized for the app - otherwise the app would hold an
     * assignment it can never use, and the gateway would refuse the payment later on.
     *
     * @param array<string, PaymentAppProviderEntity> $providers
     */
    private function syncChannels(string $appId, mixed $incoming, array $providers): void
    {
        if (!\is_array($incoming)) {
            return;
        }

        $existing = [];
        foreach ($this->loadChannels($appId) as $channel) {
            $existing[$channel->channelTechnicalName] = $channel;
        }

        $write = [];
        $keep = [];

        foreach ($incoming as $channel) {
            if (!\is_array($channel)) {
                continue;
            }

            $technicalName = \is_string($channel['channelTechnicalName'] ?? null) ? $channel['channelTechnicalName'] : '';

            if ($technicalName === '') {
                throw PaymentException::missingParameter('channelTechnicalName');
            }

            // an unknown channel is an error, and its interface has to be authorized for the app
            $definition = $this->channelRegistry->get($technicalName);

            if (!isset($providers[$definition->providerTechnicalName])) {
                throw PaymentException::providerNotEnabled($definition->providerTechnicalName, $appId);
            }

            $current = $existing[$technicalName] ?? null;
            $keep[] = $technicalName;

            $row = [
                'id' => $current->id ?? Uuid::randomHex(),
                'paymentAppId' => $appId,
                'channelTechnicalName' => $technicalName,
                'enabled' => (bool) ($channel['enabled'] ?? false),
                'settings' => array_merge($current->settings ?? [], $this->mapOf($channel['settings'] ?? null)),
            ];

            $params = $this->mergeValues($current === null ? [] : $this->cipher->decrypt($current->params), $channel['params'] ?? null);

            if ($params !== []) {
                $row['params'] = $this->cipher->encrypt($params);
            }

            $write[] = $row;
        }

        $remove = [];
        foreach (array_keys($existing) as $technicalName) {
            if (!\in_array($technicalName, $keep, true)) {
                $remove[] = ['id' => $existing[$technicalName]->id];
            }
        }

        $context = Context::createDefaultContext();

        if ($remove !== []) {
            $this->appChannelRepository->delete($remove, $context);
        }

        if ($write !== []) {
            $this->appChannelRepository->upsert($write, $context);
        }
    }

    /**
     * The values that are not sent stay untouched: an empty input means "keep what is stored", which
     * is the only way to edit an app whose secrets are never sent to the browser.
     *
     * @param array<string, mixed> $current
     *
     * @return array<string, mixed>
     */
    private function mergeValues(array $current, mixed $incoming): array
    {
        foreach ($this->mapOf($incoming) as $name => $value) {
            if ($value === '') {
                continue;
            }

            $current[$name] = $value;
        }

        return $current;
    }

    /**
     * @return array<string, string>
     */
    private function mapOf(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $name => $item) {
            if (!\is_string($name) || !\is_scalar($item)) {
                continue;
            }

            $map[$name] = (string) $item;
        }

        return $map;
    }

    private function mode(mixed $mode): string
    {
        if ($mode === PaymentAppProviderEntity::MODE_OWN) {
            return PaymentAppProviderEntity::MODE_OWN;
        }

        return PaymentAppProviderEntity::MODE_PLATFORM;
    }

    /**
     * @return list<string>
     */
    private function allowedIps(mixed $ips): array
    {
        if (!\is_array($ips)) {
            return [];
        }

        $list = [];
        foreach ($ips as $ip) {
            if (\is_string($ip) && $ip !== '') {
                $list[] = $ip;
            }
        }

        return $list;
    }

    private function nullableString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    private function generateSecret(): string
    {
        return bin2hex(random_bytes(self::GENERATED_SECRET_BYTES));
    }

    private function loadApp(string $id): ?PaymentAppEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('providers');
        $criteria->addAssociation('channels');

        $app = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $app instanceof PaymentAppEntity ? $app : null;
    }

    /**
     * @return array<string, PaymentAppProviderEntity>
     */
    private function loadProviders(string $appId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $appId));

        $providers = [];
        foreach ($this->appProviderRepository->search($criteria, Context::createDefaultContext())->getEntities() as $provider) {
            if ($provider instanceof PaymentAppProviderEntity) {
                $providers[$provider->providerTechnicalName] = $provider;
            }
        }

        return $providers;
    }

    /**
     * @return array<string, PaymentAppChannelEntity>
     */
    private function loadChannels(string $appId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $appId));

        $channels = [];
        foreach ($this->appChannelRepository->search($criteria, Context::createDefaultContext())->getEntities() as $channel) {
            if ($channel instanceof PaymentAppChannelEntity) {
                $channels[$channel->channelTechnicalName] = $channel;
            }
        }

        return $channels;
    }
}
