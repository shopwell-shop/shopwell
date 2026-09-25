<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Flow;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Flow\Rule\FlowRuleScopeBuilder;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversNothing]
class FlowServiceConfigTest extends TestCase
{
    public function testFlowRuleScopeBuilderIsTaggedForKernelReset(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../../src/Core/Content/DependencyInjection/flow.php');

        static::assertSame(
            [['method' => 'reset']],
            $container->getDefinition(FlowRuleScopeBuilder::class)->getTag('kernel.reset'),
        );
    }
}
