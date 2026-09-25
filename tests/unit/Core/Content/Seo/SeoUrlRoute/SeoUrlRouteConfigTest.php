<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlRouteConfig::class)]
class SeoUrlRouteConfigTest extends TestCase
{
    public function testConfig(): void
    {
        $entityDefinition = static::createStub(EntityDefinition::class);
        $config = new SeoUrlRouteConfig(
            $entityDefinition,
            'foo_bar',
            '{{ foo.bar }}',
            false,
            'fooId'
        );

        static::assertSame($entityDefinition, $config->getDefinition());
        static::assertSame('foo_bar', $config->getRouteName());
        static::assertSame('{{ foo.bar }}', $config->getTemplate());
        static::assertFalse($config->getSkipInvalid());
        static::assertSame(
            ['fooId' => 'foo-value'],
            $config->getPrimaryKeyParameter('foo-value')
        );
    }

    public function testGetPrimaryKeyParameterThrowsWhenNoKeyConfigured(): void
    {
        $defintion = static::createStub(EntityDefinition::class);
        $defintion->method('getEntityName')->willReturn('foo_bar');

        $config = new SeoUrlRouteConfig(
            $defintion,
            'foo_bar',
            '{{ foo.bar }}'
        );

        $this->expectExceptionObject(SeoUrlRouteConfigException::routeConfigMissingParameterKeyForPrimaryKey('foo_bar'));

        $config->getPrimaryKeyParameter('foo-value');
    }
}
