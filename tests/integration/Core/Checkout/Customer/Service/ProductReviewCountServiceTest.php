<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class ProductReviewCountServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private ProductReviewCountService $reviewCountService;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->reviewCountService = static::getContainer()->get(ProductReviewCountService::class);
    }

    public function testReviewCountIsUpdatedCorrectly(): void
    {
        $this->createProduct('p1');
        $this->createProduct('p2');

        $this->createCustomer('c1');
        $createdReviews = [];
        $createdReviews[] = $this->createReview('c1', 'p1', true);
        $createdReviews[] = $this->createReview('c1', 'p2', false);

        $this->createCustomer('c2');
        $createdReviews[] = $this->createReview('c2', 'p2', true);

        $this->reviewCountService->updateReviewCount($createdReviews);

        $customerRepo = static::getContainer()->get('customer.repository');
        /** @var CustomerCollection $customers */
        $customers = $customerRepo->search(new Criteria([$this->ids->get('c1'), $this->ids->get('c2')]), Context::createDefaultContext())->getEntities();

        $firstCustomer = $customers->get($this->ids->get('c1'));
        static::assertInstanceOf(CustomerEntity::class, $firstCustomer);
        static::assertSame(1, $firstCustomer->getReviewCount());

        $secondCustomer = $customers->get($this->ids->get('c2'));
        static::assertInstanceOf(CustomerEntity::class, $secondCustomer);
        static::assertSame(1, $secondCustomer->getReviewCount());
    }

    private function createCustomer(string $customerNumber): void
    {
        $customer = (new CustomerBuilder(
            $this->ids,
            $customerNumber
        ))->build();

        $customerRepo = static::getContainer()->get('customer.repository');
        $customerRepo->create([$customer], Context::createDefaultContext());
    }

    private function createProduct(string $productNumber): void
    {
        $product = new ProductBuilder(
            $this->ids,
            $productNumber
        );
        $product->price(100);

        $productRepo = static::getContainer()->get('product.repository');
        $productRepo->create([$product->build()], Context::createDefaultContext());
    }

    private function createReview(string $customerNumber, string $productNumber, bool $status): string
    {
        $productReviewRepo = static::getContainer()->get('product_review.repository');

        $id = Uuid::randomHex();

        $productReviewRepo->create([
            [
                'id' => $id,
                'customerId' => $this->ids->get($customerNumber),
                'productId' => $this->ids->get($productNumber),
                'status' => $status,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'title' => 'foo',
                'content' => 'bar',
                'points' => 3,
            ],
        ], Context::createDefaultContext());

        return $id;
    }
}
