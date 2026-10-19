<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationException;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\Traits\StateMachineMigration;
use Shopwell\Core\Migration\Traits\StateMachineMigrationTrait;
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
 * Creates the state machines and the tables of the generic payment gateway in their final shape.
 *
 * The configuration of the gateway is a 2x2 matrix, and the tables follow it exactly:
 *
 *  - `payment_provider_config` is the platform account of a payment interface (alipay, wechat pay, …),
 *  - `payment_app_provider` is the authorization of an app for a whole interface, and it carries the
 *    mode - the platform account or the account the app brings itself,
 *  - `payment_channel_config` are the parameters of one channel of an interface (the appid of a wechat
 *    channel, for instance),
 *  - `payment_app_channel` is the switch of one channel for one app.
 *
 * The credentials live on the interface and never on a single product of it: an alipay application
 * covers every product of alipay, so the mobile web payment and the qr code payment share the very same
 * keys. Adding a product therefore never duplicates a key.
 *
 * The rest of the tables is the bookkeeping: the payment orders, their attempts, the refunds, the
 * incoming notifications, the outgoing webhook deliveries and the payum tokens of the running
 * checkouts.
 *
 * There are two kinds of tenants: an external app creates its orders through the api and carries its
 * own out trade number, while the orders of the shop itself reference the checkout order and its
 * transaction - that reference is what the gateway writes the paid state back through. The tenant of
 * the shop is seeded below, the app of an external caller is onboarded in the administration.
 *
 * The state machines are imported first, because the state fields of the bookkeeping tables reference
 * them.
 *
 * ⚠️ The seeded credentials below are alipay's *public sandbox* test account: they are deliberately
 * not a secret, but they must be replaced with the real merchant credentials before this channel is
 * used in production, and the demo app has to be removed. The seeded key pair is a locally generated
 * placeholder: it is complete and signs and verifies against itself, but it belongs to no alipay app,
 * so both keys have to be swapped for the ones of the real sandbox app in the alipay console.
 *
 * @internal
 */
#[Package('framework')]
class Migration1791856510CreatePaymentTables extends MigrationStep
{
    use StateMachineMigrationTrait;

    final public const PROVIDER = AlipayApi::PROVIDER;

    final public const CHANNEL = AlipayApi::CHANNEL_H5;

    /**
     * The app id of the sandbox app of the alipay open platform.
     */
    final public const SANDBOX_APP_ID = '9021000149657375';

    final public const SANDBOX_GATEWAY_URL = 'https://openapi-sandbox.dl.alipaydev.com';

    /**
     * The private key of the sandbox app, used to sign the requests: the pkcs#8 body without the pem
     * envelope, the way `AlipayApi::wrapPem()` expects it.
     */
    final public const SANDBOX_PRIVATE_KEY = 'MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQCx1O874e6cAzTwOqU2Ri/8ZaGu1WdvjZ8MaiMgNAIR1SZh7yJ/+HcC2ijpMPq0x1+FoCWz0u/haCHNJFWL36fPaLILJTOicN9EwqdQCT0JbYQw1TOL+V+G9/tq2YFuizwVyRJ+ua2qC0JZoT9cqfVZTxTQJ++H++pJ9XjJtTHyEXwAl89xYI1KjYu1S5LmPtuLjpx+xmcg9S+vZdYxFW3yvRHrsvZI4ECSNi0A27cpbPSqucq7w2cjfhistPjTdew4+h05p2liC9XYqfnNRfv8r3iufyP/ovVMFUEDtScIveyX2Lb+g1l6Afp/5o369vI3JLHUAI+cYF6UtD6WtiZtAgMBAAECggEABMed5ISmlq/pYNmarjJFwOl0ZpAa1x892qYRqqBrO9J4VSKJ0Vm6zStS9S2ZV3mSYjOTP9mYE/1qBysGV5ub1ZHRU0ZdNG0HO0BmIdsVtmaoqm1YlNcPj7+jlNQiy/dXFpjQc/KGre7eii0w2EtDKnLbMtsX75ZATluYMwe5P3HYSnmx63jtO+MRlCcLO/orrmMzxS1hQ/jg9CnxDoGlsDFheO7bUROx/VH110iB9X61j08FQyiRex2e0Y5z/nvBByYPz3o+fDnNuIwRU+MSGX/fhRQ83X49EzjB1xSYMwXgK7fJdVziqySXMrwsetGCAgMqR32XjQ6sr+jmzbzE4QKBgQDzzQLOXrB0EHD74XUe060y9U8Zv14dq23KDLnhiHQPbmLUrqheTDbOIWLcuNr3tEHvS0MGlYJdkKZpRRK404rykq9u7BR+8F8eHsBdEBK10eSE/YdzuFjB2/IpkZ/NzaCZqo0Uj+9m4en1yS6bJPzhq2zkrBSuOw93XjD82Rkv/QKBgQC6uuMP31X2qlXmVvsX224HjVPC7b78Kidqt0BOaG/tsOLmES0lZ0KdrKLlIxxl7wDd/FYzqiqQRLZp+9Tj8SF4+TCZlnMFaEJFTMzIRa45zcj8tW9y/PAond4yIy2mmjj7NM3l1/chmr6pwxcb5JWX/bRqmV7nUSbLXq8LAOQDMQKBgQCmxRAsVr1hR3Xh9sGFCsspVoB7Xlpk9UUutzHXwt2RSLM2RRtLFdtPeasEzTaLxrRrmhIJSPBaTH5o+CTsZVhgwlJbEAadJpQKKILxpfMGiMNG7Z5gXDPquCeYo9KqlwRK2U2USTk8KUE/LhVBbzXEsNzWN5gV7rSPtNDRxTfjiQKBgQCD3vX6QKRJ9hOB/02GlK3I4ZMU56foF7EMmWvts63QNAqZpem3L4yJI+FKZxhoGHkJTGpoRDWY+x3EDVhUvfcAoyMiZHDIkc+zd0Br82HOarkVVuDlWSIV3R+NUvCrZuD07161gmaCMfGSnDdWuIC4/TAL1FKEk/gMycpev/tHEQKBgQCqwyrCe/VvYcBkD3LWeKfcH19QHv4gcc8fyPTYSFlqUeXJLi0wO0qs+SbrLJBkdf1LoltmyWu4X6Z5OtyRXf90NA3xP0FXFXUIE+SSveG/5XcCWG3SR31yAE9ZcvzfGydD+MYFOc28Q2XhCCXbVxQkHKY4fGLFiuIetrjbg+35/w==';

    /**
     * The public key of alipay itself, used to verify the notifications: the spki body without the pem
     * envelope.
     */
    final public const SANDBOX_ALIPAY_PUBLIC_KEY = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsdTvO+HunAM08DqlNkYv/GWhrtVnb42fDGojIDQCEdUmYe8if/h3Atoo6TD6tMdfhaAls9Lv4WghzSRVi9+nz2iyCyUzonDfRMKnUAk9CW2EMNUzi/lfhvf7atmBbos8FckSfrmtqgtCWaE/XKn1WU8U0Cfvh/vqSfV4ybUx8hF8AJfPcWCNSo2LtUuS5j7bi46cfsZnIPUvr2XWMRVt8r0R67L2SOBAkjYtANu3KWz0qrnKu8NnI34YrLT403XsOPodOadpYgvV2Kn5zUX7/K94rn8j/6L1TBVBA7UnCL3sl9i2/oNZegH6f+aN+vbyNySx1ACPnGBelLQ+lrYmbQIDAQAB';

    final public const DEMO_APP_TECHNICAL_NAME = 'alipay_sandbox_demo';

    final public const DEMO_APP_SECRET = 'sandbox-only-app-secret';

    public function getCreationTimestamp(): int
    {
        return 1791856510;
    }

    public function update(Connection $connection): void
    {
        $this->importPaymentOrderStateMachine($connection);
        $this->importPaymentRefundStateMachine($connection);
        $this->importPaymentSubscriptionStateMachines($connection);
        $this->removeAmbiguousFailureTransitionsOfCycles($connection);
        $this->removeExpireTransitionOfOpenedOrders($connection);

        $this->createPaymentAppTable($connection);
        $this->createPaymentProviderConfigTable($connection);
        $this->createPaymentChannelConfigTable($connection);
        $this->createPaymentAppProviderTable($connection);
        $this->createPaymentAppChannelTable($connection);
        $this->createPaymentOrderTable($connection);
        $this->createPaymentGatewayTokenTable($connection);
        $this->createPaymentTransactionTable($connection);
        $this->createPaymentRefundTable($connection);
        $this->createPaymentNotificationTable($connection);
        $this->createPaymentWebhookDeliveryTable($connection);
        $this->createPaymentMethodGatewayChannelTable($connection);
        $this->createPaymentPlanTable($connection);
        $this->createPaymentMandateTable($connection);
        $this->createPaymentSubscriptionTable($connection);
        $this->createPaymentSubscriptionCycleTable($connection);

        // the demo sandbox account is the reason the api can be called right after the installation
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $cipher = $this->cipher();

        $this->seedProviderConfig($connection, $cipher, $now);
        $this->seedChannelConfig($connection, $now);
        $this->seedDemoApp($connection, $cipher, $now);
        $this->seedPlatformTenant($connection, $cipher, $now);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }

    private function importPaymentOrderStateMachine(Connection $connection): void
    {
        $this->import(
            new StateMachineMigration(
                PaymentOrderStates::STATE_MACHINE,
                '支付单状态',
                'Payment order state',
                [
                    StateMachineMigration::state(PaymentOrderStates::STATE_CREATED, '已创建', 'Created'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_OPENED, '已唤起支付', 'Opened'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_PAID, '已支付', 'Paid'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_FAILED, '支付失败', 'Failed'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_CLOSED, '已关闭', 'Closed'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_EXPIRED, '已过期', 'Expired'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_REFUNDED_PARTIALLY, '部分退款', 'Partially refunded'),
                    StateMachineMigration::state(PaymentOrderStates::STATE_REFUNDED, '已全额退款', 'Refunded'),
                ],
                [
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_PROCESS, PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_OPENED),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_PAY, PaymentOrderStates::STATE_OPENED, PaymentOrderStates::STATE_PAID),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_FAIL, PaymentOrderStates::STATE_OPENED, PaymentOrderStates::STATE_FAILED),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_CLOSE, PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_CLOSED),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_CLOSE, PaymentOrderStates::STATE_OPENED, PaymentOrderStates::STATE_CLOSED),
                    // an order the app never got pay parameters for may simply run out of time. One that
                    // was handed to the channel is never expired by the platform: the channel may report
                    // a late payment, and losing that payment would be worse than a lingering order.
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_EXPIRE, PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_EXPIRED),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_REFUND_PARTIALLY, PaymentOrderStates::STATE_PAID, PaymentOrderStates::STATE_REFUNDED_PARTIALLY),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_REFUND_PARTIALLY, PaymentOrderStates::STATE_REFUNDED_PARTIALLY, PaymentOrderStates::STATE_REFUNDED_PARTIALLY),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_REFUND, PaymentOrderStates::STATE_PAID, PaymentOrderStates::STATE_REFUNDED),
                    StateMachineMigration::transition(PaymentOrderStates::ACTION_REFUND, PaymentOrderStates::STATE_REFUNDED_PARTIALLY, PaymentOrderStates::STATE_REFUNDED),
                ],
                PaymentOrderStates::STATE_CREATED,
            ),
            $connection
        );
    }

    private function importPaymentRefundStateMachine(Connection $connection): void
    {
        $this->import(
            new StateMachineMigration(
                PaymentRefundStates::STATE_MACHINE,
                '支付退款状态',
                'Payment refund state',
                [
                    StateMachineMigration::state(PaymentRefundStates::STATE_OPEN, '已申请', 'Open'),
                    StateMachineMigration::state(PaymentRefundStates::STATE_PROCESSING, '退款中', 'Processing'),
                    StateMachineMigration::state(PaymentRefundStates::STATE_FINISHED, '退款成功', 'Finished'),
                    StateMachineMigration::state(PaymentRefundStates::STATE_FAILED, '退款失败', 'Failed'),
                ],
                [
                    StateMachineMigration::transition(PaymentRefundStates::ACTION_PROCESS, PaymentRefundStates::STATE_OPEN, PaymentRefundStates::STATE_PROCESSING),
                    StateMachineMigration::transition(PaymentRefundStates::ACTION_FINISH, PaymentRefundStates::STATE_PROCESSING, PaymentRefundStates::STATE_FINISHED),
                    StateMachineMigration::transition(PaymentRefundStates::ACTION_FINISH, PaymentRefundStates::STATE_OPEN, PaymentRefundStates::STATE_FINISHED),
                    StateMachineMigration::transition(PaymentRefundStates::ACTION_FAIL, PaymentRefundStates::STATE_PROCESSING, PaymentRefundStates::STATE_FAILED),
                    StateMachineMigration::transition(PaymentRefundStates::ACTION_FAIL, PaymentRefundStates::STATE_OPEN, PaymentRefundStates::STATE_FAILED),
                ],
                PaymentRefundStates::STATE_OPEN,
            ),
            $connection
        );
    }

    /**
     * The subscription state machines. `canceled` and `expired` of a subscription are final and have
     * no outgoing transition; the cycle machine carries an `unknown` state that only a query of the
     * channel may resolve - both mandate-driven providers accept a charge asynchronously and forbid
     * a second attempt while it is running, so guessing the outcome would either double charge or
     * ship without money.
     */
    private function importPaymentSubscriptionStateMachines(Connection $connection): void
    {
        $this->import(
            new StateMachineMigration(
                PaymentSubscriptionStates::STATE_MACHINE,
                '订阅状态',
                'Subscription status',
                [
                    StateMachineMigration::state(PaymentSubscriptionStates::STATE_PENDING, '待生效', 'Pending'),
                    StateMachineMigration::state(PaymentSubscriptionStates::STATE_TRIALING, '试用中', 'Trialing'),
                    StateMachineMigration::state(PaymentSubscriptionStates::STATE_ACTIVE, '生效中', 'Active'),
                    StateMachineMigration::state(PaymentSubscriptionStates::STATE_PAST_DUE, '逾期未付', 'Past due'),
                    StateMachineMigration::state(PaymentSubscriptionStates::STATE_SUSPENDED, '已暂停', 'Suspended'),
                    StateMachineMigration::state(PaymentSubscriptionStates::STATE_CANCELED, '已取消', 'Canceled'),
                    StateMachineMigration::state(PaymentSubscriptionStates::STATE_EXPIRED, '已到期', 'Expired'),
                ],
                [
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_TRIAL, PaymentSubscriptionStates::STATE_PENDING, PaymentSubscriptionStates::STATE_TRIALING),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_ACTIVATE, PaymentSubscriptionStates::STATE_PENDING, PaymentSubscriptionStates::STATE_ACTIVE),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_ACTIVATE, PaymentSubscriptionStates::STATE_TRIALING, PaymentSubscriptionStates::STATE_ACTIVE),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_ACTIVATE, PaymentSubscriptionStates::STATE_PAST_DUE, PaymentSubscriptionStates::STATE_ACTIVE),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_ACTIVATE, PaymentSubscriptionStates::STATE_SUSPENDED, PaymentSubscriptionStates::STATE_ACTIVE),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_FAIL, PaymentSubscriptionStates::STATE_TRIALING, PaymentSubscriptionStates::STATE_PAST_DUE),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_FAIL, PaymentSubscriptionStates::STATE_ACTIVE, PaymentSubscriptionStates::STATE_PAST_DUE),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_SUSPEND, PaymentSubscriptionStates::STATE_ACTIVE, PaymentSubscriptionStates::STATE_SUSPENDED),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_SUSPEND, PaymentSubscriptionStates::STATE_PAST_DUE, PaymentSubscriptionStates::STATE_SUSPENDED),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_CANCEL, PaymentSubscriptionStates::STATE_PENDING, PaymentSubscriptionStates::STATE_CANCELED),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_CANCEL, PaymentSubscriptionStates::STATE_TRIALING, PaymentSubscriptionStates::STATE_CANCELED),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_CANCEL, PaymentSubscriptionStates::STATE_ACTIVE, PaymentSubscriptionStates::STATE_CANCELED),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_CANCEL, PaymentSubscriptionStates::STATE_PAST_DUE, PaymentSubscriptionStates::STATE_CANCELED),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_CANCEL, PaymentSubscriptionStates::STATE_SUSPENDED, PaymentSubscriptionStates::STATE_CANCELED),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_EXPIRE, PaymentSubscriptionStates::STATE_PENDING, PaymentSubscriptionStates::STATE_EXPIRED),
                    StateMachineMigration::transition(PaymentSubscriptionStates::ACTION_EXPIRE, PaymentSubscriptionStates::STATE_ACTIVE, PaymentSubscriptionStates::STATE_EXPIRED),
                ],
                PaymentSubscriptionStates::STATE_PENDING,
            ),
            $connection
        );

        $this->import(
            new StateMachineMigration(
                PaymentMandateStates::STATE_MACHINE,
                '签约协议状态',
                'Payment mandate status',
                [
                    StateMachineMigration::state(PaymentMandateStates::STATE_INIT, '已创建', 'Created'),
                    StateMachineMigration::state(PaymentMandateStates::STATE_PENDING, '签约中', 'Pending'),
                    StateMachineMigration::state(PaymentMandateStates::STATE_ACTIVE, '生效中', 'Active'),
                    StateMachineMigration::state(PaymentMandateStates::STATE_SUSPENDED, '已暂停', 'Suspended'),
                    StateMachineMigration::state(PaymentMandateStates::STATE_TERMINATED, '已解约', 'Terminated'),
                    StateMachineMigration::state(PaymentMandateStates::STATE_EXPIRED, '已过期', 'Expired'),
                ],
                [
                    StateMachineMigration::transition(PaymentMandateStates::ACTION_SUBMIT, PaymentMandateStates::STATE_INIT, PaymentMandateStates::STATE_PENDING),
                    StateMachineMigration::transition(PaymentMandateStates::ACTION_ACTIVATE, PaymentMandateStates::STATE_PENDING, PaymentMandateStates::STATE_ACTIVE),
                    StateMachineMigration::transition(PaymentMandateStates::ACTION_SUSPEND, PaymentMandateStates::STATE_ACTIVE, PaymentMandateStates::STATE_SUSPENDED),
                    StateMachineMigration::transition(PaymentMandateStates::ACTION_RESUME, PaymentMandateStates::STATE_SUSPENDED, PaymentMandateStates::STATE_ACTIVE),
                    StateMachineMigration::transition(PaymentMandateStates::ACTION_TERMINATE, PaymentMandateStates::STATE_PENDING, PaymentMandateStates::STATE_TERMINATED),
                    StateMachineMigration::transition(PaymentMandateStates::ACTION_TERMINATE, PaymentMandateStates::STATE_ACTIVE, PaymentMandateStates::STATE_TERMINATED),
                    StateMachineMigration::transition(PaymentMandateStates::ACTION_TERMINATE, PaymentMandateStates::STATE_SUSPENDED, PaymentMandateStates::STATE_TERMINATED),
                    StateMachineMigration::transition(PaymentMandateStates::ACTION_EXPIRE, PaymentMandateStates::STATE_PENDING, PaymentMandateStates::STATE_EXPIRED),
                    StateMachineMigration::transition(PaymentMandateStates::ACTION_EXPIRE, PaymentMandateStates::STATE_ACTIVE, PaymentMandateStates::STATE_EXPIRED),
                ],
                PaymentMandateStates::STATE_INIT,
            ),
            $connection
        );

        $this->import(
            new StateMachineMigration(
                PaymentSubscriptionCycleStates::STATE_MACHINE,
                '订阅期次状态',
                'Subscription cycle state',
                [
                    StateMachineMigration::state(PaymentSubscriptionCycleStates::STATE_SCHEDULED, '待扣款', 'Scheduled'),
                    StateMachineMigration::state(PaymentSubscriptionCycleStates::STATE_SUBMITTED, '已提交', 'Submitted'),
                    StateMachineMigration::state(PaymentSubscriptionCycleStates::STATE_PROCESSING, '扣款处理中', 'Processing'),
                    StateMachineMigration::state(PaymentSubscriptionCycleStates::STATE_SUCCEEDED, '扣款成功', 'Succeeded'),
                    StateMachineMigration::state(PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE, '失败待重试', 'Failed, retryable'),
                    StateMachineMigration::state(PaymentSubscriptionCycleStates::STATE_FAILED_FINAL, '最终失败', 'Failed finally'),
                    StateMachineMigration::state(PaymentSubscriptionCycleStates::STATE_UNKNOWN, '结果未知', 'Unknown'),
                    StateMachineMigration::state(PaymentSubscriptionCycleStates::STATE_REFUNDED, '已退款', 'Refunded'),
                    StateMachineMigration::state(PaymentSubscriptionCycleStates::STATE_PARTIAL_REFUNDED, '部分退款', 'Partially refunded'),
                    StateMachineMigration::state(PaymentSubscriptionCycleStates::STATE_CLOSED, '已关闭', 'Closed'),
                ],
                [
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_SUBMIT, PaymentSubscriptionCycleStates::STATE_SCHEDULED, PaymentSubscriptionCycleStates::STATE_SUBMITTED),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_PROCESS, PaymentSubscriptionCycleStates::STATE_SUBMITTED, PaymentSubscriptionCycleStates::STATE_PROCESSING),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_SUCCEED, PaymentSubscriptionCycleStates::STATE_SUBMITTED, PaymentSubscriptionCycleStates::STATE_SUCCEEDED),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_SUCCEED, PaymentSubscriptionCycleStates::STATE_PROCESSING, PaymentSubscriptionCycleStates::STATE_SUCCEEDED),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_SUCCEED, PaymentSubscriptionCycleStates::STATE_UNKNOWN, PaymentSubscriptionCycleStates::STATE_SUCCEEDED),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_FAIL_RETRYABLE, PaymentSubscriptionCycleStates::STATE_SUBMITTED, PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_FAIL_FINAL, PaymentSubscriptionCycleStates::STATE_SUBMITTED, PaymentSubscriptionCycleStates::STATE_FAILED_FINAL),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_FAIL_RETRYABLE, PaymentSubscriptionCycleStates::STATE_PROCESSING, PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_FAIL_FINAL, PaymentSubscriptionCycleStates::STATE_PROCESSING, PaymentSubscriptionCycleStates::STATE_FAILED_FINAL),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_FAIL_RETRYABLE, PaymentSubscriptionCycleStates::STATE_UNKNOWN, PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_FAIL_FINAL, PaymentSubscriptionCycleStates::STATE_UNKNOWN, PaymentSubscriptionCycleStates::STATE_FAILED_FINAL),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_MARK_UNKNOWN, PaymentSubscriptionCycleStates::STATE_SUBMITTED, PaymentSubscriptionCycleStates::STATE_UNKNOWN),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_MARK_UNKNOWN, PaymentSubscriptionCycleStates::STATE_PROCESSING, PaymentSubscriptionCycleStates::STATE_UNKNOWN),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_RESCHEDULE, PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE, PaymentSubscriptionCycleStates::STATE_SCHEDULED),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_REFUND, PaymentSubscriptionCycleStates::STATE_SUCCEEDED, PaymentSubscriptionCycleStates::STATE_REFUNDED),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_REFUND_PARTIALLY, PaymentSubscriptionCycleStates::STATE_SUCCEEDED, PaymentSubscriptionCycleStates::STATE_PARTIAL_REFUNDED),
                    StateMachineMigration::transition(PaymentSubscriptionCycleStates::ACTION_CLOSE, PaymentSubscriptionCycleStates::STATE_FAILED_FINAL, PaymentSubscriptionCycleStates::STATE_CLOSED),
                ],
                PaymentSubscriptionCycleStates::STATE_SCHEDULED,
            ),
            $connection
        );
    }

    /**
     * An order that was handed to the channel may still be paid there, so the platform never expires it.
     *
     * The transition is an error of an intermediate version of this migration: the importer above only
     * adds the states and transitions a machine is missing, it never removes one. This statement
     * converges those installations on the final state machine, and it is a no-op on a fresh one, where
     * the transition was never declared. It is matched on the whole tuple - machine, action and both
     * states - because `expire` is an action the refund machine may use as well.
     */
    private function removeExpireTransitionOfOpenedOrders(Connection $connection): void
    {
        $connection->executeStatement(
            'DELETE `transition` FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                INNER JOIN `state_machine_state` `from_state` ON `from_state`.`id` = `transition`.`from_state_id`
                INNER JOIN `state_machine_state` `to_state` ON `to_state`.`id` = `transition`.`to_state_id`
                WHERE `machine`.`technical_name` = :machine
                    AND `transition`.`action_name` = :action
                    AND `from_state`.`technical_name` = :from
                    AND `to_state`.`technical_name` = :to',
            [
                'machine' => PaymentOrderStates::STATE_MACHINE,
                'action' => PaymentOrderStates::ACTION_EXPIRE,
                'from' => PaymentOrderStates::STATE_OPENED,
                'to' => PaymentOrderStates::STATE_EXPIRED,
            ]
        );
    }

    /**
     * Removes the retired `fail` action of the cycle machine.
     *
     * The action once pointed at both failure states, which makes it ambiguous: the registry
     * resolves an action to exactly one destination and already deprecates - from 6.8 throwing on -
     * an action that resolves to several. It has been split into `fail_retryable` and `fail_final`.
     *
     * The importer above only adds what a machine is missing, it never removes a transition, so an
     * installation that saw the ambiguous version keeps those rows beside the new ones. They are
     * the leftovers of a baseline that was never released, and leaving them behind would leave the
     * machine with an action the registry can no longer resolve. Deleting by machine and action
     * name is idempotent: on a fresh installation there is nothing to delete.
     */
    private function removeAmbiguousFailureTransitionsOfCycles(Connection $connection): void
    {
        $connection->executeStatement(
            'DELETE FROM `state_machine_transition`
                WHERE `state_machine_id` IN (
                    SELECT `id` FROM `state_machine` WHERE `technical_name` = :machine
                )
                    AND `action_name` = :action',
            [
                'machine' => PaymentSubscriptionCycleStates::STATE_MACHINE,
                // the retired name on purpose: this is exactly what must not exist anymore
                'action' => 'fail',
            ]
        );
    }

    /**
     * The apps that collect through the gateway. `type` tells the two kinds apart: an `external` app
     * calls the payment api and signs its requests, the `platform` tenant is the shop itself and is
     * only ever used in process.
     *
     * Neither column carries a default of its own: `type` has none at all, and `active` carries the
     * one the dal emits for every boolean column - `SchemaBuilder::$options` fixes it to 0, so any
     * other value would be schema drift. Every writer passes both values explicitly anyway, the ddl
     * default only decides what a row inserted outside the dal starts as.
     */
    private function createPaymentAppTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_app` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(64) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `type` VARCHAR(32) NOT NULL,
                `app_secret` VARCHAR(255) NOT NULL,
                `previous_app_secret` VARCHAR(255) NULL,
                `secret_rotated_at` DATETIME(3) NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 0,
                `notify_url` VARCHAR(1024) NULL,
                `return_url` VARCHAR(1024) NULL,
                `allowed_ips` JSON NULL,
                `rate_limit` INT NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_app.technical_name` (`technical_name`),
                KEY `idx.payment_app.type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The platform account of an interface. One row per interface and not per channel: rotating a key
     * is a single row, and no product of the interface can end up with a different key than its sibling.
     */
    private function createPaymentProviderConfigTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_provider_config` (
                `id` BINARY(16) NOT NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `credentials` JSON NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_provider_config.provider` (`provider_technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The parameters of one channel of an interface, the appid of a wechat channel for instance. They
     * belong to the channel and not to the interface, because the h5 channel and the jsapi channel of
     * wechat are bound to different appids of the very same merchant account.
     */
    private function createPaymentChannelConfigTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_channel_config` (
                `id` BINARY(16) NOT NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `channel_technical_name` VARCHAR(64) NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `params` JSON NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_channel_config.provider_channel` (`provider_technical_name`, `channel_technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * Which interface an app may collect through, and whose merchant account is charged.
     */
    private function createPaymentAppProviderTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_app_provider` (
                `id` BINARY(16) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `mode` VARCHAR(32) NOT NULL DEFAULT \'platform\',
                `credentials` JSON NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_app_provider.app_provider` (`payment_app_id`, `provider_technical_name`),
                KEY `idx.payment_app_provider.provider` (`provider_technical_name`),
                CONSTRAINT `fk.payment_app_provider.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * Which channels of those interfaces the app offers to its own users. A channel is only usable when
     * its interface is authorized as well, which is why the two authorizations are two tables.
     */
    private function createPaymentAppChannelTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_app_channel` (
                `id` BINARY(16) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `channel_technical_name` VARCHAR(64) NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `params` JSON NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_app_channel.app_channel` (`payment_app_id`, `channel_technical_name`),
                KEY `idx.payment_app_channel.channel` (`channel_technical_name`),
                CONSTRAINT `fk.payment_app_channel.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The bookkeeping of one payment. An order of an external app is complete without the order
     * references - the app brings its own out trade number. The shop's own orders are the other way
     * round: the payment order exists for one order transaction and carries both references, so the
     * paid event can be written back to the checkout and a refund can find the payment again.
     *
     * The references come in pairs, because the order and its transaction are versioned: a foreign
     * key into them always spans the id and the version, exactly like the transaction of an order
     * references the order. Both pairs stay empty for the order of an app.
     *
     * There is deliberately no unique key on the references: one checkout may open a second payment
     * order when the first one timed out or failed, and both are real attempts of the same order.
     * The references are deleted together with the order, like the transactions of the order itself.
     */
    private function createPaymentOrderTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_order` (
                `id` BINARY(16) NOT NULL,
                `payment_no` VARCHAR(64) NOT NULL,
                `out_trade_no` VARCHAR(128) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NULL,
                `order_version_id` BINARY(16) NULL,
                `order_transaction_id` BINARY(16) NULL,
                `order_transaction_version_id` BINARY(16) NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `channel_technical_name` VARCHAR(64) NOT NULL,
                `channel_params` JSON NULL,
                `amount` INT NOT NULL,
                `paid_amount` INT NOT NULL,
                `refunded_amount` INT NOT NULL,
                `currency` VARCHAR(3) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `body` VARCHAR(512) NULL,
                `attach` VARCHAR(1024) NULL,
                `state_id` BINARY(16) NOT NULL,
                `out_payment_no` VARCHAR(128) NULL,
                `channel_data` JSON NULL,
                `client_ip` VARCHAR(45) NULL,
                `device` VARCHAR(32) NULL,
                `notify_url` VARCHAR(1024) NULL,
                `return_url` VARCHAR(1024) NULL,
                `idempotency_key` VARCHAR(128) NULL,
                `expire_at` DATETIME(3) NULL,
                `paid_at` DATETIME(3) NULL,
                `closed_at` DATETIME(3) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_order.payment_no` (`payment_no`),
                UNIQUE KEY `uniq.payment_order.app_out_trade_no` (`payment_app_id`, `out_trade_no`),
                UNIQUE KEY `uniq.payment_order.app_idempotency` (`payment_app_id`, `idempotency_key`),
                KEY `idx.payment_order.provider` (`provider_technical_name`),
                KEY `idx.payment_order.channel` (`channel_technical_name`),
                KEY `idx.payment_order.state_id` (`state_id`),
                KEY `idx.payment_order.paid_at` (`paid_at`),
                KEY `idx.payment_order.expire_at` (`expire_at`),
                KEY `idx.payment_order.order_id` (`order_id`, `order_version_id`),
                KEY `idx.payment_order.order_transaction_id` (`order_transaction_id`, `order_transaction_version_id`),
                CONSTRAINT `fk.payment_order.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_order.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_order.order_id` FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_order.order_transaction_id` FOREIGN KEY (`order_transaction_id`, `order_transaction_version_id`) REFERENCES `order_transaction` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The payum tokens of the running checkouts. A token is the reference an interface redirects the
     * shopper back to, so it has to be found by its hash on every node - hence the unique index.
     *
     * The token is a dependent row: it grants access to one order and must not survive it, so the
     * reference is declared with a cascade delete - like the transactions and the notifications of an
     * order, and unlike the app, which is the owner of the order.
     */
    private function createPaymentGatewayTokenTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_gateway_token` (
                `id` BINARY(16) NOT NULL,
                `hash` VARCHAR(128) NOT NULL,
                `order_id` BINARY(16) NULL,
                `target_url` VARCHAR(2048) NULL,
                `after_url` VARCHAR(2048) NULL,
                `gateway_name` VARCHAR(64) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_gateway_token.hash` (`hash`),
                KEY `idx.payment_gateway_token.order_id` (`order_id`),
                CONSTRAINT `fk.payment_gateway_token.order_id` FOREIGN KEY (`order_id`) REFERENCES `payment_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createPaymentTransactionTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_transaction` (
                `id` BINARY(16) NOT NULL,
                `payment_order_id` BINARY(16) NOT NULL,
                `transaction_no` VARCHAR(64) NOT NULL,
                `type` VARCHAR(32) NOT NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `channel_technical_name` VARCHAR(64) NOT NULL,
                `request_payload` JSON NULL,
                `response_payload` JSON NULL,
                `out_transaction_id` VARCHAR(128) NULL,
                `status` VARCHAR(32) NOT NULL,
                `error_code` VARCHAR(64) NULL,
                `error_message` VARCHAR(512) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_transaction.transaction_no` (`transaction_no`),
                KEY `idx.payment_transaction.payment_order_id` (`payment_order_id`),
                CONSTRAINT `fk.payment_transaction.payment_order_id` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createPaymentRefundTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_refund` (
                `id` BINARY(16) NOT NULL,
                `refund_no` VARCHAR(64) NOT NULL,
                `payment_order_id` BINARY(16) NOT NULL,
                `out_refund_no` VARCHAR(128) NOT NULL,
                `amount` INT NOT NULL,
                `currency` VARCHAR(3) NOT NULL,
                `reason` VARCHAR(255) NULL,
                `state_id` BINARY(16) NOT NULL,
                `out_refund_id` VARCHAR(128) NULL,
                `refunded_at` DATETIME(3) NULL,
                `request_payload` JSON NULL,
                `response_payload` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_refund.refund_no` (`refund_no`),
                UNIQUE KEY `uniq.payment_refund.order_out_refund_no` (`payment_order_id`, `out_refund_no`),
                KEY `idx.payment_refund.state_id` (`state_id`),
                CONSTRAINT `fk.payment_refund.payment_order_id` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_refund.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * A notification is attributed to the interface that sent it and not to a channel: it is the
     * interface that signs - or encrypts - the body, so its credentials are the ones that read it.
     */
    private function createPaymentNotificationTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_notification` (
                `id` BINARY(16) NOT NULL,
                `payment_order_id` BINARY(16) NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `raw_body` LONGTEXT NULL,
                `headers` JSON NULL,
                `signature_valid` TINYINT(1) NOT NULL DEFAULT 0,
                `processed` TINYINT(1) NOT NULL DEFAULT 0,
                `process_result` VARCHAR(32) NULL,
                `error_message` VARCHAR(512) NULL,
                `remote_ip` VARCHAR(45) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.payment_notification.payment_order_id` (`payment_order_id`),
                KEY `idx.payment_notification.provider` (`provider_technical_name`),
                KEY `idx.payment_notification.processed` (`processed`),
                CONSTRAINT `fk.payment_notification.payment_order_id` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * One delivery of one event of one subject. The subject is what the event is about: an event that
     * happens once per order carries `order`, a refund event carries the id of that refund. The unique
     * key therefore spans all three columns - the order and the event alone are not unique, a second
     * refund of the same order has to be announced to the app as well.
     */
    private function createPaymentWebhookDeliveryTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_webhook_delivery` (
                `id` BINARY(16) NOT NULL,
                `payment_order_id` BINARY(16) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `event` VARCHAR(64) NOT NULL,
                `subject` VARCHAR(64) NOT NULL,
                `url` VARCHAR(1024) NOT NULL,
                `payload` JSON NULL,
                `signature` VARCHAR(255) NULL,
                `status` VARCHAR(32) NOT NULL,
                `attempt_count` INT NOT NULL,
                `next_retry_at` DATETIME(3) NULL,
                `last_http_status` INT NULL,
                `last_response` LONGTEXT NULL,
                `succeeded_at` DATETIME(3) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_webhook_delivery.subject` (`payment_order_id`, `event`, `subject`),
                KEY `idx.payment_webhook_delivery.retry` (`status`, `next_retry_at`),
                KEY `idx.payment_webhook_delivery.payment_app_id` (`payment_app_id`),
                CONSTRAINT `fk.payment_webhook_delivery.payment_order_id` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_webhook_delivery.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The edge between a checkout payment method and the gateway channel it collects through.
     *
     * Both directions are unique and enforced here: one method collects through exactly one channel,
     * and one channel is claimed by exactly one method. Two methods on the same channel would make the
     * shopper's choice meaningless, and both would silently charge the same merchant account. The
     * interface is part of the second key because different interfaces may name a channel alike.
     */
    private function createPaymentMethodGatewayChannelTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_method_gateway_channel` (
                `id` BINARY(16) NOT NULL,
                `payment_method_id` BINARY(16) NOT NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `channel_technical_name` VARCHAR(64) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_method_gateway_channel.payment_method_id` (`payment_method_id`),
                UNIQUE KEY `uniq.payment_method_gateway_channel.channel` (`provider_technical_name`, `channel_technical_name`),
                CONSTRAINT `fk.payment_method_gateway_channel.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The subscription plan: the price and the cadence a subscription is billed with. It is the root
     * of the subscription model and references the sales channel it is offered on and the currency it
     * is denominated in - both restricted, like the references of the checkout order, because a plan
     * that points nowhere cannot be billed.
     */
    private function createPaymentPlanTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_plan` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(64) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `billing_mode` VARCHAR(32) NOT NULL,
                `provider` VARCHAR(64) NOT NULL,
                `channel` VARCHAR(64) NOT NULL,
                `interval_unit` VARCHAR(16) NOT NULL,
                `interval_count` INT NOT NULL,
                `amount` INT NOT NULL,
                `currency_id` BINARY(16) NOT NULL,
                `trial_days` INT NOT NULL,
                `provider_plan_ref` VARCHAR(128) NULL,
                `provider_plan_snapshot` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_plan.technical_name` (`technical_name`),
                KEY `idx.payment_plan.sales_channel_id` (`sales_channel_id`),
                KEY `idx.payment_plan.currency_id` (`currency_id`),
                CONSTRAINT `fk.payment_plan.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_plan.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * A signing agreement of the mandate-driven providers. The out agreement number of wechat can
     * never be reused - not even for a re-signing of the very same customer - so it carries its own
     * unique key: a second signing must never silently collide with a terminated one.
     */
    private function createPaymentMandateTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_mandate` (
                `id` BINARY(16) NOT NULL,
                `agreement_no` VARCHAR(64) NOT NULL,
                `out_agreement_no` VARCHAR(128) NULL,
                `provider_agreement_no` VARCHAR(128) NULL,
                `customer_id` BINARY(16) NOT NULL,
                `plan_id` BINARY(16) NOT NULL,
                `provider` VARCHAR(64) NOT NULL,
                `channel` VARCHAR(64) NOT NULL,
                `sign_scene` VARCHAR(64) NULL,
                `personal_product_code` VARCHAR(64) NULL,
                `terms_snapshot` JSON NULL,
                `status_id` BINARY(16) NOT NULL,
                `single_limit` INT NULL,
                `total_limit` INT NULL,
                `max_times` INT NULL,
                `template_type` VARCHAR(32) NULL,
                `reusable` TINYINT(1) NOT NULL DEFAULT 0,
                `signed_at` DATETIME(3) NULL,
                `expires_at` DATETIME(3) NULL,
                `terminated_at` DATETIME(3) NULL,
                `termination_reason` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_mandate.agreement_no` (`agreement_no`),
                UNIQUE KEY `uniq.payment_mandate.out_agreement_no` (`out_agreement_no`),
                KEY `idx.payment_mandate.plan_id` (`plan_id`),
                KEY `idx.payment_mandate.status_id` (`status_id`),
                KEY `idx.payment_mandate.customer_id` (`customer_id`),
                CONSTRAINT `fk.payment_mandate.plan_id` FOREIGN KEY (`plan_id`) REFERENCES `payment_plan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_mandate.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_mandate.status_id` FOREIGN KEY (`status_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * One subscription of one customer on one plan. The unique key over the provider and its
     * subscription id locates a channel managed subscription - the column is nullable, because a
     * merchant initiated subscription is located by its mandate instead, and a unique key over a
     * nullable column accepts every number of NULLs.
     *
     * The plan and the mandate are the lifetime owners of the subscription, so their references are
     * restricted: a subscription of a deleted plan would be unchargeable, and a deleted mandate
     * would revoke the permission its subscription still bills through.
     */
    private function createPaymentSubscriptionTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_subscription` (
                `id` BINARY(16) NOT NULL,
                `subscription_no` VARCHAR(64) NOT NULL,
                `plan_id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `billing_mode` VARCHAR(32) NOT NULL,
                `provider` VARCHAR(64) NOT NULL,
                `channel` VARCHAR(64) NOT NULL,
                `provider_subscription_id` VARCHAR(128) NULL,
                `mandate_id` BINARY(16) NULL,
                `status_id` BINARY(16) NOT NULL,
                `provider_status` VARCHAR(64) NULL,
                `current_period_start` DATETIME(3) NULL,
                `current_period_end` DATETIME(3) NULL,
                `next_billing_at` DATETIME(3) NULL,
                `trial_end` DATETIME(3) NULL,
                `cancel_at` DATETIME(3) NULL,
                `canceled_at` DATETIME(3) NULL,
                `ended_at` DATETIME(3) NULL,
                `failure_count` INT NOT NULL,
                `last_failure_code` VARCHAR(64) NULL,
                `next_retry_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_subscription.subscription_no` (`subscription_no`),
                UNIQUE KEY `uniq.payment_subscription.provider_subscription` (`provider`, `provider_subscription_id`),
                KEY `idx.payment_subscription.status_next_billing` (`status_id`, `next_billing_at`),
                KEY `idx.payment_subscription.customer_status` (`customer_id`, `status_id`),
                KEY `idx.payment_subscription.sales_channel_id` (`sales_channel_id`),
                CONSTRAINT `fk.payment_subscription.plan_id` FOREIGN KEY (`plan_id`) REFERENCES `payment_plan` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_subscription.mandate_id` FOREIGN KEY (`mandate_id`) REFERENCES `payment_mandate` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_subscription.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_subscription.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_subscription.status_id` FOREIGN KEY (`status_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * One period of one subscription - one charge attempt per period, billed exactly once: the
     * unique key over the subscription and the cycle number is what keeps a second charge for the
     * same period from being booked, and the unique out trade number is what makes the retries of
     * one period idempotent towards the channel.
     */
    private function createPaymentSubscriptionCycleTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_subscription_cycle` (
                `id` BINARY(16) NOT NULL,
                `subscription_id` BINARY(16) NOT NULL,
                `cycle_no` INT NOT NULL,
                `period_start` DATETIME(3) NOT NULL,
                `period_end` DATETIME(3) NOT NULL,
                `amount` INT NOT NULL,
                `currency` VARCHAR(3) NOT NULL,
                `state_id` BINARY(16) NOT NULL,
                `out_trade_no` VARCHAR(128) NOT NULL,
                `provider_invoice_id` VARCHAR(128) NULL,
                `provider_transaction_id` VARCHAR(128) NULL,
                `failure_code` VARCHAR(64) NULL,
                `failure_class` VARCHAR(32) NULL,
                `attempt_count` INT NOT NULL,
                `next_retry_at` DATETIME(3) NULL,
                `paid_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_subscription_cycle.subscription_cycle` (`subscription_id`, `cycle_no`),
                UNIQUE KEY `uniq.payment_subscription_cycle.out_trade_no` (`out_trade_no`),
                KEY `idx.payment_subscription_cycle.state` (`state_id`),
                KEY `idx.payment_subscription_cycle.next_retry` (`state_id`, `next_retry_at`),
                CONSTRAINT `fk.payment_subscription_cycle.subscription_id` FOREIGN KEY (`subscription_id`) REFERENCES `payment_subscription` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_subscription_cycle.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * Seeds the alipay interface with the public sandbox account of the alipay open platform, plus one
     * demo app that may collect through it, so the payment api can be called right away.
     *
     * The credentials are written where they belong in the model: on the interface and not on a single
     * product of it, so the demo app can collect through every channel alipay is declared with.
     * Everything is written the very same way the application writes it - the credentials are sealed
     * with the same cipher, so nothing is stored in plain text. The cipher key is derived from
     * APP_SECRET, so this migration needs that environment variable, which every installation has.
     */
    private function seedProviderConfig(Connection $connection, PaymentCredentialCipher $cipher, string $now): void
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `payment_provider_config` WHERE `provider_technical_name` = :provider',
            ['provider' => self::PROVIDER]
        );

        $payload = (string) json_encode($cipher->encrypt($this->sandboxCredentials()), \JSON_THROW_ON_ERROR);
        $settings = (string) json_encode(['http_method' => 'GET'], \JSON_THROW_ON_ERROR);

        if (\is_string($existing) && $existing !== '') {
            $connection->update('payment_provider_config', [
                'enabled' => 1,
                'credentials' => $payload,
                'settings' => $settings,
                'updated_at' => $now,
            ], ['id' => $existing]);

            return;
        }

        $connection->insert('payment_provider_config', [
            'id' => Uuid::randomBytes(),
            'provider_technical_name' => self::PROVIDER,
            'enabled' => 1,
            'credentials' => $payload,
            'settings' => $settings,
            'created_at' => $now,
        ]);
    }

    /**
     * A configured interface does not offer a channel by itself - the platform decides here which of
     * the channels of an interface an app may collect through at all.
     *
     * The h5 channel declares no parameter of its own, so switching it on is everything that is needed
     * for the demo app to work out of the box.
     */
    private function seedChannelConfig(Connection $connection, string $now): void
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `payment_channel_config` WHERE `provider_technical_name` = :provider AND `channel_technical_name` = :channel',
            ['provider' => self::PROVIDER, 'channel' => self::CHANNEL]
        );

        if (\is_string($existing) && $existing !== '') {
            $connection->update('payment_channel_config', [
                'enabled' => 1,
                'updated_at' => $now,
            ], ['id' => $existing]);

            return;
        }

        $connection->insert('payment_channel_config', [
            'id' => Uuid::randomBytes(),
            'provider_technical_name' => self::PROVIDER,
            'channel_technical_name' => self::CHANNEL,
            'enabled' => 1,
            'created_at' => $now,
        ]);
    }

    private function seedDemoApp(Connection $connection, PaymentCredentialCipher $cipher, string $now): void
    {
        $appId = $connection->fetchOne(
            'SELECT `id` FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => self::DEMO_APP_TECHNICAL_NAME]
        );

        if (!\is_string($appId) || $appId === '') {
            $appId = Uuid::randomBytes();

            $connection->insert('payment_app', [
                'id' => $appId,
                'technical_name' => self::DEMO_APP_TECHNICAL_NAME,
                'name' => '支付宝沙箱联调应用',
                'type' => PaymentAppEntity::TYPE_EXTERNAL,
                'app_secret' => $cipher->encryptValue(self::DEMO_APP_SECRET),
                'active' => 1,
                'created_at' => $now,
            ]);
        }

        $this->seedAppProvider($connection, $appId, $now);
        $this->seedAppChannel($connection, $appId, $now);
    }

    /**
     * The tenant the shop itself collects through. It is written here, because the checkout cannot
     * create its first payment order without it - and here only, so the row has exactly one writer.
     * {@see \Shopwell\Core\Checkout\Payment\Storefront\PlatformPaymentApp} reads it and reports a
     * missing row as a broken installation instead of writing a second one at runtime.
     *
     * The secret is a random value sealed like every other secret and never handed out: nobody signs
     * a request with it, it exists because the column is not nullable. What keeps the api from
     * accepting this tenant as a caller is its type, not the secret, see PaymentAppEntity.
     *
     * No interface or channel is authorized here. That is done the first time the shop collects
     * through a channel, and only for a channel the merchant has enabled - seeding them all would
     * bypass the switch that opens a channel for the shop.
     */
    private function seedPlatformTenant(Connection $connection, PaymentCredentialCipher $cipher, string $now): void
    {
        $appId = $connection->fetchOne(
            'SELECT `id` FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME]
        );

        if (\is_string($appId) && $appId !== '') {
            return;
        }

        $connection->insert('payment_app', [
            'id' => Uuid::randomBytes(),
            'technical_name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME,
            'name' => '自营商城',
            'type' => PaymentAppEntity::TYPE_PLATFORM,
            'app_secret' => $cipher->encryptValue(bin2hex(random_bytes(32))),
            'active' => 1,
            'created_at' => $now,
        ]);
    }

    /**
     * The app is authorized for the whole interface, so it can collect through every channel of alipay
     * the platform has configured - and it pays with the account of the platform.
     */
    private function seedAppProvider(Connection $connection, string $appId, string $now): void
    {
        $binding = $connection->fetchOne(
            'SELECT `id` FROM `payment_app_provider` WHERE `payment_app_id` = :app AND `provider_technical_name` = :provider',
            ['app' => $appId, 'provider' => self::PROVIDER]
        );

        if (\is_string($binding) && $binding !== '') {
            return;
        }

        $connection->insert('payment_app_provider', [
            'id' => Uuid::randomBytes(),
            'payment_app_id' => $appId,
            'provider_technical_name' => self::PROVIDER,
            'enabled' => 1,
            // the demo app pays with the credentials of the platform, so it needs no own alipay account
            'mode' => PaymentAppProviderEntity::MODE_PLATFORM,
            'created_at' => $now,
        ]);
    }

    private function seedAppChannel(Connection $connection, string $appId, string $now): void
    {
        $binding = $connection->fetchOne(
            'SELECT `id` FROM `payment_app_channel` WHERE `payment_app_id` = :app AND `channel_technical_name` = :channel',
            ['app' => $appId, 'channel' => self::CHANNEL]
        );

        if (\is_string($binding) && $binding !== '') {
            return;
        }

        $connection->insert('payment_app_channel', [
            'id' => Uuid::randomBytes(),
            'payment_app_id' => $appId,
            'channel_technical_name' => self::CHANNEL,
            'enabled' => 1,
            'created_at' => $now,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function sandboxCredentials(): array
    {
        return [
            AlipayApi::APP_ID => self::SANDBOX_APP_ID,
            AlipayApi::PRIVATE_KEY => self::SANDBOX_PRIVATE_KEY,
            AlipayApi::PUBLIC_KEY => self::SANDBOX_ALIPAY_PUBLIC_KEY,
            AlipayApi::GATEWAY_URL => self::SANDBOX_GATEWAY_URL,
        ];
    }

    private function cipher(): PaymentCredentialCipher
    {
        $secret = $_SERVER['APP_SECRET'] ?? $_ENV['APP_SECRET'] ?? getenv('APP_SECRET');

        if (!\is_string($secret) || $secret === '') {
            throw MigrationException::migrationError('The environment variable APP_SECRET is required to seal the seeded payment credentials.');
        }

        return new PaymentCredentialCipher($secret);
    }
}
