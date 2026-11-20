<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultHandler;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultHandlerRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalResultHandlerRegistry::class)]
class ApprovalResultHandlerRegistryTest extends TestCase
{
    public function testFrameworkAssemblyDiscoversBusinessOwnedTaggedHandlers(): void
    {
        $first = $this->handler('finance:expense', '1.0.0');
        $second = $this->handler('finance:expense', '2.0.0');
        $container = PublicationFixture::container('unused-manifest.json');
        $container->getDefinition(ApprovalResultHandlerRegistry::class)->setPublic(true);
        $container->register('expense.v1', ApprovalResultHandler::class)->setSynthetic(true)->setPublic(true)->addTag('approval_flow.result_handler');
        $container->register('expense.v2', ApprovalResultHandler::class)->setSynthetic(true)->setPublic(true)->addTag('approval_flow.result_handler');
        $container->compile();
        $container->set('expense.v1', $first);
        $container->set('expense.v2', $second);
        $registry = $container->get(ApprovalResultHandlerRegistry::class);

        static::assertSame($first, $registry->get('finance:expense', '1.0.0'));
        static::assertSame($second, $registry->get('finance:expense', '2.0.0'));
    }

    public function testResolvesTheBusinessVersionWithoutSubstitutingANewerHandler(): void
    {
        $first = $this->handler('finance:expense', '1.0.0');
        $second = $this->handler('finance:expense', '2.0.0');
        $registry = new ApprovalResultHandlerRegistry([$first, $second]);

        static::assertSame($first, $registry->get('finance:expense', '1.0.0'));
        static::assertSame($second, $registry->get('finance:expense', '2.0.0'));
        $this->expectExceptionObject(ApprovalFlowException::resultHandlerNotFound('finance:expense', '3.0.0'));

        $registry->get('finance:expense', '3.0.0');
    }

    public function testDuplicateRegistrationDoesNotExposeAPartialCatalogue(): void
    {
        $first = $this->handler('finance:expense', '1.0.0');
        $second = $this->handler('finance:expense', '1.0.0');
        $registry = new ApprovalResultHandlerRegistry([$first, $second]);
        $expected = ApprovalFlowException::duplicateResultHandler('finance:expense', '1.0.0', $first::class, $second::class);
        try {
            $registry->get('finance:expense', '1.0.0');
            static::fail('Conflicting handlers must fail the complete catalogue.');
        } catch (ApprovalFlowException $exception) {
            static::assertEquals($expected, $exception);
        }
        $this->expectExceptionObject($expected);

        $registry->get('finance:expense', '1.0.0');
    }

    private function handler(string $type, string $version): ApprovalResultHandler
    {
        $handler = static::createStub(ApprovalResultHandler::class);
        $handler->method('getType')->willReturn($type);
        $handler->method('getVersion')->willReturn($version);

        return $handler;
    }
}
