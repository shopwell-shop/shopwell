<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Payload;

use GuzzleHttp\Psr7\Request;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopwell\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\InAppPurchase;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Webhook\Service\WebhookRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppPayloadServiceHelper
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly JsonEntityEncoder $entityEncoder,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly InAppPurchase $inAppPurchase,
        private readonly string $shopUrl,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws ShopIdChangeSuggestedException
     */
    public function buildSource(string $appVersion, string $appName): Source
    {
        return new Source(
            $this->shopUrl,
            $this->shopIdProvider->getShopId()->id,
            $appVersion,
            $this->inAppPurchase->getJWTByExtension($appName),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $webhookHeaders
     */
    public function createWebhookRequest(
        array $payload,
        string $url,
        string $shopwellVersion,
        int $connectionTimeout,
        int $requestTimeout,
        ?string $secret = null,
        ?string $languageId = null,
        ?string $userLocale = null,
        array $webhookHeaders = [],
    ): WebhookRequest {
        $timestamp = $this->clock->now()->getTimestamp();

        $payload['timestamp'] = $timestamp;

        $jsonPayload = json_encode($payload, \JSON_THROW_ON_ERROR);

        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'sw-version' => $shopwellVersion,
            ],
            $webhookHeaders
        );

        if ($languageId !== null && $userLocale !== null) {
            $headers[AuthMiddleware::SHOPWELL_CONTEXT_LANGUAGE] = $languageId;
            $headers[AuthMiddleware::SHOPWELL_USER_LANGUAGE] = $userLocale;
        }

        $request = new Request(
            'POST',
            $url,
            $headers,
            $jsonPayload
        );

        $options = [
            'connect_timeout' => $connectionTimeout,
            'timeout' => $requestTimeout,
        ];

        if ($secret !== null) {
            $options[AuthMiddleware::APP_REQUEST_TYPE] = [AuthMiddleware::APP_SECRET => $secret];
        }

        return new WebhookRequest(
            $request,
            $headers,
            $jsonPayload,
            $timestamp,
            $options,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(SourcedPayloadInterface $payload): array
    {
        $array = $payload->jsonSerialize();

        foreach ($array as $propertyName => $property) {
            if ($property instanceof SalesChannelContext) {
                $array[$propertyName] = $this->encodeSalesChannelContext($property);
            } elseif ($property instanceof Entity) {
                $array[$propertyName] = $this->encodeEntity($property);
            } elseif ($property instanceof RequestDataBag) {
                $array[$propertyName] = $property->all();
            }
        }

        return $array;
    }

    /**
     * @param array{timeout?: int} $additionalOptions
     */
    public function createRequestOptions(
        SourcedPayloadInterface $payload,
        AppEntity $app,
        Context $context,
        array $additionalOptions = []
    ): AppPayloadStruct {
        if (!$app->getAppSecret()) {
            throw AppException::registrationFailed($app->getName(), 'App secret is missing');
        }

        $defaultOptions = [
            AuthMiddleware::APP_REQUEST_CONTEXT => $context,
            AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => $app->getAppSecret(),
                AuthMiddleware::VALIDATED_RESPONSE => true,
            ],
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->buildPayload($payload, $app),
        ];

        return new AppPayloadStruct(\array_merge($defaultOptions, $additionalOptions));
    }

    private function buildPayload(SourcedPayloadInterface $payload, AppEntity $app): string
    {
        $payload->setSource($this->buildSource($app->getVersion(), $app->getName()));
        $encoded = $this->encode($payload);

        return \json_encode($encoded, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeEntity(Entity $entity): array
    {
        $definition = $this->definitionRegistry->getByEntityName($entity->getApiAlias());

        return $this->entityEncoder->encode(
            new Criteria(),
            $definition,
            $entity,
            '/api'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeSalesChannelContext(SalesChannelContext $salesChannelContext): array
    {
        $array = $salesChannelContext->jsonSerialize();

        foreach ($array as $propertyName => $property) {
            if ($property instanceof Entity) {
                $array[$propertyName] = $this->encodeEntity($property);
            }
        }

        return $array;
    }
}
