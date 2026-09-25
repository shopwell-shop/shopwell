<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Resource\SalesChannelListResource;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelListResource::class)]
class SalesChannelListResourceTest extends TestCase
{
    public function testReturnsFormattedSalesChannels(): void
    {
        $id = Uuid::randomHex();
        $langId = Uuid::randomHex();

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://shop.example.com');
        $domain->setLanguageId($langId);

        $type = new SalesChannelTypeEntity();
        $type->setId(Uuid::randomHex());
        $type->setName('Storefront');

        $channel = new SalesChannelEntity();
        $channel->setId($id);
        $channel->setName('My Shop');
        $channel->setType($type);
        $channel->setActive(true);
        $channel->setDomains(new SalesChannelDomainCollection([$domain]));

        $collection = new SalesChannelCollection([$channel]);
        $context = Context::createDefaultContext();

        $searchResult = new EntitySearchResult(
            'sales_channel',
            1,
            $collection,
            null,
            new Criteria(),
            $context,
        );

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($searchResult);

        $resource = new SalesChannelListResource($repository);
        $result = ($resource)();

        static::assertSame('shopware://sales-channels', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);

        $data = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $data);
        static::assertSame($id, $data[0]['id']);
        static::assertSame('My Shop', $data[0]['name']);
        static::assertSame('Storefront', $data[0]['type']);
        static::assertTrue($data[0]['active']);
        static::assertCount(1, $data[0]['domains']);
        static::assertSame('https://shop.example.com', $data[0]['domains'][0]['url']);
    }
}
