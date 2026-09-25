<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @internal
 */
#[Package('framework')]
class DisabledTwigCacheWarmupTest extends TestCase
{
    use KernelTestBehaviour;

    public function testServiceIsRemoved(): void
    {
        static::expectException(ServiceNotFoundException::class);
        static::getContainer()->get('twig.template_cache_warmer');
    }
}
