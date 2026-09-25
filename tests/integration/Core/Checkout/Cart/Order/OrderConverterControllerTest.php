<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
class OrderConverterControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testConvertToCartIsAllowedForOrderViewer(): void
    {
        $browser = $this->getBrowser(true, [], ['order:read']);
        $browser->jsonRequest('POST', \sprintf('/api/_action/order/%s/convert-to-cart/', Uuid::randomHex()));

        // the order does not exist, but the request must pass the privilege check to get there
        static::assertSame(
            Response::HTTP_NOT_FOUND,
            $browser->getResponse()->getStatusCode(),
            (string) $browser->getResponse()->getContent()
        );
    }
}
