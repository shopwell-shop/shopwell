<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Cache\Http\CacheRelevantRulesResolver;
use Shopwell\Core\Framework\Adapter\Cache\Http\Extension\ResolveCacheRelevantRuleIdsExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\EventDispatcher\AssertingEventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheRelevantRulesResolver::class)]
class CacheRelevantRulesResolverTest extends TestCase
{
    public function testResolveRuleAreas(): void
    {
        $eventDispatcher = new AssertingEventDispatcher(
            $this,
            [
                ResolveCacheRelevantRuleIdsExtension::NAME . '.pre' => 1,
                ResolveCacheRelevantRuleIdsExtension::NAME . '.post' => 1,
            ]
        );

        $resolver = new CacheRelevantRulesResolver(new ExtensionDispatcher(
            $eventDispatcher
        ));

        $ruleAreas = $resolver->resolveRuleAreas(
            new Request(),
            static::createStub(SalesChannelContext::class)
        );

        static::assertSame([RuleAreas::PRODUCT_AREA], $ruleAreas);
    }
}
