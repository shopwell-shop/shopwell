<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\System\SalesChannel\SalesChannel\StoreApiInfoController;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
class OpenApi3Test extends TestCase
{
    use KernelTestBehaviour;

    public function testRequestOpenApi3Json(): void
    {
        $response = self::getContainer()->get(StoreApiInfoController::class)->info(new Request());

        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));
    }
}
