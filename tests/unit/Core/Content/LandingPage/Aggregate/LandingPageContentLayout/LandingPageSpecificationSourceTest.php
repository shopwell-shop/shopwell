<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutCollection;
use Shopwell\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopwell\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageSpecificationSource;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopwell\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageSpecificationSource::class)]
#[CoversClass(LandingPageContentLayoutDefinition::class)]
class LandingPageSpecificationSourceTest extends TestCase
{
    public function testUnassignedLandingPageUsesSalesChannelDefaultLayout(): void
    {
        $context = Generator::generateSalesChannelContext();
        $defaultLayoutId = Uuid::randomHex();
        $config = new StaticSystemConfigService();
        $config->set('core.content_system.default_landing_page_content_layout', $defaultLayoutId, $context->getSalesChannelId());
        $factory = new EntityLayoutContextFactory(
            new EntityLayoutResolver(),
            static::createStub(RootContextMapper::class),
            $config,
            static::createStub(CacheTagCollector::class),
        );
        $source = new LandingPageSpecificationSource(
            new StaticEntityRepository([new LandingPageContentLayoutCollection()]),
            new LandingPageContentLayoutDefinition(),
            $factory,
        );

        static::assertSame($defaultLayoutId, $source->resolveLayoutId('/landing-page/' . Uuid::randomHex(), new Request(), $context));
    }
}
