<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopwell\Core\Framework\Store\Exception\StoreSessionExpiredException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
class StoreSessionExpiredMiddleware implements MiddlewareInterface
{
    private const STORE_TOKEN_EXPIRED = 'ShopwellPlatformException-1';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack
    ) {
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            /** @var PromiseInterface $promise */
            $promise = $handler($request, $options);

            return $promise->then(function (ResponseInterface $response) use ($request) {
                if ($response->getStatusCode() !== 401) {
                    return $response;
                }

                $body = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
                $code = $body['code'] ?? null;

                if ($code !== self::STORE_TOKEN_EXPIRED) {
                    $response->getBody()->rewind();

                    return $response;
                }

                if ($token = $request->getHeaderLine(StoreRequestOptionsProvider::SHOPWELL_PLATFORM_TOKEN_HEADER)) {
                    $this->logoutUserByToken($token);
                } else {
                    $this->logoutUserByContext();
                }

                throw new StoreSessionExpiredException();
            });
        };
    }

    private function logoutUserByToken(string $token): void
    {
        $this->connection->executeStatement(
            'UPDATE user SET store_token = NULL WHERE store_token = :token',
            ['token' => $token]
        );
    }

    private function logoutUserByContext(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return;
        }

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return;
        }

        $userId = $source->getUserId();
        if (!$userId) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE user SET store_token = NULL WHERE id = :userId',
            ['userId' => Uuid::fromHexToBytes($userId)]
        );
    }
}
