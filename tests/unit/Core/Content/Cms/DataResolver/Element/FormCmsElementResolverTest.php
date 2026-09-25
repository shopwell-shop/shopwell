<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\Element\FormCmsElementResolver;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Salutation\AbstractSalutationsSorter;
use Shopwell\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopwell\Core\System\Salutation\SalesChannel\SalutationRouteResponse;
use Shopwell\Core\System\Salutation\SalutationCollection;
use Shopwell\Core\System\Salutation\SalutationDefinition;
use Shopwell\Core\System\Salutation\SalutationEntity;
use Shopwell\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FormCmsElementResolver::class)]
class FormCmsElementResolverTest extends TestCase
{
    public function testType(): void
    {
        $formCmsElementResolver = new FormCmsElementResolver(
            static::createStub(AbstractSalutationRoute::class),
            static::createStub(AbstractSalutationsSorter::class)
        );

        static::assertSame('form', $formCmsElementResolver->getType());
    }

    public function testResolverUsesAbstractSalutationsRouteToEnrichSlot(): void
    {
        $salutationCollection = $this->getSalutationCollection();
        $sorter = static::createStub(AbstractSalutationsSorter::class);
        $sorter->method('sort')->willReturnArgument(0);
        $formCmsElementResolver = new FormCmsElementResolver($this->getSalutationRoute($salutationCollection), $sorter);

        $formElement = $this->getCmsFormElement();
        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $formCmsElementResolver->enrich(
            $formElement,
            $context,
            new ElementDataCollection()
        );

        static::assertSame($formElement->getData(), $salutationCollection);
    }

    public function testResolverDelegatesToSalutationsSorter(): void
    {
        $salutationCollection = $this->getSalutationCollection();
        $sortedCollection = new SalutationCollection();

        $sorter = $this->createMock(AbstractSalutationsSorter::class);
        $sorter->expects($this->once())
            ->method('sort')
            ->with($salutationCollection)
            ->willReturn($sortedCollection);

        $formCmsElementResolver = new FormCmsElementResolver($this->getSalutationRoute($salutationCollection), $sorter);

        $formElement = $this->getCmsFormElement();
        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $formCmsElementResolver->enrich(
            $formElement,
            $context,
            new ElementDataCollection()
        );

        static::assertSame($sortedCollection, $formElement->getData());
    }

    public function testCollectReturnsNull(): void
    {
        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());
        $salutationRoute = static::createStub(AbstractSalutationRoute::class);

        $formCmsElementResolver = new FormCmsElementResolver(
            $salutationRoute,
            static::createStub(AbstractSalutationsSorter::class)
        );
        $actual = $formCmsElementResolver->collect(new CmsSlotEntity(), $context);

        static::assertNull($actual);
    }

    private function getCmsFormElement(): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setType('form');
        $slot->setUniqueIdentifier('id');

        return $slot;
    }

    private function getSalutationCollection(): SalutationCollection
    {
        return new SalutationCollection([
            $this->createSalutationWithSalutationKey('c'),
            $this->createSalutationWithSalutationKey('a'),
            $this->createSalutationWithSalutationKey('d'),
            $this->createSalutationWithSalutationKey('b'),
        ]);
    }

    private function createSalutationWithSalutationKey(string $salutationKey): SalutationEntity
    {
        return (new SalutationEntity())->assign([
            'id' => Uuid::randomHex(),
            'salutationKey' => $salutationKey,
        ]);
    }

    private function getSalutationRoute(SalutationCollection $salutationCollection): AbstractSalutationRoute
    {
        $salutationRoute = $this->createMock(AbstractSalutationRoute::class);
        $salutationRoute->expects($this->once())
            ->method('load')
            ->willReturn(new SalutationRouteResponse(
                new EntitySearchResult(
                    SalutationDefinition::ENTITY_NAME,
                    $salutationCollection->count(),
                    $salutationCollection,
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            ));

        return $salutationRoute;
    }
}
