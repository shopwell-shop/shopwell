<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableWriteTransaction;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * Writes an app and everything that belongs to it: the app itself, its secret, the interfaces it is
 * authorized for and the channels it offers.
 *
 * An app always has a secret, but only the one that is typed (or generated) in this call is ever
 * handed back - the stored one cannot be read again, it is sealed with the same cipher the
 * application verifies the signatures with.
 *
 * Apps authorize explicit merchant accounts and separately enable their payment products.
 * Accounts retain their identity and encrypted keys when an app grant is revoked.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentAppProvisioner
{
    private const GENERATED_SECRET_BYTES = 32;

    /**
     * @param EntityRepository<PaymentAccountCollection> $accountRepository
     * @param EntityRepository<PaymentAppCollection> $appRepository
     * @param EntityRepository<PaymentAppAccountCollection> $appAccountRepository
     * @param EntityRepository<PaymentAppChannelCollection> $appChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly EntityRepository $appAccountRepository,
        private readonly EntityRepository $appChannelRepository,
        private readonly PaymentCredentialCipher $cipher,
        private readonly PaymentProviderRegistry $providerRegistry,
        private readonly PaymentProductRegistry $channelRegistry,
        private readonly ClockInterface $clock,
        private readonly Connection $connection,
        private readonly EntityRepository $accountRepository,
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
        return RetryableWriteTransaction::retryable($this->connection, function () use ($dto): array {
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
            $providers = $dto->accounts === null
                ? $this->loadAccounts($write['id'])
                : $this->syncAccounts($write['id'], $dto->accounts);

            if ($dto->channels !== null) {
                $this->syncChannels($write['id'], $dto->channels, $providers);
            }

            $stored = $this->loadApp($write['id']);
            \assert($stored instanceof PaymentAppEntity);

            return ['app' => $stored, 'secret' => $secret, 'generated' => $generated];
        });
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

        throw PaymentAppException::platformTenantNotDeletable($app->technicalName);
    }

    /**
     * Replaces the explicit account grants while retaining the merchant accounts themselves.
     *
     * @param list<PaymentAppAccountDto> $incoming
     *
     * @return array<string, PaymentAppAccountEntity>
     */
    private function syncAccounts(string $appId, array $incoming): array
    {
        $existing = $this->loadAccounts($appId);

        $context = Context::createDefaultContext();

        $write = [];
        $keep = [];

        foreach ($incoming as $provider) {
            if ($provider->accountId === '') {
                throw PaymentException::missingParameter('account_id');
            }
            $account = $this->loadAccount($provider->accountId);
            if ($account === null) {
                throw PaymentProviderException::accountNotFound($provider->accountId);
            }
            $this->providerRegistry->get($account->providerTechnicalName);
            $accountId = $account->id;
            $current = $existing[$accountId] ?? null;
            $keep[] = $accountId;
            $row = [
                'id' => $current->id ?? Uuid::randomHex(),
                'paymentAppId' => $appId,
                'accountId' => $accountId,
                'position' => $provider->position,
                'enabled' => $provider->enabled,
            ];

            $write[] = $row;
        }

        $remove = [];
        foreach ($existing as $technicalName => $row) {
            if (!\in_array($technicalName, $keep, true)) {
                $remove[] = ['id' => $row->id];
            }
        }

        if ($remove !== []) {
            $this->appAccountRepository->delete($remove, $context);
        }

        if ($write !== []) {
            $this->appAccountRepository->upsert($write, $context);
        }

        return $this->loadAccounts($appId);
    }

    /**
     * The channels of the app are the channels it offers to its own users. A channel is only written
     * when the interface behind it is authorized for the app - otherwise the app would hold an
     * assignment it can never use, and the gateway would refuse the payment later on.
     *
     * @param list<PaymentAppChannelDto> $incoming
     * @param array<string, PaymentAppAccountEntity> $providers
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

            if ($channel->enabled && !\array_filter($providers, static fn (PaymentAppAccountEntity $grant): bool => $grant->enabled && $grant->account?->providerTechnicalName === $definition->providerTechnicalName)) {
                throw PaymentProviderException::providerNotEnabled($definition->providerTechnicalName, $appId);
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
     * @param array<string> $ips
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
        $criteria->addAssociation('accounts');
        $criteria->addAssociation('accounts.account');
        $criteria->addAssociation('channels');

        $app = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $app instanceof PaymentAppEntity ? $app : null;
    }

    private function loadAccount(string $id): ?PaymentAccountEntity
    {
        return $this->accountRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
    }

    /**
     * @return array<string, PaymentAppAccountEntity>
     */
    private function loadAccounts(string $appId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $appId));
        $criteria->addAssociation('account');

        $providers = [];
        foreach ($this->appAccountRepository->search($criteria, Context::createDefaultContext())->getEntities() as $provider) {
            if ($provider instanceof PaymentAppAccountEntity) {
                $providers[$provider->accountId] = $provider;
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
