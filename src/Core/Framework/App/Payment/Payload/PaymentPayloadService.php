<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Payment\Payload;

use GuzzleHttp\ClientInterface;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopwell\Core\Framework\App\Payload\SourcedPayloadInterface;
use Shopwell\Core\Framework\App\Payment\Response\AbstractResponse;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Exception\JsonDecodingException;
use Shopwell\Core\Framework\Util\Json;

/**
 * @internal only for use by the app-systems
 */
#[Package('checkout')]
class PaymentPayloadService
{
    public const PAYMENT_REQUEST_TIMEOUT = 20;

    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly ClientInterface $client,
    ) {
    }

    /**
     * @template T of AbstractResponse
     *
     * @param class-string<T> $responseClass
     *
     * @return T
     */
    public function request(
        string $url,
        SourcedPayloadInterface $payload,
        AppEntity $app,
        string $responseClass,
        Context $context
    ): AbstractResponse {
        $optionRequest = $this->helper->createRequestOptions(
            $payload,
            $app,
            $context,
            [
                'timeout' => self::PAYMENT_REQUEST_TIMEOUT,
            ],
        );

        $response = $this->client->request('POST', $url, $optionRequest->jsonSerialize());
        $content = $response->getBody()->getContents();

        try {
            $decoded = Json::decodeToArray($content);
        } catch (JsonDecodingException $e) {
            throw AppException::paymentGatewayRequestFailed($app->getName(), $e);
        }

        return $responseClass::create($decoded);
    }
}
