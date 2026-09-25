<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Script\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Debugging\ScriptTraces;
use Shopwell\Core\Framework\Script\Execution\InterfaceHook;
use Shopwell\Core\Framework\Script\Execution\ScriptEnvironmentFactory;
use Shopwell\Core\Framework\Script\Execution\ScriptExecutor;
use Shopwell\Core\Framework\Script\Execution\ScriptLoader;
use Shopwell\Core\Framework\Script\ScriptException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScriptExecutor::class)]
class ScriptExecutorTest extends TestCase
{
    public function testThrowsIfHookIsInterfaceHook(): void
    {
        $scriptExecutor = new ScriptExecutor(
            static::createStub(ScriptLoader::class),
            static::createStub(ScriptTraces::class),
            static::createStub(ContainerInterface::class),
            static::createStub(ScriptEnvironmentFactory::class),
        );

        try {
            $scriptExecutor->execute(static::createStub(InterfaceHook::class));
        } catch (ScriptException $e) {
            static::assertSame(ScriptException::INTERFACE_HOOK_EXECUTION_NOT_ALLOWED, $e->getErrorCode());
        }
    }
}
