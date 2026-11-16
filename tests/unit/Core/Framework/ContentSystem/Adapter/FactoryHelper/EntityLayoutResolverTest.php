<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutCollection;
use Shopwell\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityLayoutResolver::class)]
class EntityLayoutResolverTest extends TestCase
{
    private EntityLayoutResolver $resolver;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->resolver = new EntityLayoutResolver(['utm_source', 'custom_tracking']);
    }

    #[TestDox('builds placeholder values from entity id field and scalar query parameters, ignoring non-scalar parameters')]
    public function testResolvePlaceholdersMergesEntityIdWithScalarQueryParameters(): void
    {
        $request = new Request(['color' => 'red', 'tags' => ['a', 'b']]);

        $result = $this->resolver->resolvePlaceholders('productId', 'product-id-1', $request);

        static::assertSame(
            ['productId' => 'product-id-1', 'color' => 'red'],
            $result->all()
        );
    }

    public function testQueryCannotReplaceTheRouteEntityIdentifier(): void
    {
        $request = new Request(['productId' => 'query-product', 'color' => 'red']);
        $result = $this->resolver->resolvePlaceholders('productId', 'route-product', $request);

        static::assertSame(['productId' => 'route-product', 'color' => 'red'], $result->all());
    }

    public function testCacheIgnoredQueryParametersCannotBecomePlaceholders(): void
    {
        $parameters = [
            'utm_source' => '<a href="https://example.test/phishing">Campaign</a>',
            'custom_tracking' => 'injected content',
            'campaign' => 'summer',
            'page' => 2,
        ];
        $request = new Request($parameters);

        $result = $this->resolver->resolvePlaceholders('landingPageId', 'landing-page-id', $request);

        static::assertSame(['landingPageId' => 'landing-page-id', 'campaign' => 'summer', 'page' => 2], $result->all());
        static::assertSame($parameters, $request->query->all());
    }

    #[TestDox('returns layout ID when assignment exists')]
    public function testReturnsLayoutIdWhenAssignmentExists(): void
    {
        $layoutId = $this->ids->get('layout');
        $entity = $this->createAssignmentEntity($layoutId);

        $repository = $this->createRepository($entity);

        $context = Generator::generateSalesChannelContext();

        $result = $this->resolver->findLayoutId('productId', $this->ids->get('product'), $context, $repository);

        static::assertSame($layoutId, $result);
    }

    #[TestDox('returns null when no assignment exists')]
    public function testReturnsNullFromLayoutIdLookupWhenNoAssignment(): void
    {
        $repository = $this->createRepository();
        $context = Generator::generateSalesChannelContext();

        $result = $this->resolver->findLayoutId('productId', $this->ids->get('product'), $context, $repository);

        static::assertNull($result);
    }

    /**
     * @return StaticEntityRepository<HeaderContentLayoutCollection>
     */
    private function createRepository(HeaderContentLayoutEntity ...$entities): StaticEntityRepository
    {
        $repository = StaticEntityRepository::of(HeaderContentLayoutCollection::class, [$entities]);

        return $repository;
    }

    private function createAssignmentEntity(string $layoutId): HeaderContentLayoutEntity
    {
        $entity = new HeaderContentLayoutEntity();
        $entity->setId($this->ids->get('assignment'));
        $entity->setContentLayoutId($layoutId);

        return $entity;
    }
}
