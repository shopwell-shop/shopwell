<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Controller;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Service\PaymentAppAuthenticator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base of the controllers of the payment api.
 *
 * The api is a plain json api: the credentials of the app are top level keys of the payload next to the
 * business parameters, everything is answered as json and the http status is the normal status of the
 * error that occurred.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractPaymentApiController
{
    public function __construct(private readonly PaymentAppAuthenticator $authenticator)
    {
    }

    /**
     * Authenticates the caller and returns it together with its complete payload.
     *
     * @throws PaymentException
     *
     * @return array{0: PaymentAppEntity, 1: array<string, mixed>}
     */
    protected function authenticate(Request $request): array
    {
        $params = $this->authenticator->authParametersOf($request, $this->payload($request));

        return [$this->authenticator->authenticate($request, $params), $params];
    }

    /**
     * The json body of the request, without the auth parameters being stripped - the signature covers
     * the business parameters of the very same payload.
     *
     * @throws PaymentException
     *
     * @return array<string, mixed>
     */
    protected function payload(Request $request): array
    {
        $content = trim($request->getContent());

        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        if (!\is_array($decoded)) {
            throw PaymentException::missingParameter('body');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function json(array $data, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        return new JsonResponse($data, $status, [], false);
    }
}
