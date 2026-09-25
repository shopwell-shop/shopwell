<?php declare(strict_types=1);

namespace Shopwell\Tests\Bench\Storefront;

use Doctrine\DBAL\Connection;
use PhpBench\Attributes as Bench;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Tests\Bench\AbstractBenchCase;
use Shopwell\Tests\Bench\Fixtures;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductDetailRouteBench extends AbstractBenchCase
{
    private const SUBJECT_CUSTOMER = 'customer-0';
    private const PRODUCT_KEY = 'product-10';

    public function setupWithLogin(): void
    {
        $this->ids = clone Fixtures::getIds();
        $this->context = Fixtures::context([
            SalesChannelContextService::CUSTOMER_ID => $this->ids->get(self::SUBJECT_CUSTOMER),
        ]);
        if (!$this->context->getCustomerId()) {
            throw new \Exception('Customer not logged in for bench tests which require it!');
        }

        static::getContainer()->get(Connection::class)->beginTransaction();
    }

    #[Bench\BeforeMethods(['setUp'])]
    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 30ms +/- 5ms')]
    public function bench_load_product_detail_route_with_logged_out_user(): void
    {
        static::getContainer()->get(ProductDetailRoute::class)
            ->load($this->ids->get(self::PRODUCT_KEY), new Request(), $this->context, new Criteria());
    }

    #[Bench\BeforeMethods(['setupWithLogin'])]
    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 30ms +/- 5ms')]
    public function bench_load_product_detail_route_with_logged_in_user(): void
    {
        static::getContainer()->get(ProductDetailRoute::class)
            ->load($this->ids->get(self::PRODUCT_KEY), new Request(), $this->context, new Criteria());
    }
}
