<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Payment\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodIndexer;
use Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodIndexingMessage;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentMethodIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private PaymentMethodIndexer $indexer;

    private Context $context;

    protected function setUp(): void
    {
        $this->indexer = static::getContainer()->get(PaymentMethodIndexer::class);
        $this->context = Context::createDefaultContext();
    }

    public function testIndexerName(): void
    {
        static::assertSame(
            'payment_method.indexer',
            $this->indexer->getName()
        );
    }

    public function testGeneratesDistinguishablePaymentNameIfPaymentIsProvidedByExtension(): void
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
                    'technicalName' => 'payment_creaditcard',
                    'active' => true,
                ],
                [
                    'id' => $invoicePaymentByShopwellPluginId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Invoice',
                        'zh-CN' => '发票支付',
                    ],
                    'technicalName' => 'payment_invoice',
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
                    'technicalName' => 'payment_invoiceplugin',
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
                    'technicalName' => 'payment_App_identifier',
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

    public function testPaymentMethodIndexerNotLooping(): void
    {
        // Setup payment method(s)
        /** @var EntityRepository<PaymentMethodCollection> $paymentRepository */
        $paymentRepository = static::getContainer()->get('payment_method.repository');

        $paymentMethodId = Uuid::randomHex();

        $this->context->state(static function (Context $context) use ($paymentRepository, $paymentMethodId): void {
            $paymentRepository->create(
                [
                    [
                        'id' => $paymentMethodId,
                        'name' => [
                            'en-GB' => 'Credit card',
                            'zh-CN' => '信用卡',
                        ],
                        'technicalName' => 'payment_creditcard_test',
                        'active' => true,
                        'plugin' => [
                            'name' => 'Plugin',
                            'baseClass' => 'Plugin\MyPlugin',
                            'autoload' => [],
                            'version' => '1.0.0',
                            'label' => [
                                'en-GB' => 'Plugin (English)',
                                'zh-CN' => 'Plugin (中文)',
                            ],
                        ],
                    ],
                ],
                $context
            );
        }, EntityIndexerRegistry::DISABLE_INDEXING, EntityIndexerRegistry::USE_INDEXING_QUEUE);

        // Run indexer
        $messageBus = static::getContainer()->get('messenger.default_bus');
        static::assertInstanceOf(TraceableMessageBus::class, $messageBus);
        $messageBus->reset();
        $ids = [$paymentMethodId];

        $this->context->state(function (Context $context) use ($ids): void {
            $message = new PaymentMethodIndexingMessage($ids, null, $context);
            $this->indexer->handle($message);
        }, EntityIndexerRegistry::DISABLE_INDEXING, EntityIndexerRegistry::USE_INDEXING_QUEUE);

        // Check messenger if there is another new PaymentMethodIndexingMessage (it shouldn't)
        /** @var TraceableMessageBus $messageBus */
        $messages = $messageBus->getDispatchedMessages();
        static::assertCount(0, $messages);
    }
}
