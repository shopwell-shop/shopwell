<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\DictionaryTypeDefinition;
use Shopwell\Core\System\Dictionary\SalesChannel\AbstractDictionaryRoute;
use Shopwell\Core\System\Dictionary\SalesChannel\DictionaryRouteResponse;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\ContentSystem\Hydration\DataLoader\FormGendersDataLoader;
use Shopwell\Storefront\ContentSystem\Hydration\DataLoader\FormGendersLoaderConfig;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FormGendersDataLoader::class)]
class FormGendersDataLoaderTest extends TestCase
{
    public function testLoadsTheTranslatedGenderDictionaryForTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $entries = new DictionaryEntryCollection();
        $response = static::createStub(DictionaryRouteResponse::class);
        $response->method('getEntries')->willReturn($entries);
        $route = $this->createMock(AbstractDictionaryRoute::class);
        $route->expects($this->once())->method('load')->with(
            static::callback(static fn (Request $request): bool => $request->attributes->get('technicalName') === DictionaryTypeDefinition::TYPE_GENDER),
            $context,
            static::anything(),
        )->willReturn($response);

        $loader = new FormGendersDataLoader($route);
        $result = $loader->load(new LoaderInputs([]), new DataRequirement('genders', FormGendersDataLoader::SOURCE, new FormGendersLoaderConfig()), $context, new Request());

        static::assertSame($entries, $result->data);
        static::assertSame(DictionaryEntryCollection::class, $loader->producibleTypes()[0]->producedType);
        static::assertSame('form_genders', $loader::getRequirementType());
    }
}
