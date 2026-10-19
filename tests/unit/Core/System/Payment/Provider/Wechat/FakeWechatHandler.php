<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Shopwell\Core\Framework\Log\Package;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

/**
 * The guzzle handler the wechat sdk talks through while the transfer tests run - it records every
 * request (method, path, body, headers) and answers from a canned queue.
 *
 * The answers are signed with the platform key of the test, so the verifier of the sdk runs on them
 * exactly like it runs on a real answer: a test that passes through this handler exercises the
 * signing of the request, the verification of the answer and the decoding, not only the action.
 *
 * @internal
 */
#[Package('framework')]
final class FakeWechatHandler
{
    /**
     * @var list<array{method: string, url: string, path: string, body: array<string, mixed>, headers: array<string, string>}>
     */
    public array $requests = [];

    /**
     * @param list<array{status: int, body: string}> $answers answered in the order they are queued
     */
    public function __construct(
        private readonly string $platformPrivateKey,
        private readonly string $platformSerial,
        private array $answers = [],
    ) {
    }

    /**
     * Queues one answer, signed with the platform key.
     *
     * @param array<string, mixed> $body
     */
    public function answer(int $status, array $body): self
    {
        $this->answers[] = ['status' => $status, 'body' => (string) json_encode($body, \JSON_THROW_ON_ERROR)];

        return $this;
    }

    public function stack(): HandlerStack
    {
        return HandlerStack::create(function (RequestInterface $request, array $options = []): PromiseInterface {
            $raw = (string) $request->getBody();
            $decoded = json_decode($raw, true);

            $headers = [];
            foreach (array_keys($request->getHeaders()) as $name) {
                $headers[$name] = $request->getHeaderLine($name);
            }

            $this->requests[] = [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri(),
                'path' => $request->getUri()->getPath(),
                'body' => \is_array($decoded) ? $decoded : [],
                'headers' => $headers,
            ];

            $answer = array_shift($this->answers) ?? ['status' => 200, 'body' => '{}'];
            $nonce = 'unit-test-nonce';
            $timestamp = (string) time();

            return Create::promiseFor(new Response($answer['status'], [
                'Content-Type' => 'application/json',
                'Wechatpay-Nonce' => $nonce,
                'Wechatpay-Serial' => $this->platformSerial,
                'Wechatpay-Signature' => Rsa::sign(Formatter::response($timestamp, $nonce, $answer['body']), $this->platformPrivateKey),
                'Wechatpay-Timestamp' => $timestamp,
            ], $answer['body']));
        });
    }

    /**
     * The only request that left, or a failure when the count is not exactly one.
     *
     * @return array{method: string, url: string, path: string, body: array<string, mixed>, headers: array<string, string>}
     */
    public function only(): array
    {
        if (\count($this->requests) !== 1) {
            throw new \RuntimeException(\sprintf('expected exactly one request, got %d', \count($this->requests)));
        }

        return $this->requests[0];
    }
}
