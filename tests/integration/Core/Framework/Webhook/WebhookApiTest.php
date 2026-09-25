<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Webhook;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class WebhookApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testWriteWebhookViaApi(): void
    {
        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/webhook/',
            [
                'name' => 'My super webhook',
                'eventName' => 'product.written',
                'url' => 'http://127.0.0.1',
            ]
        );

        $response = $this->getBrowser()->getResponse();

        static::assertSame(204, $response->getStatusCode(), \print_r($response->getContent(), true));
    }
}
