<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopwell\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopwell\Core\Checkout\Gateway\Command\AddPaymentMethodCommand;
use Shopwell\Core\Checkout\Gateway\Command\Handler\AddPaymentMethodCommandHandler;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\ExceptionLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddPaymentMethodCommandHandler::class)]
class AddPaymentMethodCommandHandlerTest extends TestCase
{
    public function testSupportedCommands(): void
    {
        static::assertSame(
            [AddPaymentMethodCommand::class],
            AddPaymentMethodCommandHandler::supportedCommands()
        );
    }

    public function testHandle(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setUniqueIdentifier(Uuid::randomHex());
        $paymentMethod->setTechnicalName('test');

        $result = new EntitySearchResult(
            PaymentMethodDefinition::ENTITY_NAME,
            1,
            new PaymentMethodCollection([$paymentMethod]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->with(
                static::callback(
                    static function (Criteria $criteria): bool {
                        static::assertCount(1, $criteria->getFilters());

                        /** @var EqualsFilter $filter */
                        $filter = $criteria->getFilters()[0];

                        static::assertInstanceOf(EqualsFilter::class, $filter);
                        static::assertSame('technicalName', $filter->getField());
                        static::assertSame('test', $filter->getValue());

                        static::assertTrue($criteria->hasAssociation('appPaymentMethod'));
                        $assoc = $criteria->getAssociation('appPaymentMethod');
                        static::assertTrue($assoc->hasAssociation('app'));

                        return true;
                    }
                ),
                static::isInstanceOf(Context::class)
            )
            ->willReturn($result);

        $command = new AddPaymentMethodCommand('test');

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $context = Generator::generateSalesChannelContext();

        $handler = new AddPaymentMethodCommandHandler($repo, static::createStub(ExceptionLogger::class));
        $handler->handle($command, $response, $context);

        static::assertSame($paymentMethod, $response->getAvailablePaymentMethods()->first());
    }

    public function testPaymentMethodNotFoundThrows(): void
    {
        $result = new EntitySearchResult(
            PaymentMethodDefinition::ENTITY_NAME,
            0,
            new PaymentMethodCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $command = new AddPaymentMethodCommand('test');

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $context = Generator::generateSalesChannelContext();

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->once())
            ->method('logOrThrowException')
            ->with(static::equalTo(CheckoutGatewayException::handlerException('Payment method "{{ technicalName }}" not found', ['technicalName' => 'test'])));

        $handler = new AddPaymentMethodCommandHandler($repo, $logger);
        $handler->handle($command, $response, $context);
    }
}
