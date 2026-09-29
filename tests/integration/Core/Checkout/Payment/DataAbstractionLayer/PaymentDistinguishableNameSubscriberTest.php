<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Payment\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameSubscriber;
use Shopwell\Core\Checkout\Payment\PaymentEvents;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentDistinguishableNameSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private PaymentDistinguishableNameSubscriber $subscriber;

    private Context $context;

    protected function setUp(): void
    {
        $this->subscriber = new PaymentDistinguishableNameSubscriber();
        $this->context = Context::createDefaultContext();
    }

    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'addDistinguishablePaymentName',
            ],
            $this->subscriber->getSubscribedEvents()
        );
    }

    public function testFallsBackToPaymentMethodNameIfDistinguishableNameIsNotSet(): void
    {
        $paymentRepository = static::getContainer()->get('payment_method.repository');

        $paymentRepository->create(
            [
                [
                    'id' => $creditCardPaymentId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Credit card',
                        'zh-CN' => '信用卡',
                    ],
                    'technicalName' => 'payment_creditcard',
                    'active' => true,
                ],
                [
                    'id' => $invoicePaymentByShopwellPluginId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Invoice',
                        'zh-CN' => '发票支付',
                    ],
                    'technicalName' => 'payment_invoice1',
                    'active' => true,
                    'plugin' => [
                        'name' => 'Shopwell',
                        'baseClass' => 'Swag\Paypal',
                        'autoload' => [],
                        'version' => '1.0.0',
                        'label' => [
                            'en-GB' => 'Shopwell (English)',
                            'zh-CN' => 'Shopwell (中文)',
                        ],
                    ],
                ],
                [
                    'id' => $invoicePaymentByPluginId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Invoice',
                        'zh-CN' => '发票',
                    ],
                    'technicalName' => 'payment_invoice2',
                    'active' => true,
                    'plugin' => [
                        'name' => 'Plugin',
                        'baseClass' => 'Plugin\Paypal',
                        'autoload' => [],
                        'version' => '1.0.0',
                        'label' => [
                            'en-GB' => 'Plugin (English)',
                            'zh-CN' => 'Plugin (中文)',
                        ],
                    ],
                ],
                [
                    'id' => $invoicePaymentByAppId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Invoice',
                        'zh-CN' => '发票',
                    ],
                    'technicalName' => 'payment_invoice3',
                    'active' => true,
                    'appPaymentMethod' => [
                        'identifier' => 'identifier',
                        'appName' => 'appName',
                        'app' => [
                            'name' => 'App',
                            'path' => 'path',
                            'version' => '1.0.0',
                            'label' => 'App',
                            'integration' => [
                                'accessKey' => 'accessKey',
                                'secretAccessKey' => 'secretAccessKey',
                                'label' => 'Integration',
                            ],
                            'aclRole' => [
                                'name' => 'aclRole',
                            ],
                        ],
                    ],
                ],
            ],
            $this->context
        );

        /** @var PaymentMethodCollection $payments */
        $payments = $paymentRepository
            ->search(new Criteria(), $this->context)
            ->getEntities();

        $creditCardPayment = $payments->get($creditCardPaymentId);
        static::assertNotNull($creditCardPayment);
        static::assertSame('Credit card', $creditCardPayment->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByShopwellPlugin */
        $invoicePaymentByShopwellPlugin = $payments->get($invoicePaymentByShopwellPluginId);
        static::assertSame('Invoice | Shopwell (English)', $invoicePaymentByShopwellPlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByPlugin */
        $invoicePaymentByPlugin = $payments->get($invoicePaymentByPluginId);
        static::assertSame('Invoice | Plugin (English)', $invoicePaymentByPlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByApp */
        $invoicePaymentByApp = $payments->get($invoicePaymentByAppId);
        static::assertSame('Invoice | App', $invoicePaymentByApp->getDistinguishableName());

        /** @var PaymentMethodEntity $paidInAdvance */
        $paidInAdvance = $payments
            ->filterByProperty('name', 'Paid in advance')
            ->first();

        static::assertSame($paidInAdvance->getTranslation('name'), $paidInAdvance->getTranslation('distinguishableName'));

        $zhCnContext = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->getZhCnLanguageId(), Defaults::LANGUAGE_SYSTEM]
        );

        /** @var PaymentMethodCollection $payments */
        $payments = $paymentRepository
            ->search(new Criteria(), $zhCnContext)
            ->getEntities();

        $creditCardPayment = $payments->get($creditCardPaymentId);
        static::assertNotNull($creditCardPayment);
        static::assertSame('信用卡', $creditCardPayment->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByShopwellPlugin */
        $invoicePaymentByShopwellPlugin = $payments->get($invoicePaymentByShopwellPluginId);
        static::assertSame('发票支付 | Shopwell (中文)', $invoicePaymentByShopwellPlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByPlugin */
        $invoicePaymentByPlugin = $payments->get($invoicePaymentByPluginId);
        static::assertSame('发票 | Plugin (中文)', $invoicePaymentByPlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByApp */
        $invoicePaymentByApp = $payments->get($invoicePaymentByAppId);
        static::assertSame('发票 | App', $invoicePaymentByApp->getDistinguishableName());
    }
}
