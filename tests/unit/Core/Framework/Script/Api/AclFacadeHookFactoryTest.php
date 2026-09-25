<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Api\AclFacadeHookFactory;
use Shopwell\Core\Framework\Script\AppContextCreator;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AclFacadeHookFactory::class)]
class AclFacadeHookFactoryTest extends TestCase
{
    public function testFactory(): void
    {
        $appContextCreator = $this->createMock(AppContextCreator::class);
        $hook = static::createStub(Hook::class);

        $script = new Script('my-script', '', new \DateTimeImmutable());
        $context = Context::createCLIContext();

        $appContextCreator
            ->expects($this->once())
            ->method('getAppContext')
            ->with($hook, $script)
            ->willReturn($context);

        $factory = new AclFacadeHookFactory($appContextCreator);

        static::assertSame('acl', $factory->getName());

        $factory->factory($hook, $script);
    }
}
