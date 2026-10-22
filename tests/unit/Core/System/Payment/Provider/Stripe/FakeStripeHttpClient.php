<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use Shopwell\Core\Framework\Log\Package;
use Stripe\HttpClient\ClientInterface;

/**
 * The http client the stripe sdk talks through while the transfer tests run - it records every
 * request (method, path, headers, params) and answers from a canned map of path to status and body,
 * so the actions are driven end to end without a network.
 *
 * It carries logic of its own (the recording, the lookup of an answer, the parsing of a header), so
 * it is exercised directly by FakeStripeHttpClientTest and must not be excluded from coverage.
 *
 * @internal
 */
#[Package('framework')]
final class FakeStripeHttpClient implements ClientInterface
{
    /**
     * @var array<string, array{0: int, 1: string}> path => [status, body]
     */
    public array $answers = [];

    /**
     * @var list<array{method: string, path: string, headers: list<string>, params: array<array-key, mixed>}>
     */
    private array $requests = [];

    /**
     * Records the request and answers it from {@see self::$answers}.
     *
     * @param 'delete'|'get'|'post' $method
     * @param string $absUrl
     * @param array<array-key, mixed> $headers
     * @param array<array-key, mixed> $params
     * @param bool $hasFile
     * @param 'v1'|'v2' $apiMode
     * @param int|null $maxNetworkRetries
     *
     * @return array{0: string, 1: int, 2: array<string, list<string>>}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = (string) (parse_url((string) $absUrl, \PHP_URL_PATH) ?: '');
        $headers = array_values(array_map(strval(...), (array) $headers));

        $this->requests[] = [
            'method' => (string) $method,
            'path' => $path,
            'headers' => $headers,
            'params' => $params,
        ];

        $answer = $this->answers[$path]
            ?? throw new \RuntimeException(\sprintf('no fake stripe answer for %s %s', $method, $path));

        return [$answer[1], $answer[0], ['request-id' => ['req_unit_test']]];
    }

    /**
     * How often a path was called - the "the transfer is never created twice" assertions read it.
     */
    public function count(string $path): int
    {
        $count = 0;

        foreach ($this->requests as $request) {
            if ($request['path'] === $path) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return array{method: string, path: string, headers: list<string>, params: array<string, mixed>}
     */
    public function requestOf(string $path): array
    {
        foreach ($this->requests as $request) {
            if ($request['path'] === $path) {
                return $request;
            }
        }

        throw new \RuntimeException(\sprintf('no request was made to %s', $path));
    }

    /**
     * One header of the request to a path, or null when it was not sent.
     */
    public function headerOf(string $path, string $name): ?string
    {
        foreach ($this->requestOf($path)['headers'] as $header) {
            [$headerName, $value] = array_pad(explode(':', $header, 2), 2, '');

            if (strtolower(trim($headerName)) === strtolower($name)) {
                return trim($value);
            }
        }

        return null;
    }
}
