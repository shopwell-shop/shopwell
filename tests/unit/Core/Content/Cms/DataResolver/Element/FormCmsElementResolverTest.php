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
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryEntity;
use Shopwell\Core\System\Dictionary\SalesChannel\AbstractDictionaryRoute;
use Shopwell\Core\System\Dictionary\SalesChannel\DictionaryRouteResponse;
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
            static::createStub(AbstractDictionaryRoute::class)
        );

        static::assertSame('form', $formCmsElementResolver->getType());
    }

    public function testResolverUsesAbstractDictionaryRouteToEnrichSlot(): void
    {
        $salutationCollection = $this->getDictionaryEntryCollection();
        $formCmsElementResolver = new FormCmsElementResolver($this->getDictionaryRoute($salutationCollection));

        $formElement = $this->getCmsFormElement();
        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $formCmsElementResolver->enrich(
            $formElement,
            $context,
            new ElementDataCollection()
        );

        static::assertSame($formElement->getData(), $salutationCollection);
    }

    public function testCollectReturnsNull(): void
    {
        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());
        $salutationRoute = static::createStub(AbstractDictionaryRoute::class);

        $formCmsElementResolver = new FormCmsElementResolver(
            $salutationRoute
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

    private function getDictionaryEntryCollection(): DictionaryEntryCollection
    {
        return new DictionaryEntryCollection([
            $this->createSalutationWithTechnicalName('c'),
            $this->createSalutationWithTechnicalName('a'),
            $this->createSalutationWithTechnicalName('d'),
            $this->createSalutationWithTechnicalName('b'),
        ]);
    }

    private function createSalutationWithTechnicalName(string $technicalName): DictionaryEntryEntity
    {
        return (new DictionaryEntryEntity())->assign([
            'id' => Uuid::randomHex(),
            'technicalName' => $technicalName,
        ]);
    }

    private function getDictionaryRoute(DictionaryEntryCollection $salutationCollection): AbstractDictionaryRoute
    {
        $salutationRoute = $this->createMock(AbstractDictionaryRoute::class);
        $salutationRoute->expects($this->once())
            ->method('load')
            ->willReturn(new DictionaryRouteResponse(
                new EntitySearchResult(
                    DictionaryEntryDefinition::ENTITY_NAME,
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
