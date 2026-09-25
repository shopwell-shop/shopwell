<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Profiling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Profiling\Profiler;
use Shopwell\Core\Profiling\Profiling;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Profiling::class)]
class ProfilingTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $profiling = new Profiling();

        static::assertSame(-2, $profiling->getTemplatePriority());
    }

    public function testBoot(): void
    {
        $container = new Container();
        $container->set(Profiler::class, static::createStub(Profiler::class));
        $container->compile();

        $profiling = new Profiling();
        $profiling->setContainer($container);
        $profiling->boot();

        static::assertInstanceOf(FrozenParameterBag::class, $container->getParameterBag());
    }
}
