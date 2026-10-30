<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Webhook\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Webhook\Authorization\RestrictWriteSubscriber;
use Shopwell\Core\Framework\Webhook\Authorization\WriteAuthorizer;
use Shopwell\Core\Framework\Webhook\WebhookDefinition;
use Shopwell\Core\Framework\Webhook\WebhookException;
use Shopwell\Core\System\Tax\TaxDefinition;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RestrictWriteSubscriber::class)]
class RestrictWriteSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([PreWriteValidationEvent::class => 'validate'], RestrictWriteSubscriber::getSubscribedEvents());
    }

    public function testTheWebhookWritesAreAuthorizedAndTheirViolationsRejectTheWrite(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [WebhookDefinition::class, TaxDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $webhook = $registry->getByEntityName(WebhookDefinition::ENTITY_NAME);
        $existence = static::createStub(EntityExistence::class);

        $insertId = Uuid::randomBytes();
        $deleteId = Uuid::randomBytes();
        $insert = new InsertCommand($webhook, ['id' => $insertId], ['id' => $insertId], $existence, '/insert');
        $delete = new DeleteCommand($webhook, ['id' => $deleteId], $existence);
        $context = new Context(new AdminApiSource(Uuid::randomHex()));
        $violation = WebhookException::webhookNotOwned(Uuid::fromBytesToHex($deleteId));

        $authorizer = $this->createMock(WriteAuthorizer::class);
        $authorizer->expects($this->once())
            ->method('getViolations')
            ->with([$insert, $delete], $context)
            ->willReturn([$violation]);

        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), [
            $insert,
            $delete,
            new CascadeDeleteCommand($webhook, ['id' => Uuid::randomBytes()], $existence),
            new UpdateCommand($registry->getByEntityName('tax'), ['id' => $insertId], ['id' => $insertId], $existence, '/update'),
        ]);

        (new RestrictWriteSubscriber($authorizer))->validate($event);

        static::assertSame([$violation], $event->getExceptions()->getExceptions());
    }
}
