<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Payment\Handler;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodCollection;
use Shopwell\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopwell\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentHandlerRegistryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private PaymentHandlerRegistry $paymentHandlerRegistry;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentMethodRepository;

    /**
     * @var EntityRepository<AppPaymentMethodCollection>
     */
    private EntityRepository $appPaymentMethodRepository;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $this->appPaymentMethodRepository = static::getContainer()->get('app_payment_method.repository');
        $this->paymentHandlerRegistry = static::getContainer()->get(PaymentHandlerRegistry::class);

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testPayments/manifest.xml');
        $appLifecycle = static::getContainer()->get(AppLifecycle::class);
        $appLifecycle->install($manifest, new AppInstallParameters(), Context::createDefaultContext());
    }

    public function testGetHandler(): void
    {
        $paymentMethod = $this->getPaymentMethod(InvoicePayment::class);
        $handler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());
        static::assertInstanceOf(InvoicePayment::class, $handler);
    }

    public function testAppResolve(): void
    {
        $appPaymentData = [
            'id' => Uuid::randomHex(),
            'identifier' => 'apptest',
            'appName' => 'apptest',
            'payUrl' => null,
            'finalizeUrl' => null,
            'validateUrl' => null,
            'captureUrl' => null,
            'refundUrl' => null,
        ];

        $paymentMethod = $this->getPaymentMethod('refundable');
        $appPaymentData['paymentMethodId'] = $paymentMethod->getId();

        $this->appPaymentMethodRepository->upsert([$appPaymentData], Context::createDefaultContext());

        $handler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());

        static::assertInstanceOf(AppPaymentHandler::class, $handler);
    }

    private function getPaymentMethod(string $handler): PaymentMethodEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handler));
        $criteria->addAssociation('app');

        /** @var PaymentMethodEntity|null $method */
        $method = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if (!$method) {
            $method = [
                'id' => Uuid::randomHex(),
                'technicalName' => 'payment_test',
                'handlerIdentifier' => $handler,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => $handler,
                    ],
                ],
            ];

            $this->paymentMethodRepository->upsert([$method], Context::createDefaultContext());

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handler));
            $criteria->addAssociation('app');

            /** @var PaymentMethodEntity|null $method */
            $method = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        }

        static::assertNotNull($method);

        return $method;
    }
}
