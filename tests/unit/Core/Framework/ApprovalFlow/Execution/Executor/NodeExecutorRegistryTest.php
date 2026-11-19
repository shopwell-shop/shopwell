<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NodeExecutorRegistry::class)]
class NodeExecutorRegistryTest extends TestCase
{
    public function testResolvesCoexistingExactVersions(): void
    {
        $first = $this->executor('finance:review', '1.0.0');
        $second = $this->executor('finance:review', '2.0.0');
        $registry = new NodeExecutorRegistry([$first, $second]);

        static::assertSame($first, $registry->get('finance:review', '1.0.0'));
        static::assertSame($second, $registry->get('finance:review', '2.0.0'));
    }

    public function testMissingExactVersionNeverFallsBack(): void
    {
        $registry = new NodeExecutorRegistry([$this->executor('finance:review', '2.0.0')]);
        $this->expectExceptionObject(ApprovalFlowException::nodeExecutorNotFound('finance:review', '1.0.0'));

        $registry->get('finance:review', '1.0.0');
    }

    public function testConflictDoesNotLeaveAPartiallyLoadedCatalogue(): void
    {
        $first = $this->executor('finance:review', '1.0.0');
        $second = $this->executor('finance:review', '1.0.0');
        $registry = new NodeExecutorRegistry([$first, $second]);
        $expected = ApprovalFlowException::duplicateNodeExecutor('finance:review', '1.0.0', $first::class, $second::class);
        try {
            $registry->get('finance:review', '1.0.0');
            static::fail('Conflicting executors must fail the whole catalogue.');
        } catch (ApprovalFlowException $exception) {
            static::assertEquals($expected, $exception);
        }
        $this->expectExceptionObject($expected);

        $registry->get('finance:review', '1.0.0');
    }

    #[DataProvider('invalidIdentityProvider')]
    public function testRejectsInvalidRegistrationIdentity(string $type, string $version): void
    {
        $executor = $this->executor($type, $version);
        $registry = new NodeExecutorRegistry([$executor]);
        try {
            $registry->get($type, $version);
            static::fail('Invalid identities must not enter the executor catalogue.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_EXECUTOR, $exception->getErrorCode());
            static::assertSame($executor::class, $exception->getParameters()['executor']);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidIdentityProvider(): iterable
    {
        yield 'type must be namespaced' => ['review', '1.0.0'];
        yield 'type cannot be numeric' => ['123', '1.0.0'];
        yield 'version cannot be latest' => ['finance:review', 'latest'];
        yield 'version cannot be a range' => ['finance:review', '^1.0.0'];
        yield 'version must be canonical' => ['finance:review', '01.0.0'];
        yield 'version cannot have a trailing newline' => ['finance:review', "1.0.0\n"];
    }

    private function executor(string $type, string $version): NodeExecutor
    {
        $executor = static::createStub(NodeExecutor::class);
        $executor->method('getType')->willReturn($type);
        $executor->method('getVersion')->willReturn($version);

        return $executor;
    }
}
