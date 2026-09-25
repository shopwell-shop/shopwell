<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Media\Subscriber\MediaCreationSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaCreationSubscriber::class)]
class MediaCreationSubscriberTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                EntityWriteEvent::class => 'beforeWrite',
            ],
            MediaCreationSubscriber::getSubscribedEvents()
        );
    }

    public function getDefinition(): MediaDefinition
    {
        new StaticDefinitionInstanceRegistry(
            [$definition = new MediaDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        return $definition;
    }

    public function testBeforeWriteOnlyReactsToLiveVersions(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId($this->ids->create('version'));

        $subscriber = new MediaCreationSubscriber();

        $definition = $this->getDefinition();

        $command = new InsertCommand(
            $definition,
            ['path' => 'media/Bildschirm­fotö 2023-06-24 um 16.30.36.png'],
            ['id' => $this->ids->getBytes('media-1')],
            static::createStub(EntityExistence::class),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext($context),
            [$command],
        );

        $subscriber->beforeWrite($event);

        static::assertSame('media/Bildschirmfotö 2023-06-24 um 16.30.36.png', $command->getPayload()['path']);
    }

    public function testPathIsReplacedOnInsert(): void
    {
        $context = Context::createDefaultContext();

        $subscriber = new MediaCreationSubscriber();

        $definition = $this->getDefinition();

        $command = new InsertCommand(
            $definition,
            ['path' => 'media/Bildschirm­foto 2023-06-24 um 16.30.36.png'],
            ['id' => $this->ids->getBytes('media-1')],
            static::createStub(EntityExistence::class),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext($context),
            [$command],
        );

        $subscriber->beforeWrite($event);

        static::assertSame('media/Bildschirmfoto 2023-06-24 um 16.30.36.png', $command->getPayload()['path']);
    }

    public function testPathIsReplacedOnUpdate(): void
    {
        $context = Context::createDefaultContext();

        $subscriber = new MediaCreationSubscriber();

        $definition = $this->getDefinition();

        $command = new UpdateCommand(
            $definition,
            ['path' => 'media/Bildschirmfoto 2023-06-24 um 16.30.36.png'],
            ['id' => $this->ids->getBytes('media-1')],
            static::createStub(EntityExistence::class),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext($context),
            [$command],
        );

        $subscriber->beforeWrite($event);

        static::assertSame('media/Bildschirmfoto 2023-06-24 um 16.30.36.png', $command->getPayload()['path']);
    }
}
