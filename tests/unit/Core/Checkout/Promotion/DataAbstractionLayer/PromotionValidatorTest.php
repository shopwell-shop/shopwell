<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionValidator;
use Shopwell\Core\Checkout\Promotion\PromotionDefinition;
use Shopwell\Core\Checkout\Promotion\PromotionException;
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
#[CoversClass(PromotionValidator::class)]
class PromotionValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [PromotionDefinition::class],
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
            PromotionValidator::getSubscribedEvents()
        );
    }

    public function testValidate(): void
    {
        $promotionId = Uuid::randomBytes();

        $context = Context::createDefaultContext();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [new DeleteCommand(
                $this->definitionInstanceRegistry->get(PromotionDefinition::class),
                ['id' => $promotionId],
                new EntityExistence(PromotionDefinition::ENTITY_NAME, ['id' => $promotionId], true, false, false, [])
            )],
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                'SELECT id FROM promotion WHERE id IN (:ids) AND order_count > 0',
                ['ids' => [$promotionId]],
                ['ids' => ArrayParameterType::BINARY]
            )
            ->willReturn(false);

        $subscriber = new PromotionValidator($connection);
        $subscriber->validate($event);
    }

    public function testValidateWithOrderCount(): void
    {
        $promotionId = Uuid::randomBytes();

        $context = Context::createDefaultContext();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [new DeleteCommand(
                $this->definitionInstanceRegistry->get(PromotionDefinition::class),
                ['id' => $promotionId],
                new EntityExistence(PromotionDefinition::ENTITY_NAME, ['id' => $promotionId], true, false, false, [])
            )],
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                'SELECT id FROM promotion WHERE id IN (:ids) AND order_count > 0',
                ['ids' => [$promotionId]],
                ['ids' => ArrayParameterType::BINARY]
            )
            ->willReturn('someId');

        $this->expectExceptionObject(PromotionException::promotionUsedDeleteRestriction());
        $subscriber = new PromotionValidator($connection);
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

        $subscriber = new PromotionValidator($connection);
        $subscriber->validate($event);
    }
}
