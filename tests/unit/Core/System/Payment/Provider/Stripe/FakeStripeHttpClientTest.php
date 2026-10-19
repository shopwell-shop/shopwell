<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;

/**
 * The fake client records what the stripe sdk sends and answers from a canned map, so the recording
 * and the lookups are asserted here instead of only being trusted by the transfer tests.
 *
 * The client itself lives in the tests as well and is therefore no coverage source of the suite.
 *
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class FakeStripeHttpClientTest extends TestCase
{
    private const PATH = '/v1/transfers';

    private const URL = 'https://api.stripe.com/v1/transfers';

    public function testRecordsTheRequestAndAnswersFromTheCannedMap(): void
    {
        $client = new FakeStripeHttpClient();
        $client->answers[self::PATH] = [201, '{"id": "tr_1"}'];

        $response = $client->request(
            'post',
            self::URL,
            ['Authorization: Bearer sk_test_123', 'Idempotency-Key:abc'],
            ['amount' => 100, 'currency' => 'cny'],
            false
        );

        static::assertSame(['{"id": "tr_1"}', 201, ['request-id' => ['req_unit_test']]], $response);
        static::assertSame(1, $client->count(self::PATH));
        static::assertSame(
            [
                'method' => 'post',
                'path' => self::PATH,
                'headers' => ['Authorization: Bearer sk_test_123', 'Idempotency-Key:abc'],
                'params' => ['amount' => 100, 'currency' => 'cny'],
            ],
            $client->requestOf(self::PATH)
        );
    }

    public function testCountsEveryCallOfAPath(): void
    {
        $client = new FakeStripeHttpClient();
        $client->answers[self::PATH] = [200, '{}'];

        $client->request('get', self::URL, [], [], false);
        $client->request('get', self::URL, [], [], false);

        static::assertSame(2, $client->count(self::PATH));
        static::assertSame(0, $client->count('/v1/payouts'));
    }

    /**
     * The first request of a path is the one the assertions read - a retry does not replace it.
     */
    public function testRequestOfReturnsTheFirstRequestOfAPath(): void
    {
        $client = new FakeStripeHttpClient();
        $client->answers[self::PATH] = [200, '{}'];

        $client->request('post', self::URL, [], ['amount' => 100], false);
        $client->request('post', self::URL, [], ['amount' => 200], false);

        static::assertSame(['amount' => 100], $client->requestOf(self::PATH)['params']);
    }

    public function testHeaderOfIsCaseInsensitiveAndTrimmed(): void
    {
        $client = new FakeStripeHttpClient();
        $client->answers[self::PATH] = [200, '{}'];

        $client->request('post', self::URL, ['Idempotency-Key:  key-1 '], [], false);

        static::assertSame('key-1', $client->headerOf(self::PATH, 'idempotency-key'));
        static::assertSame('key-1', $client->headerOf(self::PATH, 'IDEMPOTENCY-KEY'));
    }

    /**
     * A header the sdk did not send has to read as null, the tests use it to assert the absence.
     */
    public function testHeaderOfReturnsNullForAHeaderThatWasNotSent(): void
    {
        $client = new FakeStripeHttpClient();
        $client->answers[self::PATH] = [200, '{}'];

        $client->request('post', self::URL, ['Authorization: Bearer sk_test_123'], [], false);

        static::assertNull($client->headerOf(self::PATH, 'Idempotency-Key'));
    }

    public function testRequestOfRejectsAPathThatWasNeverCalled(): void
    {
        $client = new FakeStripeHttpClient();

        $this->expectExceptionObject(new \RuntimeException('no request was made to /v1/transfers'));

        $client->requestOf(self::PATH);
    }

    /**
     * A test that forgot to can an answer must fail loudly instead of talking to stripe.
     */
    public function testRequestRejectsAPathWithoutACannedAnswer(): void
    {
        $client = new FakeStripeHttpClient();

        $this->expectExceptionObject(new \RuntimeException('no fake stripe answer for post /v1/transfers'));

        $client->request('post', self::URL, [], [], false);
    }
}
