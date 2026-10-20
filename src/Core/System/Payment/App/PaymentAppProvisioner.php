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
 *  1. the app is authorized for a payment interface, and the account source of that authorization
 *     says whose merchant account is charged - an account of its own, or the one of another app it
 *     inherits, the tenant of the platform in particular,
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
     * How many hops an inheritance chain may have, see
     * {@see \Shopwell\Core\System\Payment\Provider\PaymentConfigResolver}. The provisioner refuses a
     * chain that is longer than the resolver is willing to walk, so a save can never write a row that
     * the payment would then be unable to resolve.
     */
    private const MAX_ACCOUNT_SOURCE_HOPS = 8;

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
     * Creates or updates an app from the body of the administration call.
     *
     * @throws PaymentException
     *
     * @return array{app: PaymentAppEntity, secret: string, generated: bool} the plain secret is only
     *                                                                       set when one was typed or generated in this call - whoever receives it is the only one
     *                                                                       who ever sees it
     */
    public function save(PaymentAppDto $dto): array
    {
        // the constraints of the dto reject a missing technical name, but the provisioner is also
        // called without the controller in front of it - a null must never reach the write as an
        // empty technical name, that would create an app nobody can address
        if ($dto->technicalName === null) {
            throw PaymentException::missingParameter('technical_name');
        }

        $id = $dto->id !== null && $dto->id !== '' ? $dto->id : null;
        $app = $id === null ? null : $this->loadApp($id);

        // the tenant of the platform is editable - it is the account the shop itself collects
        // through, and the merchant has to be able to authorize interfaces on it and switch channels
        // on. What it cannot be is renamed: its technical name is what the seeded configuration and
        // the migration refer to, so the stored one wins over the payload.
        $isPlatformTenant = $app !== null && $app->type === PaymentAppEntity::TYPE_PLATFORM;

        $write = [
            'id' => $app->id ?? Uuid::randomHex(),
            'technicalName' => $isPlatformTenant ? $app->technicalName : trim($dto->technicalName),
            'name' => $dto->name,
            'active' => $dto->active,
            'notifyUrl' => $this->nullableString($dto->notifyUrl),
            'returnUrl' => $this->nullableString($dto->returnUrl),
            'allowedIps' => $this->allowedIps($dto->allowedIps),
            'rateLimit' => $dto->rateLimit,
        ];

        // an app onboarded here is always a caller of the api. The type is written on creation only:
        // it is not part of the payload, and an existing row must never change its kind - the tenant
        // of the shop is seeded by the migration and a save that names it is refused above
        if ($app === null) {
            $write['type'] = PaymentAppEntity::TYPE_EXTERNAL;
        }

        // an app always has a secret, but only the one that is typed (or generated) in this call is
        // ever handed back - the stored one cannot be read again
        $secret = $dto->appSecret !== null ? trim($dto->appSecret) : '';
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

        // an omitted list keeps the stored authorizations: revoking them is a security relevant
        // decision and must never be a side effect of a caller that did not send the field
        $providers = $dto->providers === null
            ? $this->loadProviders($write['id'])
            : $this->syncProviders($write['id'], $write['technicalName'], $dto->providers);

        if ($dto->channels !== null) {
            $this->syncChannels($write['id'], $dto->channels, $providers);
        }

        $stored = $this->loadApp($write['id']);
        \assert($stored instanceof PaymentAppEntity);

        return ['app' => $stored, 'secret' => $secret, 'generated' => $generated];
    }

    /**
     * Removes an app and everything that is cascaded with it.
     *
     * The row is read before it is deleted: only the stored row tells which kind of tenant it is, and
     * the tenant of the platform must not be deletable at all - the shop would lose the account it
     * attributes its own payments to, and every platform payment would lose its bookkeeping.
     *
     * @throws PaymentException
     */
    public function delete(string $id, Context $context): void
    {
        $app = $this->loadApp($id);

        $this->assertDeletable($app);

        $this->appRepository->delete([['id' => $id]], $context);
    }

    /**
     * The tenant of the platform cannot be deleted: the shop would lose the account it attributes its
     * own payments to, and every platform payment would lose its bookkeeping. Editing it is a
     * different matter - the merchant configures the interfaces and channels of the shop on it - so
     * this guard is only asked before a delete.
     *
     * @throws PaymentException
     */
    private function assertDeletable(?PaymentAppEntity $app): void
    {
        if ($app === null || $app->type !== PaymentAppEntity::TYPE_PLATFORM) {
            return;
        }

        throw PaymentException::platformTenantNotDeletable($app->technicalName);
    }

    /**
     * The interfaces of the app are the interfaces it may collect through, so the payload is the full
     * list: an interface that is not in it does not belong to the app anymore.
     *
     * @param list<PaymentAppProviderDto> $incoming
     *
     * @return array<string, PaymentAppProviderEntity>
     */
    private function syncProviders(string $appId, string $appTechnicalName, array $incoming): array
    {
        $existing = $this->loadProviders($appId);

        $context = Context::createDefaultContext();

        $write = [];
        $keep = [];

        foreach ($incoming as $provider) {
            $technicalName = $provider->providerTechnicalName;

            // an authorization without an interface cannot be resolved and must not be looked up as
            // the empty name - the request is invalid, not the registry
            if ($technicalName === null) {
                throw PaymentException::missingParameter('provider_technical_name');
            }

            // an interface the shop does not know cannot be enabled for an app
            $this->providerRegistry->get($technicalName);

            $current = $existing[$technicalName] ?? null;
            $keep[] = $technicalName;

            $sourceAppId = $this->accountSource($appId, $appTechnicalName, $technicalName, $provider->sourceAppId);

            $row = [
                'id' => $current->id ?? Uuid::randomHex(),
                'paymentAppId' => $appId,
                'providerTechnicalName' => $technicalName,
                'enabled' => $provider->enabled,
                'sourceAppId' => $sourceAppId,
                'settings' => array_merge($current->settings ?? [], $provider->settings),
            ];

            // the credentials belong to the account of the app itself. An inheriting authorization
            // keeps whatever was stored before (the merchant may switch back tomorrow), but nothing
            // new is collected from a form that was not even shown.
            $credentials = $sourceAppId === null
                ? $this->mergeValues($current === null ? [] : $this->cipher->decrypt($current->credentials), $provider->credentials)
                : [];

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
     * @param list<PaymentAppChannelDto> $incoming
     * @param array<string, PaymentAppProviderEntity> $providers
     */
    private function syncChannels(string $appId, array $incoming, array $providers): void
    {
        $existing = [];
        foreach ($this->loadChannels($appId) as $channel) {
            $existing[$channel->channelTechnicalName] = $channel;
        }

        $write = [];
        $keep = [];

        foreach ($incoming as $channel) {
            $technicalName = $channel->channelTechnicalName;

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
                'enabled' => $channel->enabled,
                'settings' => array_merge($current->settings ?? [], $channel->settings),
            ];

            $params = $this->mergeValues($current === null ? [] : $this->cipher->decrypt($current->params), $channel->params);

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
     * @param array<string, string> $current
     * @param array<string, string> $incoming
     *
     * @return array<string, string>
     */
    private function mergeValues(array $current, array $incoming): array
    {
        foreach ($incoming as $name => $value) {
            if ($value === '') {
                continue;
            }

            $current[$name] = $value;
        }

        return $current;
    }

    /**
     * The account source of one interface, validated before it is written.
     *
     * An empty value means "the app collects on its own account". Anything else has to name an app
     * that exists, and the resulting chain has to end somewhere: an app that inherits from itself, or
     * two apps that inherit from each other, would have no account at all. Both are refused here
     * instead of at the first payment, because this is the only moment the merchant can still act on
     * it.
     *
     * The walk is the same one {@see \Shopwell\Core\System\Payment\Provider\PaymentConfigResolver}
     * does when it collects, so a chain that is accepted here is one the payment can resolve. The
     * tenant of the platform ends every chain, it never inherits itself.
     *
     * @throws PaymentException
     */
    private function accountSource(
        string $appId,
        string $appTechnicalName,
        string $providerTechnicalName,
        ?string $sourceAppId
    ): ?string {
        if ($sourceAppId === null || $sourceAppId === '') {
            return null;
        }

        $visited = [$appId => true];
        $currentId = $sourceAppId;
        $hops = 0;

        while (true) {
            // naming the app itself is the shortest circle, and the visited set is what catches the
            // longer ones - the hop limit only keeps an absurdly deep chain from being written
            if (isset($visited[$currentId]) || $hops > self::MAX_ACCOUNT_SOURCE_HOPS) {
                throw PaymentException::providerInheritanceCycle($providerTechnicalName, $appTechnicalName);
            }

            $visited[$currentId] = true;

            $source = $this->loadAppRow($currentId);

            if (!$source instanceof PaymentAppEntity) {
                throw PaymentException::providerSourceAppMissing($providerTechnicalName, $appTechnicalName, $currentId);
            }

            // the tenant of the platform is the root of the chain: it collects on the platform level
            // configuration and has nobody to inherit from
            if ($source->type === PaymentAppEntity::TYPE_PLATFORM) {
                return $sourceAppId;
            }

            ++$hops;

            $nextSourceAppId = $this->loadProviderRow($currentId, $providerTechnicalName)?->sourceAppId;

            if ($nextSourceAppId === null) {
                return $sourceAppId;
            }

            $currentId = $nextSourceAppId;
        }
    }

    /**
     * @param list<string> $ips
     *
     * @return list<string>
     */
    private function allowedIps(array $ips): array
    {
        $list = [];
        foreach ($ips as $ip) {
            if ($ip !== '') {
                $list[] = $ip;
            }
        }

        return $list;
    }

    private function nullableString(?string $value): ?string
    {
        return $value !== null && $value !== '' ? $value : null;
    }

    private function generateSecret(): string
    {
        return bin2hex(random_bytes(self::GENERATED_SECRET_BYTES));
    }

    private function loadApp(string $id): ?PaymentAppEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('providers');
        $criteria->addAssociation('providers.sourceApp');
        $criteria->addAssociation('channels');

        $app = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $app instanceof PaymentAppEntity ? $app : null;
    }

    /**
     * The plain row of an app, without any association: the account source chain only needs the type
     * and the technical name of the apps it walks through.
     */
    private function loadAppRow(string $id): ?PaymentAppEntity
    {
        $app = $this->appRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        return $app instanceof PaymentAppEntity ? $app : null;
    }

    private function loadProviderRow(string $appId, string $providerTechnicalName): ?PaymentAppProviderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $appId),
            new EqualsFilter('providerTechnicalName', $providerTechnicalName)
        );
        $criteria->setLimit(1);

        $provider = $this->appProviderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $provider instanceof PaymentAppProviderEntity ? $provider : null;
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
