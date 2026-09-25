<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Store\Api;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Api\ExtensionStoreLicensesController;
use Shopwell\Core\Framework\Store\Services\ExtensionStoreLicensesService;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
class ExtensionStoreLicensesControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCancelSubscription(): void
    {
        $provider = static::createStub(ExtensionStoreLicensesService::class);
        $provider->method('cancelSubscription');

        $controller = new ExtensionStoreLicensesController(
            $provider
        );

        $response = $controller->cancelSubscription(1, Context::createDefaultContext());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testRateLicensedExtension(): void
    {
        $provider = static::createStub(ExtensionStoreLicensesService::class);

        $controller = new ExtensionStoreLicensesController(
            $provider
        );

        $request = new Request();
        $request->request->replace([
            'authorName' => 'Max',
            'headline' => 'Max',
            'rating' => 1,
            'version' => '2.0.0',
        ]);

        $response = $controller->rateLicensedExtension(1, $request, Context::createDefaultContext());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
