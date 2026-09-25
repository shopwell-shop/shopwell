<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Script\Api;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Test\AppSystemTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ResponseHookTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = $this->getSalesChannelBrowser();
    }

    public function testResponseHook(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->browser->request('GET', '/account/login');
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        static::assertSame('deny', $response->headers->get('X-Frame-Options'));

        $this->browser->request('GET', '/');
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        static::assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }
}
