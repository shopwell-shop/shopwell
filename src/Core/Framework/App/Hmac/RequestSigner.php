<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Hmac;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class RequestSigner
{
    final public const SHOPWELL_APP_SIGNATURE = 'shopwell-app-signature';

    final public const SHOPWELL_SHOP_SIGNATURE = 'shopwell-shop-signature';

    public function signRequest(RequestInterface $request, string $secret): RequestInterface
    {
        if ($request->getMethod() !== Request::METHOD_POST) {
            return clone $request;
        }

        $body = $request->getBody()->getContents();

        $request->getBody()->rewind();

        if (!\strlen($body)) {
            return clone $request;
        }

        return $request->withAddedHeader(self::SHOPWELL_SHOP_SIGNATURE, $this->signPayload($body, $secret));
    }

    public function isResponseAuthentic(ResponseInterface $response, string $secret): bool
    {
        if (!$response->hasHeader(self::SHOPWELL_APP_SIGNATURE)) {
            return false;
        }

        $responseSignature = $response->getHeaderLine(self::SHOPWELL_APP_SIGNATURE);
        $compareSignature = $this->signPayload($response->getBody()->getContents(), $secret);

        $response->getBody()->rewind();

        return hash_equals($compareSignature, $responseSignature);
    }

    public function signPayload(string $payload, string $secretKey, string $algorithm = 'sha256'): string
    {
        return hash_hmac($algorithm, $payload, $secretKey);
    }
}
