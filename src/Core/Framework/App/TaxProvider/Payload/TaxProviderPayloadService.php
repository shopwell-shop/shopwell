<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\TaxProvider\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopwell\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopwell\Core\Framework\App\TaxProvider\Response\TaxProviderResponse;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\ExceptionLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Exception\JsonDecodingException;
use Shopwell\Core\Framework\Util\Json;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class TaxProviderPayloadService
{
    public function __construct(
        private readonly AppPayloadServiceHelper $helper,
        private readonly Client $client,
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function request(
        string $url,
        TaxProviderPayload $payload,
        AppEntity $app,
        Context $context
    ): ?TaxProviderResult {
        $optionRequest = $this->helper->createRequestOptions($payload, $app, $context);

        try {
            $response = $this->client->post($url, $optionRequest->jsonSerialize());
            $content = $response->getBody()->getContents();

            $decoded = Json::decodeToArray($content);
        } catch (GuzzleException|JsonDecodingException $e) {
            $this->logger->logOrThrowException($e);

            return null;
        }

        try {
            return TaxProviderResponse::create($decoded);
        } catch (AppException $e) {
            $this->logger->logOrThrowException($e);

            return null;
        }
    }
}
