<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Framework\Controller;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;

/**
 * @internal
 */
#[Package('discovery')]
class XmlHttpRequestableInterfaceTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    public function testPageLoads(): void
    {
        $client = $this->createSalesChannelBrowser(null, true);
        $client->request('GET', 'http://localhost/');

        static::assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testAccessDeniedForXmlHttpRequest(): void
    {
        $client = $this->createSalesChannelBrowser(null, true);

        $client->xmlHttpRequest('GET', 'http://localhost/');

        static::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testPageletLoads(): void
    {
        $client = $this->createSalesChannelBrowser(null, true);

        $client->request('GET', 'http://localhost/checkout/offcanvas');

        static::assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testPageletLoadsForXmlHttpRequest(): void
    {
        $client = $this->createSalesChannelBrowser(null, true);

        $client->xmlHttpRequest('GET', 'http://localhost/checkout/offcanvas');

        static::assertSame(200, $client->getResponse()->getStatusCode());
    }
}
