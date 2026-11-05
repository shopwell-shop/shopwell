<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Config\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Method\PaymentMethodRegistry;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Config\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigService;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tells the administration which payment interfaces this shop can be configured with, and stores the
 * credentials of an interface and the parameters of its channels.
 *
 * An interface is code and lives in the server: the administration therefore asks here instead of
 * shipping a list of its own, which would silently drift apart from the interfaces the shop actually
 * supports - including the ones an extension adds. What the administration renders comes from the
 * `payment-provider.xml` of the interface, so an interface of an extension is configurable without
 * touching the administration at all.
 *
 * Non-secret values are echoed back so an operator can read the merchant account an interface runs
 * on and correct a single field without retyping the rest. A secret only reports whether it is
 * configured; its original value is never returned. Everything stays sealed with the same cipher the
 * gateway reads with. The sealing and the writing itself live in the
 * {@see PaymentProviderConfigService}, this controller only translates between the request and the
 * presentation.
 *
 * The two configurations are deliberately separate urls: the credentials belong to the interface and
 * are shared by all of its channels, the parameters (`appid` of a wechat channel, for instance) belong
 * to one channel alone.
 *
 * @internal
 */
#[Package('framework')]
class PaymentProviderConfigController extends AbstractController
{
    public function __construct(
        private readonly PaymentProviderRegistry $providerRegistry,
        private readonly PaymentProviderConfigService $configService,
        private readonly PaymentCredentialCipher $cipher,
        private readonly PaymentMethodRegistry $methods,
    ) {
    }

    #[Route(
        path: '/api/_action/payment/providers',
        name: 'api.action.payment.providers',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_provider_config:read'],
        ],
        methods: [Request::METHOD_GET]
    )]
    public function list(): JsonResponse
    {
        $providers = [];

        foreach ($this->providerRegistry->all() as $provider) {
            $providers[] = $this->presentProvider(
                $provider,
                $this->configService->findProviderConfig($provider->technicalName),
                $this->configService->findChannelConfigs($provider->technicalName)
            );
        }

        return new JsonResponse(['providers' => $providers]);
    }

    #[Route(
        path: '/api/_action/payment/account-definitions',
        name: 'api.action.payment.account.definitions',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_account:read'],
        ],
        methods: [Request::METHOD_GET]
    )]
    #[Route(
        path: '/api/_action/payment/definitions',
        name: 'api.action.payment.definitions',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_app:read'],
        ],
        methods: [Request::METHOD_GET]
    )]
    public function definitions(): JsonResponse
    {
        $providers = [];
        foreach ($this->providerRegistry->all() as $provider) {
            $providers[] = $this->presentProvider($provider, null, []);
        }

        return new JsonResponse(['providers' => $providers]);
    }

    #[Route(
        path: '/api/_action/payment/providers/{provider}',
        name: 'api.action.payment.provider.save',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_provider_config:update'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function save(string $provider, Request $request): JsonResponse
    {
        // an unknown interface is a 404, an interface of an extension that is not installed anymore
        // must not be silently writable
        $definition = $this->providerRegistry->get($provider);

        $payload = $this->payload($request);

        $this->configService->saveProvider(
            $definition,
            (bool) ($payload['enabled'] ?? false),
            $payload['credentials'] ?? [],
            $payload['settings'] ?? null
        );

        return new JsonResponse([
            'provider' => $this->presentProvider($definition, $this->configService->findProviderConfig($provider), $this->configService->findChannelConfigs($provider)),
        ]);
    }

    #[Route(
        path: '/api/_action/payment/providers/{provider}',
        name: 'api.action.payment.provider.reset',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_provider_config:delete'],
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function reset(string $provider): JsonResponse
    {
        $definition = $this->providerRegistry->get($provider);

        $this->configService->resetProvider($provider);

        return new JsonResponse([
            'provider' => $this->presentProvider($definition, $this->configService->findProviderConfig($provider), $this->configService->findChannelConfigs($provider)),
        ]);
    }

    #[Route(
        path: '/api/_action/payment/providers/{provider}/channels/{channel}',
        name: 'api.action.payment.provider.channel.save',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_channel_config:update'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function saveChannel(string $provider, string $channel, Request $request): JsonResponse
    {
        $definition = $this->providerRegistry->get($provider);

        if (!$definition->hasProduct($channel)) {
            throw PaymentProviderException::unknownChannel($channel);
        }

        $channelDefinition = $definition->products[$channel];

        $payload = $this->payload($request);

        $this->configService->saveChannel(
            $channelDefinition,
            (bool) ($payload['enabled'] ?? false),
            $payload['params'] ?? [],
            $payload['settings'] ?? null
        );

        return new JsonResponse([
            'channel' => $this->presentChannel($channelDefinition, $this->configService->findChannelConfig($provider, $channel), $this->providerAccount($provider)),
        ]);
    }

    #[Route(
        path: '/api/_action/payment/providers/{provider}/channels/{channel}',
        name: 'api.action.payment.provider.channel.reset',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_channel_config:delete'],
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function resetChannel(string $provider, string $channel): JsonResponse
    {
        $definition = $this->providerRegistry->get($provider);

        if (!$definition->hasProduct($channel)) {
            throw PaymentProviderException::unknownChannel($channel);
        }

        $this->configService->resetChannel($provider, $channel);

        return new JsonResponse([
            'channel' => $this->presentChannel($definition->products[$channel], null),
        ]);
    }

    /**
     * @param array<string, PaymentChannelConfigEntity> $channelConfigs
     *
     * @return array<string, mixed>
     */
    private function presentProvider(PaymentProvider $provider, ?PaymentProviderConfigEntity $config, array $channelConfigs): array
    {
        $account = $config === null ? null : $this->configService->findAccount($config->accountId);
        $credentials = $this->cipher->decrypt($account?->credentials);

        $channels = [];
        foreach ($provider->products as $channel) {
            $channels[] = $this->presentChannel($channel, $channelConfigs[$channel->technicalName] ?? null, $account);
        }

        return [
            'technicalName' => $provider->technicalName,
            'label' => $provider->label,
            'enabled' => $config !== null && $config->enabled,
            'configured' => $credentials !== [],
            'accountId' => $config?->accountId,
            'credentialFields' => $this->presentFields($provider->credentials, $credentials),
            'settings' => $config->settings ?? [],
            'products' => $channels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentChannel(PaymentProduct $channel, ?PaymentChannelConfigEntity $config, ?PaymentAccountEntity $account = null): array
    {
        $params = $this->cipher->decrypt($account?->productParams[$channel->technicalName] ?? null);

        return [
            'product' => $channel->technicalName,
            'method' => $channel->method,
            'methodTranslationKey' => $this->methods->get($channel->method)->translationKey,
            'label' => $channel->label,
            'interactions' => $channel->interactions,
            'currencies' => $channel->currencies,
            'devices' => $channel->devices(),
            'runtimeParams' => $channel->runtimeParams,
            'paramFields' => $this->presentFields($channel->params, $params),
            'enabled' => $account !== null && \in_array($channel->technicalName, $account->enabledProducts, true),
            'configured' => $params !== [],
            'settings' => $config->settings ?? [],
        ];
    }

    private function providerAccount(string $provider): ?PaymentAccountEntity
    {
        $config = $this->configService->findProviderConfig($provider);

        return $config === null ? null : $this->configService->findAccount($config->accountId);
    }

    /**
     * The fields of a configuration as the form renders them.
     *
     * A non-secret field carries its stored value. A secret only carries `configured`: the empty input
     * means "keep the stored secret" when the form is saved, so no original value has to cross the api.
     *
     * @param list<PaymentConfigField> $fields
     * @param array<string, mixed> $values
     *
     * @return list<array<string, mixed>>
     */
    private function presentFields(array $fields, array $values): array
    {
        $presented = [];

        foreach ($fields as $field) {
            $stored = $values[$field->name] ?? null;
            $hasValue = \is_scalar($stored) && (string) $stored !== '';

            $presented[] = $field->toArray() + [
                'value' => $hasValue && !$field->secret ? (string) $stored : null,
                'configured' => $hasValue,
            ];
        }

        return $presented;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $content = $request->getContent();

        if ($content !== '') {
            $decoded = json_decode($content, true);

            if (\is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->request->all();
    }
}
