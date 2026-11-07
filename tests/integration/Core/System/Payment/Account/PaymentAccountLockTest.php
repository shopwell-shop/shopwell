<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Account;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Account\PaymentAccountIdentitySubscriber;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * @internal
 */
#[Package('framework')]
class PaymentAccountLockTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Connection $contender;

    private string $accountId;

    private string $appId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        static::assertSame(0, $this->connection->getTransactionNestingLevel());
        $this->contender = DriverManager::getConnection($this->connection->getParams());
        $this->contender->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        $this->accountId = Uuid::randomHex();
        $this->appId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        static::getContainer()->get('payment_app.repository')->create([[
            'id' => $this->appId,
            'technicalName' => 'account_lock_' . $this->appId,
            'name' => 'Concurrent account owner',
            'type' => PaymentAppEntity::TYPE_EXTERNAL,
            'active' => true,
            'appSecret' => $this->cipher()->encryptValue('secret'),
        ]], $context);
        static::getContainer()->get('payment_account.repository')->create([[
            'id' => $this->accountId,
            'providerTechnicalName' => 'alipay',
            'name' => 'Original merchant',
            'ownerAppId' => $this->appId,
            'active' => true,
            'enabledProducts' => ['alipay_h5'],
            'credentials' => $this->cipher()->encrypt(['app_id' => 'original-merchant']),
        ]], $context);
    }

    protected function tearDown(): void
    {
        while ($this->contender->isTransactionActive()) {
            $this->contender->rollBack();
        }
        $this->contender->close();
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        $this->connection->executeStatement('DELETE FROM payment_order WHERE payment_app_id = UNHEX(:id)', ['id' => $this->appId]);
        $this->connection->executeStatement('DELETE FROM payment_account WHERE owner_app_id = UNHEX(:id)', ['id' => $this->appId]);
        $this->connection->executeStatement('DELETE FROM payment_app WHERE id = UNHEX(:id)', ['id' => $this->appId]);
        parent::tearDown();
    }

    #[DataProvider('durableBindings')]
    public function testEveryFirstBindingAcquiresAnExclusiveAccountLockBeforeItsInsert(string $entity): void
    {
        $definition = static::getContainer()->get(DefinitionInstanceRegistry::class)->getByEntityName($entity);
        $command = new InsertCommand($definition, ['account_id' => Uuid::fromHexToBytes($this->accountId)], ['id' => Uuid::randomBytes()], EntityExistence::createEmpty(), '');
        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [$command]);
        $this->connection->beginTransaction();
        static::getContainer()->get(PaymentAccountIdentitySubscriber::class)->validate($event);
        $this->contender->beginTransaction();

        // A foreign-key shared lock would allow this read. The prewrite must already own an
        // exclusive account lock, before any aggregate insert or FK check has happened.
        $this->expectException(LockWaitTimeoutException::class);
        $this->contender->fetchOne('SELECT id FROM payment_account WHERE id = UNHEX(:id) LOCK IN SHARE MODE', ['id' => $this->accountId]);
    }

    public static function durableBindings(): \Generator
    {
        yield 'collection attempt' => ['payment_attempt'];
        yield 'transfer' => ['payment_transfer'];
        yield 'subscription' => ['payment_subscription'];
        yield 'mandate' => ['payment_mandate'];
    }

    public function testIdentityValidationSeesAFirstBindingCommittedAfterItsRepeatableReadSnapshot(): void
    {
        $app = static::getContainer()->get('payment_app.repository')->search(new Criteria([$this->appId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(PaymentAppEntity::class, $app);
        $order = static::getContainer()->get(PaymentOrderService::class)->create($app, new PaymentOrderRequest('lock-' . Uuid::randomHex(), 1000, 'CNY', 'Concurrent identity'));
        $accountLock = new PaymentAccountLock($this->contender);
        $bindingCommitted = false;
        $definition = static::getContainer()->get(DefinitionInstanceRegistry::class)->getByEntityName('payment_account');
        $command = new UpdateCommand($definition, ['credentials' => json_encode($this->cipher()->encrypt(['app_id' => 'another-merchant']), \JSON_THROW_ON_ERROR)], ['id' => Uuid::fromHexToBytes($this->accountId)], EntityExistence::createEmpty(), '');
        $subscriber = new PaymentAccountIdentitySubscriber($accountLock, static::getContainer()->get(PaymentProviderRegistry::class), $this->cipher());
        $this->expectExceptionObject(PaymentProviderException::accountIdentityImmutable($this->accountId));

        $accountLock->transaction(function () use ($accountLock, $order, $subscriber, $command, &$bindingCommitted): void {
            if (!$bindingCommitted) {
                static::assertSame(0, (int) $this->contender->fetchOne('SELECT COUNT(*) FROM payment_attempt WHERE account_id = UNHEX(:id)', ['id' => $this->accountId]));
                $this->connection->beginTransaction();
                static::getContainer()->get('payment_attempt.repository')->create([[
                    'id' => Uuid::randomHex(),
                    'paymentOrderId' => $order->id,
                    'attemptNo' => 'p' . Uuid::randomHex(),
                    'accountId' => $this->accountId,
                    'method' => 'alipay',
                    'providerTechnicalName' => 'alipay',
                    'channelTechnicalName' => 'alipay_h5',
                    'interaction' => 'redirect',
                    'status' => 'pending',
                    'context' => [],
                    'configuration' => [],
                ]], Context::createDefaultContext());
                try {
                    $accountLock->lock($this->accountId);
                    static::fail('An uncommitted binding must serialize an account edit.');
                } catch (LockWaitTimeoutException) {
                    // Commit the binding while the editor retains its older consistent-read snapshot.
                }
                $this->connection->commit();
                static::assertSame(0, (int) $this->contender->fetchOne('SELECT COUNT(*) FROM payment_attempt WHERE account_id = UNHEX(:id)', ['id' => $this->accountId]));
                $bindingCommitted = true;
            }

            $subscriber->validate(new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [$command]));
        });
    }

    public function testFirstBindingCanCreateItsAccountInTheSameDalWrite(): void
    {
        $app = static::getContainer()->get('payment_app.repository')->search(new Criteria([$this->appId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(PaymentAppEntity::class, $app);
        $order = static::getContainer()->get(PaymentOrderService::class)->create($app, new PaymentOrderRequest('nested-' . Uuid::randomHex(), 1000, 'CNY', 'Nested account binding'));
        $accountId = Uuid::randomHex();
        $attemptId = Uuid::randomHex();

        static::getContainer()->get('payment_attempt.repository')->create([[
            'id' => $attemptId,
            'paymentOrderId' => $order->id,
            'attemptNo' => 'p' . Uuid::randomHex(),
            'account' => [
                'id' => $accountId, 'providerTechnicalName' => 'alipay', 'name' => 'New nested merchant',
                'ownerAppId' => $this->appId, 'active' => true, 'enabledProducts' => ['alipay_h5'],
            ],
            'method' => 'alipay',
            'providerTechnicalName' => 'alipay',
            'channelTechnicalName' => 'alipay_h5',
            'interaction' => 'redirect',
            'status' => 'pending',
            'context' => [],
            'configuration' => [],
        ]], Context::createDefaultContext());

        static::assertSame($accountId, $this->connection->fetchOne('SELECT LOWER(HEX(account_id)) FROM payment_attempt WHERE id = UNHEX(:id)', ['id' => $attemptId]));
    }

    public function testLockedAccountHydrationUsesCurrentStateAfterAConcurrentEdit(): void
    {
        $accountLock = new PaymentAccountLock($this->contender);
        $edited = false;
        $current = $accountLock->transaction(function () use ($accountLock, &$edited) {
            if (!$edited) {
                static::assertSame('Original merchant', $this->contender->fetchOne('SELECT name FROM payment_account WHERE id = UNHEX(:id)', ['id' => $this->accountId]));
                static::getContainer()->get('payment_account.repository')->update([[
                    'id' => $this->accountId,
                    'name' => 'Current merchant',
                    'credentials' => $this->cipher()->encrypt(['app_id' => 'current-merchant']),
                ]], Context::createDefaultContext());
                $edited = true;
            }

            return $accountLock->lock($this->accountId);
        });
        static::assertSame('Current merchant', $current->name);
        static::assertSame(['app_id' => 'current-merchant'], $this->cipher()->decrypt($current->credentials));
    }

    private function cipher(): PaymentCredentialCipher
    {
        return static::getContainer()->get(PaymentCredentialCipher::class);
    }
}
