<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopwell\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;
use Shopwell\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Shopwell\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;
use Shopwell\Storefront\ContentSystem\Validation\HeaderFooterAssignmentWriteValidator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HeaderFooterAssignmentWriteValidator::class)]
class HeaderFooterAssignmentWriteValidatorTest extends TestCase
{
    #[DataProvider('assignmentProvider')]
    public function testAssignmentGate(string $entity, ?string $rootSource, bool $hasField, bool $skip, bool $reject): void
    {
        $reader = static::createStub(LayoutRootSourceReader::class);
        $reader->method('read')->willReturn($rootSource);
        $command = static::createStub(WriteCommand::class);
        $command->method('getEntityName')->willReturn($entity);
        $command->method('hasField')->willReturn($hasField);
        $command->method('getPayload')->willReturn(['content_layout_id' => 'layout-id']);
        $command->method('getPath')->willReturn('/assignments/0');
        $context = Context::createDefaultContext();
        if ($skip) {
            $context->addState(LayoutGate::SKIP_VALIDATION_STATE);
        }
        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), [$command]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new HeaderFooterAssignmentWriteValidator($reader));

        $dispatcher->dispatch($event);

        $exceptions = $event->getExceptions()->getExceptions();
        if (!$reject) {
            static::assertSame([], $exceptions);

            return;
        }

        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
        static::assertSame('/assignments/0', $exceptions[0]->getPath());
        $violations = $exceptions[0]->getViolations();
        static::assertCount(1, $violations);
        static::assertSame(ContentSystemException::ROOT_SOURCE_ASSIGNMENT_MISMATCH, $violations->get(0)->getCode());
        static::assertSame('/content_layout_id', $violations->get(0)->getPropertyPath());
        static::assertSame($rootSource, $violations->get(0)->getInvalidValue());
        static::assertStringContainsString('root source is "' . $rootSource . '"', (string) $violations->get(0)->getMessage());
    }

    /**
     * @return iterable<string, array{string, string|null, bool, bool, bool}>
     */
    public static function assignmentProvider(): iterable
    {
        yield 'header accepts header source' => [HeaderContentLayoutDefinition::ENTITY_NAME, 'header', true, false, false];
        yield 'footer accepts footer source' => [FooterContentLayoutDefinition::ENTITY_NAME, 'footer', true, false, false];
        yield 'header rejects footer source' => [HeaderContentLayoutDefinition::ENTITY_NAME, 'footer', true, false, true];
        yield 'footer rejects product source' => [FooterContentLayoutDefinition::ENTITY_NAME, 'product', true, false, true];
        yield 'unresolved layout defers to foreign key validation' => [HeaderContentLayoutDefinition::ENTITY_NAME, null, true, false, false];
        yield 'unrelated entity is ignored' => ['product', 'product', true, false, false];
        yield 'assignment update without layout is ignored' => [HeaderContentLayoutDefinition::ENTITY_NAME, 'product', false, false, false];
        yield 'trusted import can skip a mismatch' => [HeaderContentLayoutDefinition::ENTITY_NAME, 'product', true, true, false];
    }
}
