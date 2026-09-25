<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodValidator;
use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodValidator::class)]
class PaymentMethodValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [PaymentMethodDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                PreWriteValidationEvent::class => 'validate',
            ],
            PaymentMethodValidator::getSubscribedEvents()
        );
    }

    public function testValidate(): void
    {
        $paymentMethodId = Uuid::randomBytes();

        $context = Context::createDefaultContext();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [new DeleteCommand(
                $this->definitionInstanceRegistry->get(PaymentMethodDefinition::class),
                ['id' => $paymentMethodId],
                new EntityExistence(PaymentMethodDefinition::ENTITY_NAME, ['id' => $paymentMethodId], true, false, false, [])
            )],
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                'SELECT id FROM payment_method WHERE id IN (:ids) AND plugin_id IS NOT NULL',
                ['ids' => [$paymentMethodId]],
                ['ids' => ArrayParameterType::BINARY]
            )
            ->willReturn(false);

        $subscriber = new PaymentMethodValidator($connection);
        $subscriber->validate($event);
    }

    public function testValidateWithExistingPlugin(): void
    {
        $paymentMethodId = Uuid::randomBytes();

        $context = Context::createDefaultContext();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [new DeleteCommand(
                $this->definitionInstanceRegistry->get(PaymentMethodDefinition::class),
                ['id' => $paymentMethodId],
                new EntityExistence(PaymentMethodDefinition::ENTITY_NAME, ['id' => $paymentMethodId], true, false, false, [])
            )],
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                'SELECT id FROM payment_method WHERE id IN (:ids) AND plugin_id IS NOT NULL',
                ['ids' => [$paymentMethodId]],
                ['ids' => ArrayParameterType::BINARY]
            )
            ->willReturn('pluginId');

        $this->expectExceptionObject(PaymentException::pluginPaymentMethodDeleteRestriction());
        $subscriber = new PaymentMethodValidator($connection);
        $subscriber->validate($event);
    }

    public function testValidateWithoutCommand(): void
    {
        $context = Context::createDefaultContext();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            []
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('fetchOne');

        $subscriber = new PaymentMethodValidator($connection);
        $subscriber->validate($event);
    }
}
