<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\DependencyInjection\Configuration;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Configuration::class)]
class ConfigurationTest extends TestCase
{
    public function testConfigTree(): void
    {
        $configuration = new Configuration();
        $tree = $configuration->getConfigTreeBuilder();

        static::assertSame('elasticsearch', $tree->buildTree()->getName());
    }
}
