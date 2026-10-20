<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentGatewayHandler;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationException;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\Traits\StateMachineMigration;
use Shopwell\Core\Migration\Traits\StateMachineMigrationTrait;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStates;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use Shopwell\Core\System\Payment\Subscription\PaymentMandateStates;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleStates;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionStates;

/**
 * Creates the state machines and the tables of the generic payment gateway in their final shape.
 *
 * The configuration of the gateway is a 2x2 matrix, and the tables follow it exactly:
 *
 *  - `payment_provider_config` is the platform account of a payment interface (alipay, wechat pay, …),
 *  - `payment_app_provider` is the authorization of an app for a whole interface, and it names the
 *    account source - `NULL` for the credentials the app brings itself, the id of another app to
 *    inherit the account of that app, the tenant of the platform in particular,
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
 * transaction - that reference is what the gateway writes the paid state back through.
 *
 * Only one tenant is seeded: the shop itself (`platform`). It is the default app of the installation
 * and the account the checkout collects through, so an installation can take its first payment
 * without anyone creating an app first. An external app is onboarded by an administrator in the
 * administration, never here.
 *
 * Four payment methods are seeded on top of that tenant - alipay, wechat pay, paypal and stripe - so
 * the selection of the checkout is not empty after the installation and the merchant configures a
 * method that already works instead of assembling one. What they differ in is their readiness, and
 * that difference is data, not code: alipay, paypal and stripe collect with the sandbox accounts
 * seeded below, wechat pay waits for the merchant id and the certificate of *this* merchant and stays
 * out of the checkout until they exist.
 *
 * The state machines are imported first, because the state fields of the bookkeeping tables reference
 * them.
 *
 * ⚠️ The seeded credentials are sandbox accounts, for all three: alipay is seeded with a sandbox app id
 * and a locally generated dummy key pair, paypal with the sandbox client id and secret, stripe with the
 * test keys. The alipay pair is complete and signs and verifies against itself, but it belongs to no
 * alipay app, so a sandbox request signed with it is rejected - both keys have to be replaced with the
 * ones of the merchant's own sandbox app in the alipay console. Nothing here is a secret and nothing may
 * reach production unchanged; `sandbox`/`environment` are on and the stripe keys are `sk_test_`/`pk_test_`,
 * so going live is a deliberate switch.
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
     * The technical name of the alipay payment method a fresh installation offers.
     */
    final public const PAYMENT_METHOD_ALIPAY = 'alipay';

    /**
     * The technical name of the wechat pay payment method a fresh installation offers.
     */
    final public const PAYMENT_METHOD_WECHAT = 'wechat';

    /**
     * The channel the alipay payment method collects through.
     *
     * It is the only non sdk channel of alipay that declares both `web` and `wap`, so the one method
     * is collectable on a desktop browser and on a phone alike.
     */
    final public const PAYMENT_METHOD_ALIPAY_CHANNEL = AlipayApi::CHANNEL_QR;

    /**
     * The channel the wechat pay payment method collects through.
     *
     * It is the only non sdk channel of wechat pay that declares `web`: `wechat_h5` is wap only and the
     * remaining three are opened by an sdk the storefront must not bind.
     */
    final public const PAYMENT_METHOD_WECHAT_CHANNEL = WechatApi::CHANNEL_NATIVE;

    /**
     * The technical name of the paypal payment method a fresh installation offers.
     */
    final public const PAYMENT_METHOD_PAYPAL = 'paypal';

    /**
     * The technical name of the stripe payment method a fresh installation offers.
     */
    final public const PAYMENT_METHOD_STRIPE = 'stripe';

    /**
     * The channel of paypal the method collects through. Paypal declares a single product, and it is
     * the redirect checkout every browser can follow, so there is nothing to choose from.
     */
    final public const PAYMENT_METHOD_PAYPAL_CHANNEL = 'paypal_checkout';

    /**
     * The channel of stripe the method collects through, the redirect checkout of the stripe account.
     */
    final public const PAYMENT_METHOD_STRIPE_CHANNEL = 'stripe_checkout';

    /**
     * The positions the two seeded methods take in the payment selection, right after the four
     * methods of the checkout itself.
     */
    final public const PAYMENT_METHOD_ALIPAY_POSITION = 10;

    final public const PAYMENT_METHOD_WECHAT_POSITION = 11;

    final public const PAYMENT_METHOD_PAYPAL_POSITION = 12;

    final public const PAYMENT_METHOD_STRIPE_POSITION = 13;

    /**
     * The app id of the sandbox app of the alipay open platform.
     */
    final public const SANDBOX_APP_ID = '9021000149657375';

    /**
     * The client id of the paypal sandbox app, seeded so the interface is configured out of the box.
     */
    final public const PAYPAL_SANDBOX_CLIENT_ID = 'ATLGyYsLAve0DhlI4D_L4BI7Ak8jjtYmNGEjQla4EsExF9Df8FqZY0MDUmjOpSMhZhh4JoWp8xvw8gWn';

    /**
     * The client secret of the paypal sandbox app.
     */
    final public const PAYPAL_SANDBOX_CLIENT_SECRET = 'EDFbGlvazxMwGyNDWkjx8IgHJKArHOymBd2RfTXnlKTrf51DVn3eNfu7Xrl3NjZv0mjYz5QWbHL__o8o';

    /**
     * The secret key of the stripe test account. Stripe has no sandbox flag: the prefix of the key
     * decides the environment, `sk_test_` is the test mode the seeded credentials belong to.
     */
    final public const STRIPE_TEST_SECRET_KEY = 'sk_test_51UQsKsQM0sYLTkulPaZoETq2ziWyyV8X0TX9327DxBZ979uOlaf9BTDZT5DK3A4ihcbQzR2qXJ4c4MxQ8mIPfxPr00XVLyHE0h';

    /**
     * The publishable key of the same stripe test account. It is not a secret.
     */
    final public const STRIPE_TEST_PUBLISHABLE_KEY = 'pk_test_51UQsKsQM0sYLTkulZTkNnFEpgQKawrIUIXZ6mg4r6grcIlqr8QX8vQwN43WBG2FwCZSpOp4keEwY7KsfNbiOsraL006kbX2Orw';

    /**
     * The gateway the sandbox of the alipay interface answers on. It is not stored: the api picks it
     * from the `sandbox` flag, so switching the flag off goes back to the configured gateway instead of
     * keeping a sandbox url in a row nobody looks at.
     */
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
    final public const SANDBOX_ALIPAY_PUBLIC_KEY = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA4QFGqrFhDQGlgLwqir7AdHEIfIzXw7vn/WH5xWvL72j0gCygtLUWwrYe2mEtW12KBGu3CG+5kRuHC/ND7WgkhtdpXzMjG8YiVXFHVJqZVzD8DLR0PLC54av2A9QM4R7lcP8TmXMOdnFbeB6pN71u0ipPvNS8q9K4Os+jOZJyJbnjg99UHVkCLAgQ9gQB44A0BLNZ7YHaM7rwkhy8PvOPEBTVACPDjnpIC0Ma/M06OfBj8WeXZJK3pad92BI5wPCL6b61Z9hi2hXnVXNhKIS1dhRUH5fjoPo2nOOTUKV/anY5Xb9lZbDxN5Ypy+8yYMVvIItwPTO1GCffEoXSgHlT9QIDAQAB';

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

        // the seeded platform account is the reason the checkout can collect right after an installation
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $cipher = $this->cipher();

        $this->seedProviderConfig($connection, $cipher, $now);
        $this->seedPaypalProviderConfig($connection, $cipher, $now);
        $this->seedStripeProviderConfig($connection, $cipher, $now);
        $this->seedChannelConfig($connection, $now);
        $this->seedPlatformTenant($connection, $cipher, $now);

        // the methods the checkout offers right away - they collect on the tenant seeded above, so
        // they are written after it
        $this->seedGatewayPaymentMethods($connection, $now);
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
     *
     * `source_app_id` names the app whose account is charged, `NULL` means the app brings its own one.
     * Pointing it at the tenant of the platform is what "the platform collects for me" means.
     *
     * The foreign key is `ON DELETE SET NULL` on purpose: deleting the source app must not delete the
     * authorization of the inheriting app, and it must not leave a dangling id either. The inheriting
     * app falls back to "collects on its own account" instead, which has no credentials and is
     * therefore refused at payment time - visibly, instead of silently collecting on an account that
     * is not ours anymore.
     */
    private function createPaymentAppProviderTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_app_provider` (
                `id` BINARY(16) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `source_app_id` BINARY(16) NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `credentials` JSON NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_app_provider.app_provider` (`payment_app_id`, `provider_technical_name`),
                KEY `idx.payment_app_provider.provider` (`provider_technical_name`),
                KEY `idx.payment_app_provider.source_app` (`source_app_id`),
                CONSTRAINT `fk.payment_app_provider.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_app_provider.source_app_id` FOREIGN KEY (`source_app_id`) REFERENCES `payment_app` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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
     * A payment method does not point at an interface, it points at the pair a payment really runs
     * through: the app whose merchant account is charged, and one channel of that app. The interface
     * is not a column here because it is not a fact of its own - a channel belongs to exactly one
     * interface and the registry refuses to start when two interfaces name a channel alike, so the
     * interface is derived from the channel and can never disagree with it.
     *
     * Both directions are unique and enforced here: one method collects through exactly one channel,
     * and one channel of an app is claimed by exactly one method. Two methods on the same channel
     * would make the shopper's choice meaningless, and both would silently charge the same merchant
     * account. The app is part of the second key because two apps may well offer a channel of the
     * same name against different accounts.
     */
    private function createPaymentMethodGatewayChannelTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_method_gateway_channel` (
                `id` BINARY(16) NOT NULL,
                `payment_method_id` BINARY(16) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `channel_technical_name` VARCHAR(64) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_method_gateway_channel.payment_method_id` (`payment_method_id`),
                UNIQUE KEY `uniq.payment_method_gateway_channel.channel` (`payment_app_id`, `channel_technical_name`),
                CONSTRAINT `fk.payment_method_gateway_channel.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_method_gateway_channel.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
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
     * Seeds the alipay interface with the public sandbox account of the alipay open platform, so the
     * checkout can collect through it right away.
     *
     * The credentials are written where they belong in the model: on the interface and not on a single
     * product of it, so every channel alipay is declared with collects with the very same keys.
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
     * the channels of an interface the shop may collect through at all.
     *
     * The three seeded accounts are switched on with the channel their payment method is bound to,
     * because a bound channel that is not switched on is filtered out of the checkout just like a
     * channel that does not exist. Wechat pay is deliberately not among them: its method is bound, but
     * the merchant id and the certificate of this very shop do not exist, so the collectability check
     * keeps it out of the checkout until a merchant fills them in.
     */
    private function seedChannelConfig(Connection $connection, string $now): void
    {
        $this->enableChannel($connection, self::PROVIDER, self::CHANNEL, $now);
        $this->enableChannel($connection, self::PROVIDER, self::PAYMENT_METHOD_ALIPAY_CHANNEL, $now);
        $this->enableChannel($connection, PaypalApi::PROVIDER, self::PAYMENT_METHOD_PAYPAL_CHANNEL, $now);
        $this->enableChannel($connection, StripeApi::PROVIDER, self::PAYMENT_METHOD_STRIPE_CHANNEL, $now);
    }

    /**
     * Switches one channel of one interface on, or leaves the existing row exactly as it is.
     *
     * An existing row is not re-enabled: the merchant may have switched the channel off, and an
     * upgrade that undid that decision would start collecting through a product the shop does not want
     * to sell anymore.
     */
    private function enableChannel(Connection $connection, string $providerTechnicalName, string $channelTechnicalName, string $now): void
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `payment_channel_config` WHERE `provider_technical_name` = :provider AND `channel_technical_name` = :channel',
            ['provider' => $providerTechnicalName, 'channel' => $channelTechnicalName]
        );

        if (\is_string($existing) && $existing !== '') {
            return;
        }

        $connection->insert('payment_channel_config', [
            'id' => Uuid::randomBytes(),
            'provider_technical_name' => $providerTechnicalName,
            'channel_technical_name' => $channelTechnicalName,
            'enabled' => 1,
            'created_at' => $now,
        ]);
    }

    /**
     * The tenant the shop itself collects through. It is written here, because the checkout cannot
     * create its first payment order without it - and here only, so the row has exactly one writer.
     * {@see \Shopwell\Core\Checkout\Payment\Storefront\PlatformPaymentApp} reads it and reports a
     * missing row as a broken installation instead of writing a second one at runtime.
     *
     * It is the default app of the installation: there is no separate demo tenant next to it, an
     * external caller is onboarded by an administrator.
     *
     * The secret is a random value sealed like every other secret and never handed out: nobody signs
     * a request with it, it exists because the column is not nullable. What keeps the api from
     * accepting this tenant as a caller is its type, not the secret, see PaymentAppEntity.
     *
     * The interface and the channel seeded above are authorized for it in the same run, because the
     * default app has to be able to collect the moment the installation is finished - an app without
     * a single authorized interface cannot open a payment at all, and the application list would show
     * a tenant that could not do what it exists for. That is the one interface whose channel
     * `payment_channel_config` switches on, not all of them: seeding every authorization would bypass
     * the merchant's switch, and the channels an operator enables later are still authorized on
     * demand by {@see \Shopwell\Core\Checkout\Payment\Storefront\PlatformPaymentApp::appForChannel()}.
     *
     * Both writes are insert-if-absent. A merchant may have switched the authorization off or pointed
     * it at an account of its own; an upgrade that re-enabled it would silently undo that decision.
     */
    private function seedPlatformTenant(Connection $connection, PaymentCredentialCipher $cipher, string $now): void
    {
        $appId = $connection->fetchOne(
            'SELECT `id` FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME]
        );

        if (!\is_string($appId) || $appId === '') {
            $appId = Uuid::randomBytes();

            $connection->insert('payment_app', [
                'id' => $appId,
                'technical_name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME,
                'name' => 'Platform Payment App',
                'type' => PaymentAppEntity::TYPE_PLATFORM,
                'app_secret' => $cipher->encryptValue(bin2hex(random_bytes(32))),
                'active' => 1,
                'created_at' => $now,
            ]);
        }

        // an existing tenant is not skipped: the authorizations are what makes it usable, and an
        // installation that ran this migration before seeding them has to get them as well
        foreach ([AlipayApi::PROVIDER, PaypalApi::PROVIDER, StripeApi::PROVIDER] as $provider) {
            $this->authorizePlatformProvider($connection, $appId, $provider, $now);
        }

        $this->offerChannel($connection, $appId, self::CHANNEL, $now);
        $this->offerChannel($connection, $appId, self::PAYMENT_METHOD_PAYPAL_CHANNEL, $now);
        $this->offerChannel($connection, $appId, self::PAYMENT_METHOD_STRIPE_CHANNEL, $now);
    }

    /**
     * Authorizes one interface for the platform tenant: the tenant of the platform is the root of every
     * inheritance chain, it collects on the merchant account that is configured on the platform level -
     * `source_app_id` stays NULL, there is nobody to inherit from.
     *
     * An existing row is left exactly as it is - the merchant may have switched the interface off or
     * pointed it at own credentials, and neither is undone here.
     */
    private function authorizePlatformProvider(Connection $connection, string $appId, string $providerTechnicalName, string $now): void
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `payment_app_provider` WHERE `payment_app_id` = :app AND `provider_technical_name` = :provider',
            ['app' => $appId, 'provider' => $providerTechnicalName]
        );

        if (\is_string($existing) && $existing !== '') {
            return;
        }

        $connection->insert('payment_app_provider', [
            'id' => Uuid::randomBytes(),
            'payment_app_id' => $appId,
            'provider_technical_name' => $providerTechnicalName,
            'source_app_id' => null,
            'enabled' => 1,
            'created_at' => $now,
        ]);
    }

    /**
     * Offers one channel to one app, or leaves the existing row exactly as it is.
     *
     * The row is what {@see \Shopwell\Core\System\Payment\Provider\PaymentConfigResolver::resolve()}
     * requires before a payment can be opened at all, and what the administration shows as the switch
     * of the channel for that app - a merchant who switched it off has decided against that product
     * and keeps that decision.
     */
    private function offerChannel(Connection $connection, string $appId, string $channelTechnicalName, string $now): void
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `payment_app_channel` WHERE `payment_app_id` = :app AND `channel_technical_name` = :channel',
            ['app' => $appId, 'channel' => $channelTechnicalName]
        );

        if (\is_string($existing) && $existing !== '') {
            return;
        }

        $connection->insert('payment_app_channel', [
            'id' => Uuid::randomBytes(),
            'payment_app_id' => $appId,
            'channel_technical_name' => $channelTechnicalName,
            'enabled' => 1,
            'created_at' => $now,
        ]);
    }

    /**
     * Seeds the four payment methods a fresh installation offers: alipay, wechat pay, paypal and stripe.
     *
     * A payment method of the gateway is a binding and nothing else. It names the app whose merchant
     * account is charged and one channel of that app - the interface is derived from the channel and
     * the credentials from the app - so everything the method is worth is the row in
     * `payment_method_gateway_channel`. Seeding a method therefore means seeding that binding, and the
     * method itself on top of it.
     *
     * The channels are the ones that can be handed a shopper in a browser of either kind:
     *
     *  - `alipay_qr` is the only non sdk channel of alipay that declares both `web` and `wap`, so the
     *    one method covers a desktop browser and a phone. Binding `alipay_h5` would have hidden it from
     *    every desktop, binding `alipay_web` from every phone.
     *  - `wechat_native` is the only non sdk channel of wechat pay that declares `web`. `wechat_h5` is
     *    wap only and `wechat_jsapi`, `wechat_mini` and `wechat_app` are opened by an sdk, which the
     *    checkout refuses to hand a payer over to.
     *  - paypal and stripe declare a single product each, the redirect checkout every browser follows.
     *
     * They are not equally ready, and the difference is expressed in data instead of code:
     *
     *  - alipay, paypal and stripe collect the moment the installation finishes.
     *    {@see seedProviderConfig()} and its two siblings seeded the sandbox account of the interface,
     *    and the channel the method is bound to is switched on here, both on the platform level and for
     *    the terminal app - a channel that is not switched on, or not offered by the app whose account
     *    is charged, is not collectable even though the method exists.
     *  - wechat pay is listed in the administration but never offered to a shopper. No
     *    `payment_channel_config` row is written for `wechat_native` and the interface has no
     *    credentials, so {@see \Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodCollectability}
     *    drops the method before the shopper ever sees it. That is the rule and not an omission: wechat
     *    pay needs the merchant id and the certificate of *this* merchant, nobody can invent them, and
     *    a method that throws at the end of the checkout is worse than one that is not offered. The
     *    merchant fills them in on the payment interface page and the method appears by itself.
     *
     * Every method is assigned to every sales channel that exists at this point. On a fresh
     * installation that is no channel at all - the installer runs this migration before it creates the
     * storefront, and the storefront brings its own selection of methods afterwards, so nothing is
     * missing there. The assignments matter for an instance that is *upgraded*: its sales channels are
     * already there when this migration runs, and a method that no sales channel offers never reaches
     * the store api, no matter how correct its configuration is.
     */
    private function seedGatewayPaymentMethods(Connection $connection, string $now): void
    {
        $platformAppId = $connection->fetchOne(
            'SELECT `id` FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME]
        );

        if (!\is_string($platformAppId) || $platformAppId === '') {
            // there is no app to collect the methods on, so there is nothing to bind them to
            return;
        }

        $alipayMethodId = $this->seedGatewayPaymentMethod(
            $connection,
            $platformAppId,
            self::PAYMENT_METHOD_ALIPAY,
            self::PAYMENT_METHOD_ALIPAY_CHANNEL,
            self::PAYMENT_METHOD_ALIPAY_POSITION,
            ['zh' => '支付宝', 'en' => 'Alipay'],
            $now
        );

        if ($alipayMethodId === null) {
            return;
        }

        // the seeded account of the interface is only collectable through a channel the platform
        // offers, so the channel of the method is switched on together with the method.
        //
        // This has to be `alipay_qr` and cannot be left to the channel that {@see seedChannelConfig()}
        // already switched on: that one is `alipay_h5`, which has no payment method bound to it, so a
        // shopper could pick nothing through it. `alipay_qr` is the channel this method is bound to, and
        // a bound channel that is not switched on is filtered out by the collectability check just like
        // a channel that does not exist - the method would be listed but not collect a single payment.
        $this->enableChannel($connection, self::PROVIDER, self::PAYMENT_METHOD_ALIPAY_CHANNEL, $now);
        $this->offerChannel($connection, $platformAppId, self::PAYMENT_METHOD_ALIPAY_CHANNEL, $now);

        // wechat pay is deliberately left unconfigured: its method is bound, but neither the channel
        // nor the credentials exist, so the collectability filter keeps it out of the checkout until a
        // merchant fills in the merchant id and the certificate of this very shop
        $this->seedGatewayPaymentMethod(
            $connection,
            $platformAppId,
            self::PAYMENT_METHOD_WECHAT,
            self::PAYMENT_METHOD_WECHAT_CHANNEL,
            self::PAYMENT_METHOD_WECHAT_POSITION,
            ['zh' => '微信支付', 'en' => 'WeChat Pay'],
            $now
        );

        // paypal and stripe are complete on the other hand: the sandbox account of the interface is
        // seeded, the channel the method is bound to is switched on here and offered to the platform
        // tenant, so both are collectable the moment the installation is finished - and both are
        // swapped for a live account by switching `environment` on the interface, not by editing code.
        $paypalMethodId = $this->seedGatewayPaymentMethod(
            $connection,
            $platformAppId,
            self::PAYMENT_METHOD_PAYPAL,
            self::PAYMENT_METHOD_PAYPAL_CHANNEL,
            self::PAYMENT_METHOD_PAYPAL_POSITION,
            ['zh' => 'PayPal', 'en' => 'PayPal'],
            $now
        );

        if ($paypalMethodId !== null) {
            $this->enableChannel($connection, PaypalApi::PROVIDER, self::PAYMENT_METHOD_PAYPAL_CHANNEL, $now);
            $this->offerChannel($connection, $platformAppId, self::PAYMENT_METHOD_PAYPAL_CHANNEL, $now);
        }

        $stripeMethodId = $this->seedGatewayPaymentMethod(
            $connection,
            $platformAppId,
            self::PAYMENT_METHOD_STRIPE,
            self::PAYMENT_METHOD_STRIPE_CHANNEL,
            self::PAYMENT_METHOD_STRIPE_POSITION,
            ['zh' => 'Stripe', 'en' => 'Stripe'],
            $now
        );

        if ($stripeMethodId !== null) {
            $this->enableChannel($connection, StripeApi::PROVIDER, self::PAYMENT_METHOD_STRIPE_CHANNEL, $now);
            $this->offerChannel($connection, $platformAppId, self::PAYMENT_METHOD_STRIPE_CHANNEL, $now);
        }
    }

    /**
     * Writes one payment method of the gateway, its binding, its translations and its sales channel
     * assignments - each of them only when it is not there yet.
     *
     * The row of the method itself is skipped as a whole when a method with that technical name exists:
     * an administrator may have renamed it, repositioned it or switched it off, and none of that is
     * undone by an upgrade. The translations, the binding and the assignments are still completed,
     * because a method that lost one of them does not work - and each of them is matched on its own
     * identity, so a name an administrator edited is never overwritten either.
     *
     * @param array{zh: string, en: string} $names the name of the method in chinese and in english
     *
     * @return string|null the id of the method, `null` when the channel is already collected through by
     *                     another method - the binding is unique, so that method keeps the channel
     */
    private function seedGatewayPaymentMethod(
        Connection $connection,
        string $platformAppId,
        string $technicalName,
        string $channelTechnicalName,
        int $position,
        array $names,
        string $now
    ): ?string {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `payment_method` WHERE `technical_name` = :name',
            ['name' => $technicalName]
        );

        $methodId = \is_string($existing) && $existing !== '' ? $existing : null;

        if ($methodId === null) {
            $methodId = Uuid::randomBytes();

            $connection->insert('payment_method', [
                'id' => $methodId,
                'handler_identifier' => PaymentGatewayHandler::class,
                'technical_name' => $technicalName,
                'position' => $position,
                'active' => 1,
                // a failed payment is retried with the method the shopper picked, not with another one
                'after_order_enabled' => 0,
                'plugin_id' => null,
                'media_id' => null,
                'availability_rule_id' => null,
                'created_at' => $now,
            ]);
        }

        $this->seedPaymentMethodTranslations($connection, $methodId, $names, $now);

        $bound = $connection->fetchOne(
            'SELECT `payment_method_id` FROM `payment_method_gateway_channel` WHERE `payment_method_id` = :method',
            ['method' => $methodId]
        );

        if (!\is_string($bound) || $bound === '') {
            // the channel of an app is claimed by exactly one method, so a channel another method
            // already collects through is left to it instead of failing the unique key
            $claimed = $connection->fetchOne(
                'SELECT `payment_method_id` FROM `payment_method_gateway_channel` WHERE `payment_app_id` = :app AND `channel_technical_name` = :channel',
                ['app' => $platformAppId, 'channel' => $channelTechnicalName]
            );

            if (\is_string($claimed) && $claimed !== '') {
                return null;
            }

            $connection->insert('payment_method_gateway_channel', [
                'id' => Uuid::randomBytes(),
                'payment_method_id' => $methodId,
                'payment_app_id' => $platformAppId,
                'channel_technical_name' => $channelTechnicalName,
                'created_at' => $now,
            ]);
        }

        $this->assignPaymentMethodToSalesChannels($connection, $methodId);

        return $methodId;
    }

    /**
     * The name of a seeded method in every language of the installation.
     *
     * The language id is never hard coded: an installation brings its own set of languages, and any of
     * them may be chinese. The row is matched on the locale of the language instead - `zh-CN` and
     * `zh_CN` alike, since the separator is a spelling of the locale and not a different language - and
     * a language that is neither chinese nor english is left without a translation rather than given a
     * wrong one: the method then shows its key, which is visible instead of silent.
     *
     * An existing translation is never overwritten - an administrator may have renamed the method.
     *
     * @param array{zh: string, en: string} $names
     */
    private function seedPaymentMethodTranslations(Connection $connection, string $methodId, array $names, string $now): void
    {
        $languages = $connection->fetchAllAssociative(
            'SELECT `language`.`id` AS `id`, `locale`.`code` AS `code`
                FROM `language`
                    LEFT JOIN `locale` ON `locale`.`id` = `language`.`locale_id`'
        );

        foreach ($languages as $language) {
            $languageId = $language['id'] ?? null;

            if (!\is_string($languageId) || $languageId === '') {
                continue;
            }

            $code = strtolower((string) ($language['code'] ?? ''));

            $name = match (true) {
                str_starts_with($code, 'zh') => $names['zh'],
                str_starts_with($code, 'en') => $names['en'],
                default => null,
            };

            if ($name === null) {
                continue;
            }

            $existing = $connection->fetchOne(
                'SELECT 1 FROM `payment_method_translation` WHERE `payment_method_id` = :method AND `language_id` = :language',
                ['method' => $methodId, 'language' => $languageId]
            );

            if ($existing !== false) {
                continue;
            }

            $connection->insert('payment_method_translation', [
                'payment_method_id' => $methodId,
                'language_id' => $languageId,
                'name' => $name,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * Makes a seeded method available in every sales channel that exists right now.
     *
     * This is the upgrade half of the seeding, not the installation half: the installer runs the
     * migrations before it creates the storefront, so on a fresh installation there is not a single
     * sales channel yet and this method writes no row at all. An upgraded instance is the opposite -
     * its channels are already there, and they will never learn about the two new methods on their own.
     * That is why the assignments are written here instead of being left to the channel creation path.
     *
     * The availability of a payment method is the assignment and not the method: the store api reports
     * the methods of the sales channel it is called for, so a method without an assignment is invisible
     * in every storefront even though it is active and correctly bound.
     *
     * A channel that is opened later than this migration is not covered by it - it picks its methods
     * through the administration, like it does for every other method of the checkout.
     */
    private function assignPaymentMethodToSalesChannels(Connection $connection, string $methodId): void
    {
        $salesChannelIds = $connection->fetchFirstColumn('SELECT `id` FROM `sales_channel`');

        foreach ($salesChannelIds as $salesChannelId) {
            if (!\is_string($salesChannelId) || $salesChannelId === '') {
                continue;
            }

            $existing = $connection->fetchOne(
                'SELECT 1 FROM `sales_channel_payment_method` WHERE `sales_channel_id` = :channel AND `payment_method_id` = :method',
                ['channel' => $salesChannelId, 'method' => $methodId]
            );

            if ($existing !== false) {
                continue;
            }

            $connection->insert('sales_channel_payment_method', [
                'sales_channel_id' => $salesChannelId,
                'payment_method_id' => $methodId,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function sandboxCredentials(): array
    {
        return [
            AlipayApi::APP_ID => self::SANDBOX_APP_ID,
            // the sandbox is the default: the seeded keys belong to a sandbox app only
            AlipayApi::SANDBOX => '1',
            AlipayApi::PRIVATE_KEY => self::SANDBOX_PRIVATE_KEY,
            AlipayApi::PUBLIC_KEY => self::SANDBOX_ALIPAY_PUBLIC_KEY,
        ];
    }

    /**
     * Seeds the paypal interface with the sandbox app of the platform, so the interface is configured
     * the moment an operator opens it instead of asking for two values nobody has at hand.
     *
     * The row is only written when it does not exist yet: an interface that already carries
     * credentials belongs to the merchant, and a migration run has no business overwriting a live
     * client id. The environment is `sandbox` for the same reason the alipay seed is a sandbox one -
     * going live is a decision, not a default.
     */
    private function seedPaypalProviderConfig(Connection $connection, PaymentCredentialCipher $cipher, string $now): void
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `payment_provider_config` WHERE `provider_technical_name` = :provider',
            ['provider' => PaypalApi::PROVIDER]
        );

        if (\is_string($existing) && $existing !== '') {
            return;
        }

        $connection->insert('payment_provider_config', [
            'id' => Uuid::randomBytes(),
            'provider_technical_name' => PaypalApi::PROVIDER,
            'enabled' => 1,
            'credentials' => (string) json_encode($cipher->encrypt($this->paypalSandboxCredentials()), \JSON_THROW_ON_ERROR),
            'settings' => (string) json_encode([], \JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function paypalSandboxCredentials(): array
    {
        return [
            PaypalApi::CLIENT_ID => self::PAYPAL_SANDBOX_CLIENT_ID,
            PaypalApi::CLIENT_SECRET => self::PAYPAL_SANDBOX_CLIENT_SECRET,
            PaypalApi::ENVIRONMENT => 'sandbox',
        ];
    }

    /**
     * Seeds the stripe interface with the test account of the platform, the same way as the other two:
     * the interface is configured the moment an operator opens it, and an existing row is left alone.
     */
    private function seedStripeProviderConfig(Connection $connection, PaymentCredentialCipher $cipher, string $now): void
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `payment_provider_config` WHERE `provider_technical_name` = :provider',
            ['provider' => StripeApi::PROVIDER]
        );

        if (\is_string($existing) && $existing !== '') {
            return;
        }

        $connection->insert('payment_provider_config', [
            'id' => Uuid::randomBytes(),
            'provider_technical_name' => StripeApi::PROVIDER,
            'enabled' => 1,
            'credentials' => (string) json_encode($cipher->encrypt($this->stripeTestCredentials()), \JSON_THROW_ON_ERROR),
            'settings' => (string) json_encode([], \JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function stripeTestCredentials(): array
    {
        return [
            StripeApi::SECRET_KEY => self::STRIPE_TEST_SECRET_KEY,
            StripeApi::PUBLISHABLE_KEY => self::STRIPE_TEST_PUBLISHABLE_KEY,
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
