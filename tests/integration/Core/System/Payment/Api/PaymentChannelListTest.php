<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Api;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract of `GET /payment-api/v1/channels`: the channels an app may actually use - enabled for the
 * app and backed by an authorized payment interface.
 *
 * @internal
 */
#[Package('framework')]
class PaymentChannelListTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PaymentApiFixtureTrait;

    private const PATH_CHANNELS = '/payment-api/v1/channels';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAlipayPlatform();
    }

    public function testListsEnabledChannels(): void
    {
        $this->createContractApp();

        $client = $this->getBrowser();
        $client->request('GET', self::PATH_CHANNELS, $this->signedPayload('GET', self::PATH_CHANNELS));

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $decoded = self::decodeResponse($response);
        static::assertArrayHasKey('channels', $decoded);
        static::assertCount(1, $decoded['channels']);

        $channel = $decoded['channels'][0];
        static::assertSame('alipay_h5', $channel['channel']);
        static::assertSame('alipay', $channel['provider']);
        static::assertSame('redirect', $channel['flow']);
        static::assertSame(['CNY'], $channel['currencies']);
        static::assertSame(['wap'], $channel['devices']);
        // whose merchant account the channel is charged on: the contract app inherits the account of
        // the platform tenant, which is what the retired `mode = 'platform'` used to say. The id is
        // named instead of a mode, because an app can inherit from any other app and not only from
        // the platform.
        static::assertSame($this->platformTenantId(Context::createDefaultContext()), $channel['source_app_id']);
        static::assertTrue($channel['inherited']);
        static::assertArrayNotHasKey('mode', $channel);
        static::assertArrayHasKey('channel_label', $channel);
        static::assertArrayHasKey('provider_label', $channel);
        static::assertArrayHasKey('runtime_params', $channel);
    }

    public function testAppWithoutAuthorizationsGetsEmptyList(): void
    {
        $this->createContractApp(withProvider: false, withChannel: false);

        $client = $this->getBrowser();
        $client->request('GET', self::PATH_CHANNELS, $this->signedPayload('GET', self::PATH_CHANNELS));

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $decoded = self::decodeResponse($response);
        static::assertSame(['channels' => []], $decoded);
    }
}
