<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopwell\Core\Content\Product\SalesChannel\Document\ProductDocumentRouteResponse;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentRouteResponse::class)]
class ProductDocumentRouteResponseTest extends TestCase
{
    public function testReturnsTheResultAndItsDocuments(): void
    {
        $document = new ProductDocumentEntity();
        $document->setId(Uuid::randomHex());
        $documents = new ProductDocumentCollection([$document]);

        $result = new EntitySearchResult('product_document', 1, $documents, null, new Criteria(), Context::createDefaultContext());
        $response = new ProductDocumentRouteResponse($result);

        static::assertSame($result, $response->getResult());
        static::assertSame($documents, $response->getDocuments());
    }
}
