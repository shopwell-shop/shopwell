<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Validation\ContentLayoutAssignmentWriteValidator;
use Shopwell\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutAssignmentWriteValidator::class)]
class ContentLayoutAssignmentWriteValidatorTest extends TestCase
{
    private IdsCollection $ids;

    private EntityDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
        $this->definition = (new StaticDefinitionInstanceRegistry(
            [CategoryContentLayoutDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        ))->getByEntityName(CategoryContentLayoutDefinition::ENTITY_NAME);
    }

    #[TestDox('adds no violation when the assigned layout is not loadable (no root source), deferring to the FK')]
    public function testUnloadableLayoutAddsNoViolation(): void
    {
        $event = $this->validate([], [$this->assign('/0', 'layout')]);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    #[TestDox('adds no violation when the layout root source matches the assignment type')]
    public function testMatchingRootSourceAddsNoViolation(): void
    {
        $event = $this->validate(['layout' => 'category'], [$this->assign('/0', 'layout')]);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    #[TestDox('adds a rootSourceAssignmentMismatch violation at the assignment path when the root source differs from the assignment type')]
    public function testMismatchingRootSourceAddsViolation(): void
    {
        $event = $this->validate(['layout' => 'product'], [$this->assign('/0', 'layout')]);

        $collected = $event->getExceptions()->getExceptions();
        static::assertCount(1, $collected);
        static::assertInstanceOf(WriteConstraintViolationException::class, $collected[0]);
        static::assertSame('/0', $collected[0]->getPath());

        $violations = $collected[0]->getViolations();
        static::assertCount(1, $violations);
        static::assertSame(ContentSystemException::ROOT_SOURCE_ASSIGNMENT_MISMATCH, $violations->get(0)->getCode());
    }

    #[TestDox('judges an assignment the batch writes twice on the layout it assigns last')]
    public function testAssignmentWrittenTwiceIsJudgedOnItsLastAssignment(): void
    {
        $event = $this->validate(
            ['product-layout' => 'product', 'category-layout' => 'category'],
            [$this->assign('/0', 'product-layout'), $this->assign('/1', 'category-layout', UpdateCommand::class)],
        );

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    #[TestDox('skips a command whose entity is not an assignable definition without reading the root source')]
    public function testSkipsCommandForNonAssignableDefinition(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn(static::createStub(EntityDefinition::class));

        $reader = $this->createMock(LayoutRootSourceReader::class);
        $reader->expects($this->never())->method('rootSources');

        $event = $this->event([$this->assign('/0', 'layout')]);
        $this->validator($registry, $reader)->preValidate($event);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    #[TestDox('skips an assignment update that does not set the content_layout_id field without reading the root source')]
    public function testSkipsAssignmentUpdateWithoutContentLayoutId(): void
    {
        $reader = $this->createMock(LayoutRootSourceReader::class);
        $reader->expects($this->never())->method('rootSources');

        $event = $this->event([$this->command(UpdateCommand::class, '/0', ['sales_channel_id' => 'channel'])]);
        $this->validator($this->assignableRegistry(), $reader)->preValidate($event);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    /**
     * @param array<string, string> $rootSourceByLayoutKey
     * @param list<WriteCommand> $commands
     */
    private function validate(array $rootSourceByLayoutKey, array $commands): PreWriteValidationEvent
    {
        $rootSources = [];
        foreach ($rootSourceByLayoutKey as $key => $rootSource) {
            $rootSources[$this->ids->get($key)] = $rootSource;
        }

        $reader = static::createStub(LayoutRootSourceReader::class);
        $reader->method('rootSources')->willReturn($rootSources);

        $event = $this->event($commands);
        $this->validator($this->assignableRegistry(), $reader)->preValidate($event);

        return $event;
    }

    private function validator(DefinitionInstanceRegistry $registry, LayoutRootSourceReader $reader): ContentLayoutAssignmentWriteValidator
    {
        return new ContentLayoutAssignmentWriteValidator($registry, $reader, new WriteOutcome(static::createStub(RowLoader::class)));
    }

    private function assignableRegistry(): DefinitionInstanceRegistry
    {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getContentLayoutEntityType')->willReturn('category');

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn($definition);

        return $registry;
    }

    /**
     * @param class-string<InsertCommand|UpdateCommand> $commandClass
     */
    private function assign(string $path, string $layoutKey, string $commandClass = InsertCommand::class): WriteCommand
    {
        return $this->command($commandClass, $path, ['content_layout_id' => $this->ids->get($layoutKey)]);
    }

    /**
     * @param class-string<InsertCommand|UpdateCommand> $commandClass
     * @param array<string, string> $payload
     */
    private function command(string $commandClass, string $path, array $payload): WriteCommand
    {
        return new $commandClass($this->definition, $payload, ['id' => Uuid::fromHexToBytes($this->ids->get('assignment'))], static::createStub(EntityExistence::class), $path);
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function event(array $commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), $commands);
    }
}
