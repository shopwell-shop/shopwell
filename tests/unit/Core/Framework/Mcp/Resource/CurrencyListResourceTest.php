<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Resource\CurrencyListResource;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\Currency\CurrencyEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CurrencyListResource::class)]
class CurrencyListResourceTest extends TestCase
{
    public function testReturnsFormattedCurrencies(): void
    {
        $id = Uuid::randomHex();
        $currency = new CurrencyEntity();
        $currency->setId($id);
        $currency->setIsoCode('EUR');
        $currency->setSymbol('€');
        $currency->setFactor(1.0);
        $currency->setName('Euro');

        $collection = new CurrencyCollection([$currency]);
        $context = Context::createDefaultContext();

        $searchResult = new EntitySearchResult(
            'currency',
            1,
            $collection,
            null,
            new Criteria(),
            $context,
        );

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($searchResult);

        $resource = new CurrencyListResource($repository);
        $result = ($resource)();

        static::assertSame('shopware://currencies', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);

        $data = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $data);
        static::assertSame($id, $data[0]['id']);
        static::assertSame('EUR', $data[0]['isoCode']);
        static::assertSame('€', $data[0]['symbol']);
        static::assertEquals(1.0, $data[0]['factor']);
        static::assertSame('Euro', $data[0]['name']);
    }
}
