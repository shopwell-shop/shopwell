<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1791856510CreatePaymentTables;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStates;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Subscription\PaymentMandateStates;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleStates;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionStates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1791856510CreatePaymentTables::class)]
class Migration1791856510CreatePaymentTablesTest extends TestCase
{
    /**
     * The tables of the gateway in their final shape - the configuration matrix first, then the
     * bookkeeping, then the payum tokens of the running checkouts and the binding of a checkout
     * payment method to the channel it collects through.
     */
    private const TABLES = [
        'payment_app',
        'payment_provider_config',
        'payment_channel_config',
        'payment_app_provider',
        'payment_app_channel',
        'payment_order',
        'payment_gateway_token',
        'payment_transaction',
        'payment_refund',
        'payment_notification',
        'payment_webhook_delivery',
        'payment_method_gateway_channel',
        'payment_plan',
        'payment_mandate',
        'payment_subscription',
        'payment_subscription_cycle',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791856510, (new Migration1791856510CreatePaymentTables())->getCreationTimestamp());
    }

    public function testMigrationCreatesAllTablesAndIsIdempotent(): void
    {
        $migration = new Migration1791856510CreatePaymentTables();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (self::TABLES as $table) {
            static::assertTrue(TableHelper::tableExists($this->connection, $table), 'the table "' . $table . '" was not created');
        }
    }

    /**
     * The tables are created in the shape the model ended up with, not in the shape of the structure
     * they were once migrated from: the provider sits next to the channel on the order and on the
     * attempt, the channel keeps its parameters, and the credentials live on the interface.
     */
    public function testTablesAreCreatedInTheFinalModel(): void
    {
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        $orderColumns = array_column(TableHelper::getTable($this->connection, 'payment_order')->columns, 'name');
        static::assertContains('provider_technical_name', $orderColumns);
        static::assertContains('channel_params', $orderColumns);

        $transactionColumns = array_column(TableHelper::getTable($this->connection, 'payment_transaction')->columns, 'name');
        static::assertContains('provider_technical_name', $transactionColumns);

        $notificationColumns = array_column(TableHelper::getTable($this->connection, 'payment_notification')->columns, 'name');
        static::assertContains('provider_technical_name', $notificationColumns);
        static::assertNotContains('channel_technical_name', $notificationColumns);

        $channelConfigColumns = array_column(TableHelper::getTable($this->connection, 'payment_channel_config')->columns, 'name');
        static::assertContains('provider_technical_name', $channelConfigColumns);
        static::assertContains('params', $channelConfigColumns);
        static::assertNotContains('credentials', $channelConfigColumns);
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_channel_config', 'uniq.payment_channel_config.provider_channel', ['provider_technical_name', 'channel_technical_name']));

        $appChannelColumns = array_column(TableHelper::getTable($this->connection, 'payment_app_channel')->columns, 'name');
        static::assertContains('params', $appChannelColumns);
        static::assertNotContains('credentials', $appChannelColumns);
        static::assertNotContains('mode', $appChannelColumns);
    }

    public function testMigrationImportsTheStateMachines(): void
    {
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine` WHERE `technical_name` = :name',
            ['name' => PaymentOrderStates::STATE_MACHINE]
        ));
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine` WHERE `technical_name` = :name',
            ['name' => PaymentRefundStates::STATE_MACHINE]
        ));

        $states = $this->connection->fetchFirstColumn(
            'SELECT `technical_name` FROM `state_machine_state` WHERE `state_machine_id` = (
                SELECT `id` FROM `state_machine` WHERE `technical_name` = :name
            )',
            ['name' => PaymentOrderStates::STATE_MACHINE]
        );

        static::assertEqualsCanonicalizing([
            PaymentOrderStates::STATE_CREATED,
            PaymentOrderStates::STATE_OPENED,
            PaymentOrderStates::STATE_PAID,
            PaymentOrderStates::STATE_FAILED,
            PaymentOrderStates::STATE_CLOSED,
            PaymentOrderStates::STATE_EXPIRED,
            PaymentOrderStates::STATE_REFUNDED_PARTIALLY,
            PaymentOrderStates::STATE_REFUNDED,
        ], $states);
    }

    /**
     * An order the payer never got pay parameters for may expire; one that was handed to the channel
     * may still be paid there, and expiring it would lose that payment.
     */
    public function testOnlyAnOrderThatWasNeverHandedToTheChannelCanExpire(): void
    {
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        static::assertTrue($this->hasTransition(PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_EXPIRED));
        static::assertFalse($this->hasTransition(PaymentOrderStates::STATE_OPENED, PaymentOrderStates::STATE_EXPIRED));
    }

    /**
     * A token is the bearer credential an interface redirects the shopper back with: it is looked up by
     * its hash on every node.
     */
    public function testPaymentGatewayTokenTable(): void
    {
        // the shape is asserted on the table this migration creates, not on one an earlier run left behind
        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_gateway_token`');

        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        $table = TableHelper::getTable($this->connection, 'payment_gateway_token');

        static::assertSame(
            ['id', 'hash', 'order_id', 'target_url', 'after_url', 'gateway_name', 'created_at', 'updated_at'],
            array_column($table->columns, 'name')
        );

        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_gateway_token', 'uniq.payment_gateway_token.hash', ['hash']));
        static::assertTrue(TableHelper::indexExists($this->connection, 'payment_gateway_token', 'idx.payment_gateway_token.order_id'));

        // a token grants access to one order and must not outlive it
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_gateway_token', 'fk.payment_gateway_token.order_id'));
        static::assertSame('CASCADE', $this->deleteRuleOf('payment_gateway_token', 'fk.payment_gateway_token.order_id'));
    }

    /**
     * An event is announced once per subject: `order` for an event that happens once per order, the
     * refund id for a refund event. The subject therefore belongs to the unique key - without it the
     * second refund of one order would collide with the event of the first one.
     */
    public function testPaymentWebhookDeliveryIsUniquePerSubject(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_webhook_delivery`');

        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        $subject = TableHelper::getColumnOfTable($this->connection, 'payment_webhook_delivery', 'subject');
        static::assertTrue($subject->isNotNull);
        static::assertSame(64, $subject->length);
        static::assertNull($subject->defaultValue);

        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'payment_webhook_delivery',
            'uniq.payment_webhook_delivery.subject',
            ['payment_order_id', 'event', 'subject']
        ));
        static::assertFalse(TableHelper::indexExists($this->connection, 'payment_webhook_delivery', 'uniq.payment_webhook_delivery.order_event'));
    }

    /**
     * A payment method and a gateway channel are bound one to one, and both directions are enforced by
     * the database: a method collects through exactly one channel, a channel is claimed by exactly one
     * method - otherwise the shopper's choice would make no difference.
     */
    public function testPaymentMethodGatewayChannelBindsBothSidesUniquely(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_method_gateway_channel`');

        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        $table = TableHelper::getTable($this->connection, 'payment_method_gateway_channel');
        static::assertSame(
            ['id', 'payment_method_id', 'payment_app_id', 'channel_technical_name', 'created_at', 'updated_at'],
            array_column($table->columns, 'name')
        );

        // one payment method may only collect through one channel - enforced by the database alone
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'payment_method_gateway_channel',
            'uniq.payment_method_gateway_channel.payment_method_id',
            ['payment_method_id']
        ));
        static::assertSame('UNIQUE', TableHelper::getIndexOfTable($this->connection, 'payment_method_gateway_channel', 'uniq.payment_method_gateway_channel.payment_method_id')->type);

        // and one channel of an app may only be claimed by one payment method
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'payment_method_gateway_channel',
            'uniq.payment_method_gateway_channel.channel',
            ['payment_app_id', 'channel_technical_name']
        ));
        static::assertSame('UNIQUE', TableHelper::getIndexOfTable($this->connection, 'payment_method_gateway_channel', 'uniq.payment_method_gateway_channel.channel')->type);

        // the binding is deleted together with the payment method it belongs to
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_method_gateway_channel', 'fk.payment_method_gateway_channel.payment_method_id'));
        static::assertSame('CASCADE', $this->deleteRuleOf('payment_method_gateway_channel', 'fk.payment_method_gateway_channel.payment_method_id'));

        // and with the app whose account it charges: an app that is gone must not leave a method
        // pointing at a channel nobody collects through any more
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_method_gateway_channel', 'fk.payment_method_gateway_channel.payment_app_id'));
        static::assertSame('CASCADE', $this->deleteRuleOf('payment_method_gateway_channel', 'fk.payment_method_gateway_channel.payment_app_id'));
    }

    /**
     * The dal cannot express a column default: it writes the initial value from the entity and never
     * emits a `DEFAULT`. A default in the ddl would therefore be permanent schema drift - every
     * `dal:migration:create` run would rewrite the column - so the ddl must not carry one either.
     */
    public function testTheDeliveryColumnsCarryNoDefaultTheDalCannotExpress(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_webhook_delivery`');

        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        foreach (['status', 'attempt_count'] as $column) {
            static::assertNull(
                TableHelper::getColumnOfTable($this->connection, 'payment_webhook_delivery', $column)->defaultValue,
                $column . ' must not declare a default the entity cannot express'
            );
        }
    }

    /**
     * A checkout order needs the payment order to point back at it, and only the payment order knows
     * which transaction it collects for. Without the references the paid event of the gateway has
     * nothing to write the state to.
     */
    public function testPaymentOrderReferencesTheCheckoutOrderAndItsTransaction(): void
    {
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        $columns = $this->columnsOf('payment_order');
        $appPosition = array_search('payment_app_id', $columns, true);
        static::assertIsInt($appPosition);

        // both references sit right behind the app, each with the version it points into - an app
        // order has none of them
        static::assertSame('order_id', $columns[$appPosition + 1]);
        static::assertSame('order_version_id', $columns[$appPosition + 2]);
        static::assertSame('order_transaction_id', $columns[$appPosition + 3]);
        static::assertSame('order_transaction_version_id', $columns[$appPosition + 4]);

        foreach (['order_id', 'order_version_id', 'order_transaction_id', 'order_transaction_version_id'] as $column) {
            static::assertFalse(
                TableHelper::getColumnOfTable($this->connection, 'payment_order', $column)->isNotNull,
                $column . ' must stay nullable, an app order has no checkout reference'
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'payment_order', 'idx.payment_order.order_id'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'payment_order', 'idx.payment_order.order_transaction_id'));

        // one checkout may reopen its payment, so the references are deliberately not unique - but a
        // payment order must not outlive the order it was opened for
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_order', 'fk.payment_order.order_id'));
        static::assertSame(['order_id', 'order_version_id'], $this->foreignKeyColumnsOf('payment_order', 'fk.payment_order.order_id'));
        static::assertSame('CASCADE', $this->deleteRuleOf('payment_order', 'fk.payment_order.order_id'));

        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_order', 'fk.payment_order.order_transaction_id'));
        static::assertSame(
            ['order_transaction_id', 'order_transaction_version_id'],
            $this->foreignKeyColumnsOf('payment_order', 'fk.payment_order.order_transaction_id')
        );
        static::assertSame('CASCADE', $this->deleteRuleOf('payment_order', 'fk.payment_order.order_transaction_id'));
    }

    /**
     * The type is what tells the tenant of the shop from an external app and keeps the payment api
     * from authenticating the former. It carries no default in the ddl for the same reason the
     * delivery columns carry none - the default would be a second, silently diverging source of truth.
     */
    public function testTheAppTypeTellsTheTenantsApartWithoutADefaultInTheDdl(): void
    {
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        $type = TableHelper::getColumnOfTable($this->connection, 'payment_app', 'type');
        static::assertTrue($type->isNotNull);
        static::assertSame(32, $type->length);
        static::assertNull($type->defaultValue, 'the type must not carry a default no caller has written');

        $columns = $this->columnsOf('payment_app');
        $namePosition = array_search('name', $columns, true);
        static::assertIsInt($namePosition);
        static::assertSame('type', $columns[$namePosition + 1]);

        static::assertTrue(TableHelper::indexExists($this->connection, 'payment_app', 'idx.payment_app.type'));
    }

    /**
     * Every column a caller always writes carries no default of its own: such a default would only
     * hide the one writer that forgot the value, and every `dal:migration:create` run would propose
     * to drop it.
     *
     * The one exception is a boolean: the dal emits `DEFAULT 0` for every boolean column
     * (`SchemaBuilder::$options`), so 0 is the only value that does not drift.
     */
    public function testTheColumnsTheCallersWriteCarryNoDefault(): void
    {
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        static::assertSame(
            '0',
            TableHelper::getColumnOfTable($this->connection, 'payment_app', 'active')->defaultValue,
            'the dal fixes the default of every boolean column to 0'
        );

        foreach (['paid_amount', 'refunded_amount'] as $column) {
            static::assertNull(
                TableHelper::getColumnOfTable($this->connection, 'payment_order', $column)->defaultValue,
                $column . ' is written explicitly by PaymentOrderService::create()'
            );
        }

        // the state foreign key is part of the model, not something the schema builder wants to drop
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_order', 'fk.payment_order.state_id'));
    }

    /**
     * The shop collects for its own checkout through a tenant that is seeded like the sandbox account:
     * without it the checkout cannot open a single payment order.
     *
     * The tenant alone cannot collect anything - an app without an authorized interface cannot open a
     * payment at all - so the interface of the one channel `payment_channel_config` switches on is
     * authorized for it in the same run, and that channel is offered to it. It is that one channel and
     * not every authorization the interfaces declare: seeding them all would bypass the merchant's
     * switch, and the channels an operator enables later are authorized on demand.
     */
    public function testSeedsThePlatformTenant(): void
    {
        // the seed is what is asserted here, not a row another process left behind: the migration
        // tests share one database, and a row written with another APP_SECRET cannot be decrypted
        // here. Its authorizations cascade with it.
        $this->connection->executeStatement(
            'DELETE FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME]
        );

        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        $app = $this->connection->fetchAssociative(
            'SELECT `id`, `name`, `type`, `active`, `app_secret` FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME]
        );
        static::assertIsArray($app);
        static::assertSame(PaymentAppEntity::TYPE_PLATFORM, $app['type']);
        static::assertSame(1, (int) $app['active']);

        // the secret exists because the column is not nullable, and it is sealed like every other one
        $secret = $this->cipher()->decryptValue((string) $app['app_secret']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);

        // the default app can collect right away: the interface is authorized for it, on the merchant
        // account of the platform
        $authorization = $this->connection->fetchAssociative(
            'SELECT `provider_technical_name`, `enabled`, `mode` FROM `payment_app_provider` WHERE `payment_app_id` = :app',
            ['app' => $app['id']]
        );
        static::assertIsArray($authorization);
        static::assertSame(Migration1791856510CreatePaymentTables::PROVIDER, $authorization['provider_technical_name']);
        static::assertSame(1, (int) $authorization['enabled']);
        static::assertSame(PaymentAppProviderEntity::MODE_PLATFORM, $authorization['mode']);

        // and exactly that one - a second authorization would mean the switch of the merchant was
        // bypassed somewhere
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `payment_app_provider` WHERE `payment_app_id` = :app',
            ['app' => $app['id']]
        ));

        $channel = $this->connection->fetchAssociative(
            'SELECT `channel_technical_name`, `enabled` FROM `payment_app_channel` WHERE `payment_app_id` = :app',
            ['app' => $app['id']]
        );
        static::assertIsArray($channel);
        static::assertSame(Migration1791856510CreatePaymentTables::CHANNEL, $channel['channel_technical_name']);
        static::assertSame(1, (int) $channel['enabled']);
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `payment_app_channel` WHERE `payment_app_id` = :app',
            ['app' => $app['id']]
        ));
    }

    /**
     * The tenant is looked up by its technical name, so a second row must not be writable - otherwise
     * the lookup and the seeded authorizations could point at two different rows.
     */
    public function testThePlatformTenantCannotBeSeededTwice(): void
    {
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->connection->insert('payment_app', [
            'id' => Uuid::randomBytes(),
            'technical_name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME,
            'name' => 'second tenant',
            'type' => PaymentAppEntity::TYPE_PLATFORM,
            'app_secret' => 'irrelevant',
            'active' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * The platform is usable right after the installation: the alipay sandbox account is seeded - the
     * credentials sealed the way the application seals them - the h5 channel is switched on, and both
     * are authorized for the platform tenant, which is the default app of the installation.
     */
    public function testSeedsTheAlipaySandboxAccountForThePlatformTenant(): void
    {
        // the seed is what is asserted here, not the row an earlier installation left behind: the
        // migration tests share one database, and a row that was seeded by an older version of this
        // migration still carries the names of that version. The authorizations cascade with it.
        $this->connection->executeStatement(
            'DELETE FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME]
        );

        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        $provider = $this->connection->fetchAssociative(
            'SELECT `enabled`, `credentials`, `settings` FROM `payment_provider_config` WHERE `provider_technical_name` = :provider',
            ['provider' => Migration1791856510CreatePaymentTables::PROVIDER]
        );
        static::assertIsArray($provider);
        static::assertSame(1, (int) $provider['enabled']);
        static::assertSame(['http_method' => 'GET'], json_decode((string) $provider['settings'], true, 512, \JSON_THROW_ON_ERROR));

        $credentials = $this->cipher()->decrypt(json_decode((string) $provider['credentials'], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame(Migration1791856510CreatePaymentTables::SANDBOX_APP_ID, $credentials[AlipayApi::APP_ID]);
        static::assertSame(Migration1791856510CreatePaymentTables::SANDBOX_GATEWAY_URL, $credentials[AlipayApi::GATEWAY_URL]);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT `enabled` FROM `payment_channel_config` WHERE `provider_technical_name` = :provider AND `channel_technical_name` = :channel',
            ['provider' => Migration1791856510CreatePaymentTables::PROVIDER, 'channel' => Migration1791856510CreatePaymentTables::CHANNEL]
        ));

        // the platform tenant is the default app of the installation, there is no seeded app beside it
        $app = $this->connection->fetchAssociative(
            'SELECT `id`, `name`, `type`, `active`, `app_secret` FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME]
        );
        static::assertIsArray($app);
        static::assertSame('平台支付', $app['name']);
        static::assertSame(PaymentAppEntity::TYPE_PLATFORM, $app['type']);
        static::assertSame(1, (int) $app['active']);

        // the secret exists because the column is not nullable, and it is sealed like every other one
        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->cipher()->decryptValue((string) $app['app_secret']));

        // among the names this migration ever writes, the platform is the only row - and there is no
        // external demo tenant beside it, an app of a caller is onboarded in the administration
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `payment_app` WHERE `technical_name` IN (:platform, :demo)',
            ['platform' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME, 'demo' => 'alipay_sandbox_demo']
        ));
        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => 'alipay_sandbox_demo']
        ));

        // the authorization is not lazy for the seeded channel: the default app has to be able to
        // collect the moment the installation is finished
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `payment_app_provider` WHERE `payment_app_id` = :app AND `provider_technical_name` = :provider',
            ['app' => $app['id'], 'provider' => Migration1791856510CreatePaymentTables::PROVIDER]
        ));
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `payment_app_channel` WHERE `payment_app_id` = :app AND `channel_technical_name` = :channel',
            ['app' => $app['id'], 'channel' => Migration1791856510CreatePaymentTables::CHANNEL]
        ));
    }

    /**
     * The migration runs on every upgrade, and the operator of the shop may have switched the platform
     * authorizations off in the meantime. A second run must neither write a second row (the unique keys
     * would make the migration fail) nor turn the switch back on - that would silently undo a decision
     * the merchant made on purpose.
     *
     * The migration tests share one database, so this one starts from a known state and leaves one
     * behind: the row is deleted first (its authorizations cascade with it) and at the end as well, and
     * every other test of this class seeds it again by running the migration.
     */
    public function testThePlatformAuthorizationsAreSeededOnceAndNotTouchedAgain(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME]
        );

        $migration = new Migration1791856510CreatePaymentTables();
        $migration->update($this->connection);

        $appId = $this->connection->fetchOne(
            'SELECT `id` FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME]
        );
        static::assertIsString($appId);

        $this->connection->update(
            'payment_app_provider',
            ['enabled' => 0, 'mode' => PaymentAppProviderEntity::MODE_OWN],
            ['payment_app_id' => $appId]
        );
        $this->connection->update('payment_app_channel', ['enabled' => 0], ['payment_app_id' => $appId]);

        $migration->update($this->connection);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `payment_app_provider` WHERE `payment_app_id` = :app',
            ['app' => $appId]
        ));
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `payment_app_channel` WHERE `payment_app_id` = :app',
            ['app' => $appId]
        ));

        $authorization = $this->connection->fetchAssociative(
            'SELECT `enabled`, `mode` FROM `payment_app_provider` WHERE `payment_app_id` = :app',
            ['app' => $appId]
        );
        static::assertIsArray($authorization);
        static::assertSame(0, (int) $authorization['enabled']);
        static::assertSame(PaymentAppProviderEntity::MODE_OWN, $authorization['mode']);

        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT `enabled` FROM `payment_app_channel` WHERE `payment_app_id` = :app',
            ['app' => $appId]
        ));

        // leave a fresh installation behind for the tests that follow
        $this->connection->executeStatement(
            'DELETE FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME]
        );
    }

    /**
     * The subscription state machines are imported beside the ones of the gateway. The cycle machine
     * carries the `unknown` state both mandate-driven providers force the model into: they accept a
     * charge asynchronously and forbid a second attempt while it runs, so only a query of the
     * channel may resolve what happened.
     */
    public function testMigrationImportsTheSubscriptionStateMachines(): void
    {
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        foreach ([
            PaymentSubscriptionStates::STATE_MACHINE => [
                PaymentSubscriptionStates::STATE_PENDING,
                PaymentSubscriptionStates::STATE_TRIALING,
                PaymentSubscriptionStates::STATE_ACTIVE,
                PaymentSubscriptionStates::STATE_PAST_DUE,
                PaymentSubscriptionStates::STATE_SUSPENDED,
                PaymentSubscriptionStates::STATE_CANCELED,
                PaymentSubscriptionStates::STATE_EXPIRED,
            ],
            PaymentMandateStates::STATE_MACHINE => [
                PaymentMandateStates::STATE_INIT,
                PaymentMandateStates::STATE_PENDING,
                PaymentMandateStates::STATE_ACTIVE,
                PaymentMandateStates::STATE_SUSPENDED,
                PaymentMandateStates::STATE_TERMINATED,
                PaymentMandateStates::STATE_EXPIRED,
            ],
            PaymentSubscriptionCycleStates::STATE_MACHINE => [
                PaymentSubscriptionCycleStates::STATE_SCHEDULED,
                PaymentSubscriptionCycleStates::STATE_SUBMITTED,
                PaymentSubscriptionCycleStates::STATE_PROCESSING,
                PaymentSubscriptionCycleStates::STATE_SUCCEEDED,
                PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE,
                PaymentSubscriptionCycleStates::STATE_FAILED_FINAL,
                PaymentSubscriptionCycleStates::STATE_UNKNOWN,
                PaymentSubscriptionCycleStates::STATE_REFUNDED,
                PaymentSubscriptionCycleStates::STATE_PARTIAL_REFUNDED,
                PaymentSubscriptionCycleStates::STATE_CLOSED,
            ],
        ] as $machine => $expectedStates) {
            static::assertSame(1, (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `state_machine` WHERE `technical_name` = :name',
                ['name' => $machine]
            ));

            $states = $this->connection->fetchFirstColumn(
                'SELECT `technical_name` FROM `state_machine_state` WHERE `state_machine_id` = (
                    SELECT `id` FROM `state_machine` WHERE `technical_name` = :name
                )',
                ['name' => $machine]
            );

            static::assertCount(\count($expectedStates), $states, 'the machine "' . $machine . '" carries an unexpected number of states');
            static::assertEqualsCanonicalizing($expectedStates, $states);
        }
    }

    /**
     * The final states of the subscription are final: neither `canceled` nor `expired` may leave the
     * machine, everything else can always be canceled.
     */
    public function testTheFinalSubscriptionStatesHaveNoOutgoingTransition(): void
    {
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        static::assertSame(0, $this->countOutgoingTransitions(PaymentSubscriptionStates::STATE_MACHINE, PaymentSubscriptionStates::STATE_CANCELED));
        static::assertSame(0, $this->countOutgoingTransitions(PaymentSubscriptionStates::STATE_MACHINE, PaymentSubscriptionStates::STATE_EXPIRED));
        static::assertSame(4, $this->countOutgoingTransitions(PaymentSubscriptionStates::STATE_MACHINE, PaymentSubscriptionStates::STATE_PENDING));
        static::assertSame(16, $this->countTransitionsOf(PaymentSubscriptionStates::STATE_MACHINE));
        static::assertSame(9, $this->countTransitionsOf(PaymentMandateStates::STATE_MACHINE));
        static::assertSame(17, $this->countTransitionsOf(PaymentSubscriptionCycleStates::STATE_MACHINE));
    }

    /**
     * The paths of an asynchronous charge and of a customer changing their mind: a synchronous
     * success never passes through `processing`, a failure the channel reports back after the
     * acceptance has to be able to leave `processing` - otherwise the cycle could neither be
     * retried nor closed and every reconciliation run would query it forever. An active mandate
     * the customer unsigns and a pending one the channel rejects are terminated, not expired -
     * only then can the platform tell a retry apart from a wasted one.
     */
    public function testTheAsynchronousChargeAndTerminationPathsExist(): void
    {
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        static::assertTrue($this->transitionExists(PaymentMandateStates::STATE_MACHINE, PaymentMandateStates::ACTION_TERMINATE, PaymentMandateStates::STATE_PENDING, PaymentMandateStates::STATE_TERMINATED));
        static::assertTrue($this->transitionExists(PaymentMandateStates::STATE_MACHINE, PaymentMandateStates::ACTION_TERMINATE, PaymentMandateStates::STATE_ACTIVE, PaymentMandateStates::STATE_TERMINATED));
        static::assertTrue($this->transitionExists(PaymentMandateStates::STATE_MACHINE, PaymentMandateStates::ACTION_RESUME, PaymentMandateStates::STATE_SUSPENDED, PaymentMandateStates::STATE_ACTIVE));

        static::assertTrue($this->transitionExists(PaymentSubscriptionCycleStates::STATE_MACHINE, PaymentSubscriptionCycleStates::ACTION_SUCCEED, PaymentSubscriptionCycleStates::STATE_SUBMITTED, PaymentSubscriptionCycleStates::STATE_SUCCEEDED));

        // one action name per destination: a single `fail` action pointing at both failure states
        // is ambiguous and the registry refuses it from 6.8 on
        foreach ([
            PaymentSubscriptionCycleStates::STATE_SUBMITTED,
            PaymentSubscriptionCycleStates::STATE_PROCESSING,
            PaymentSubscriptionCycleStates::STATE_UNKNOWN,
        ] as $from) {
            static::assertTrue($this->transitionExists(PaymentSubscriptionCycleStates::STATE_MACHINE, PaymentSubscriptionCycleStates::ACTION_FAIL_RETRYABLE, $from, PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE));
            static::assertTrue($this->transitionExists(PaymentSubscriptionCycleStates::STATE_MACHINE, PaymentSubscriptionCycleStates::ACTION_FAIL_FINAL, $from, PaymentSubscriptionCycleStates::STATE_FAILED_FINAL));
        }
    }

    /**
     * An action must resolve to exactly one destination state per origin: the registry looks the
     * destination up by (machine, action, from state) and deprecates an action that resolves to
     * several - from 6.8 it throws. The cycle machine once pointed `fail` at both failure states,
     * which silently sank every final failure into `failed_retryable`; this assertion is what keeps
     * another action from ever being declared that way again.
     */
    public function testAnActionNeverHasTwoDestinationsFromOneState(): void
    {
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        $ambiguous = $this->connection->fetchAllAssociative(
            'SELECT `machine`.`technical_name` AS machine,
                    `transition`.`action_name` AS action,
                    `transition`.`from_state_id` AS from_state,
                    COUNT(DISTINCT `transition`.`to_state_id`) AS destinations
                FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` IN (?, ?, ?)
                GROUP BY `machine`.`technical_name`, `transition`.`action_name`, `transition`.`from_state_id`
                HAVING destinations > 1',
            [
                PaymentSubscriptionCycleStates::STATE_MACHINE,
                PaymentSubscriptionStates::STATE_MACHINE,
                PaymentMandateStates::STATE_MACHINE,
            ]
        );

        static::assertSame([], $ambiguous, 'a state machine action must resolve to exactly one destination state');
    }

    /**
     * The plan is the root of the subscription model and its identity is its technical name - the
     * scheduler and the checkout look plans up by it.
     */
    public function testPaymentPlanTable(): void
    {
        // the plan is referenced by the mandate, which is referenced by the subscription, which is
        // referenced by the cycle - they have to go down in the reverse order they were built
        $this->dropSubscriptionTables();
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        $table = TableHelper::getTable($this->connection, 'payment_plan');
        static::assertSame(
            [
                'id', 'technical_name', 'sales_channel_id', 'billing_mode', 'provider', 'channel',
                'interval_unit', 'interval_count', 'amount', 'currency_id', 'trial_days',
                'provider_plan_ref', 'provider_plan_snapshot', 'created_at', 'updated_at',
            ],
            array_column($table->columns, 'name')
        );

        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_plan', 'uniq.payment_plan.technical_name', ['technical_name']));

        // the amount of one period carries no default the entity cannot express
        static::assertNull(TableHelper::getColumnOfTable($this->connection, 'payment_plan', 'amount')->defaultValue);

        // the sales channel and the currency are restricted, like the references of the checkout order
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_plan', 'fk.payment_plan.sales_channel_id'));
        static::assertSame('RESTRICT', $this->deleteRuleOf('payment_plan', 'fk.payment_plan.sales_channel_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_plan', 'fk.payment_plan.currency_id'));
        static::assertSame('RESTRICT', $this->deleteRuleOf('payment_plan', 'fk.payment_plan.currency_id'));
    }

    /**
     * The out agreement number of wechat can never be reused - not even after an unsigned agreement -
     * so it carries its own unique key: a re-signing must never collide with a terminated agreement.
     */
    public function testPaymentMandateTable(): void
    {
        $this->dropSubscriptionTables();
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_mandate', 'uniq.payment_mandate.agreement_no', ['agreement_no']));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_mandate', 'uniq.payment_mandate.out_agreement_no', ['out_agreement_no']));

        static::assertSame(
            '0',
            TableHelper::getColumnOfTable($this->connection, 'payment_mandate', 'reusable')->defaultValue,
            'the dal fixes the default of every boolean column to 0'
        );

        // the plan, the customer and the state are the lifetime owners of the agreement
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_mandate', 'fk.payment_mandate.plan_id'));
        static::assertSame('RESTRICT', $this->deleteRuleOf('payment_mandate', 'fk.payment_mandate.plan_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_mandate', 'fk.payment_mandate.customer_id'));
        static::assertSame('RESTRICT', $this->deleteRuleOf('payment_mandate', 'fk.payment_mandate.customer_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_mandate', 'fk.payment_mandate.status_id'));
        static::assertSame('RESTRICT', $this->deleteRuleOf('payment_mandate', 'fk.payment_mandate.status_id'));
    }

    /**
     * A channel managed subscription is located by its provider subscription id, a merchant initiated
     * one by its mandate - both references are unique or restricted, and the scheduler looks the due
     * subscriptions up by status and next billing date.
     */
    public function testPaymentSubscriptionTable(): void
    {
        $this->dropSubscriptionTables();
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_subscription', 'uniq.payment_subscription.subscription_no', ['subscription_no']));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_subscription', 'uniq.payment_subscription.provider_subscription', ['provider', 'provider_subscription_id']));

        static::assertTrue(TableHelper::indexExists($this->connection, 'payment_subscription', 'idx.payment_subscription.status_next_billing'));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_subscription', 'idx.payment_subscription.status_next_billing', ['status_id', 'next_billing_at']));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_subscription', 'idx.payment_subscription.customer_status', ['customer_id', 'status_id']));

        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_subscription', 'fk.payment_subscription.plan_id'));
        static::assertSame('RESTRICT', $this->deleteRuleOf('payment_subscription', 'fk.payment_subscription.plan_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_subscription', 'fk.payment_subscription.mandate_id'));
        static::assertSame('RESTRICT', $this->deleteRuleOf('payment_subscription', 'fk.payment_subscription.mandate_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_subscription', 'fk.payment_subscription.customer_id'));
        static::assertSame('RESTRICT', $this->deleteRuleOf('payment_subscription', 'fk.payment_subscription.customer_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_subscription', 'fk.payment_subscription.sales_channel_id'));
        static::assertSame('RESTRICT', $this->deleteRuleOf('payment_subscription', 'fk.payment_subscription.sales_channel_id'));
    }

    /**
     * One period is billed exactly once - the composite unique key keeps the scheduler and the
     * webhooks from booking a second cycle for the same period, and the out trade number makes the
     * retries of one cycle idempotent towards the channel.
     */
    public function testPaymentSubscriptionCycleTable(): void
    {
        $this->dropSubscriptionTables();
        (new Migration1791856510CreatePaymentTables())->update($this->connection);

        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'payment_subscription_cycle',
            'uniq.payment_subscription_cycle.subscription_cycle',
            ['subscription_id', 'cycle_no']
        ));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_subscription_cycle', 'uniq.payment_subscription_cycle.out_trade_no', ['out_trade_no']));

        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_subscription_cycle', 'idx.payment_subscription_cycle.state', ['state_id']));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'payment_subscription_cycle', 'idx.payment_subscription_cycle.next_retry', ['state_id', 'next_retry_at']));

        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_subscription_cycle', 'fk.payment_subscription_cycle.subscription_id'));
        static::assertSame('RESTRICT', $this->deleteRuleOf('payment_subscription_cycle', 'fk.payment_subscription_cycle.subscription_id'));
    }

    /**
     * The subscription tables are chained by foreign keys - plan, mandate, subscription, cycle - so
     * they only drop in the reverse order they were built in.
     */
    private function dropSubscriptionTables(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_subscription_cycle`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_subscription`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_mandate`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_plan`');
    }

    /**
     * @param non-empty-string $table
     *
     * @return list<string>
     */
    private function columnsOf(string $table): array
    {
        return array_column(TableHelper::getTable($this->connection, $table)->columns, 'name');
    }

    private function hasTransition(string $from, string $to): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine_state` `from_state` ON `from_state`.`id` = `transition`.`from_state_id`
                INNER JOIN `state_machine_state` `to_state` ON `to_state`.`id` = `transition`.`to_state_id`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` = :machine
                    AND `transition`.`action_name` = :action
                    AND `from_state`.`technical_name` = :from
                    AND `to_state`.`technical_name` = :to',
            [
                'machine' => PaymentOrderStates::STATE_MACHINE,
                'action' => PaymentOrderStates::ACTION_EXPIRE,
                'from' => $from,
                'to' => $to,
            ]
        );

        return (int) $count > 0;
    }

    private function transitionExists(string $machine, string $action, string $from, string $to): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine_state` `from_state` ON `from_state`.`id` = `transition`.`from_state_id`
                INNER JOIN `state_machine_state` `to_state` ON `to_state`.`id` = `transition`.`to_state_id`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` = :machine
                    AND `transition`.`action_name` = :action
                    AND `from_state`.`technical_name` = :from
                    AND `to_state`.`technical_name` = :to',
            ['machine' => $machine, 'action' => $action, 'from' => $from, 'to' => $to]
        );

        return (int) $count > 0;
    }

    private function countOutgoingTransitions(string $machine, string $from): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine_state` `from_state` ON `from_state`.`id` = `transition`.`from_state_id`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` = :machine
                    AND `from_state`.`technical_name` = :from',
            ['machine' => $machine, 'from' => $from]
        );
    }

    private function countTransitionsOf(string $machine): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` = :machine',
            ['machine' => $machine]
        );
    }

    private function deleteRuleOf(string $table, string $foreignKey): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT `DELETE_RULE` FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
                WHERE `CONSTRAINT_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `CONSTRAINT_NAME` = :foreignKey',
            ['table' => $table, 'foreignKey' => $foreignKey]
        );
    }

    /**
     * @return list<string>
     */
    private function foreignKeyColumnsOf(string $table, string $foreignKey): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT `COLUMN_NAME` FROM `information_schema`.`KEY_COLUMN_USAGE`
                WHERE `TABLE_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `CONSTRAINT_NAME` = :foreignKey
                ORDER BY `ORDINAL_POSITION`',
            ['table' => $table, 'foreignKey' => $foreignKey]
        );
    }

    private function cipher(): PaymentCredentialCipher
    {
        $secret = $_SERVER['APP_SECRET'] ?? $_ENV['APP_SECRET'] ?? getenv('APP_SECRET');
        static::assertIsString($secret);
        static::assertNotSame('', $secret);

        return new PaymentCredentialCipher($secret);
    }
}
