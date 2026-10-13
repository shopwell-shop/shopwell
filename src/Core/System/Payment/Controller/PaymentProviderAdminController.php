<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Controller;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Service\PaymentCredentialCipher;
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
 * The credentials never leave the server: a field marked as secret is never echoed back, the endpoint
 * only reports whether a value is stored, and everything it stores is sealed with the same cipher the
 * gateway reads with. Fields that are not secret are echoed back, so an operator can see which merchant
 * account an interface runs on.
 *
 * The two configurations are deliberately separate urls: the credentials belong to the interface and
 * are shared by all of its channels, the parameters (`appid` of a wechat channel, for instance) belong
 * to one channel alone.
 *
 * @internal
 */
#[Package('framework')]
class PaymentProviderAdminController extends AbstractController
{
    /**
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     *
     * @internal
     */
    public function __construct(
        private readonly PaymentProviderRegistry $providerRegistry,
        private readonly EntityRepository $providerConfigRepository,
        private readonly EntityRepository $channelConfigRepository,
        private readonly PaymentCredentialCipher $cipher,
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
                $this->loadProviderConfig($provider->technicalName),
                $this->loadChannelConfigs($provider->technicalName)
            );
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
        $enabled = (bool) ($payload['enabled'] ?? false);

        $config = $this->loadProviderConfig($provider);
        // the values that are not sent stay untouched, which is what makes it possible to edit an
        // interface without typing the secrets again
        $credentials = $this->mergeValues($config === null ? [] : $this->cipher->decrypt($config->credentials), $payload['credentials'] ?? []);
        $settings = array_merge($config->settings ?? [], $this->mapOf($payload['settings'] ?? null));

        if ($enabled) {
            $this->assertComplete($provider, $definition->credentials, $credentials);
        }

        $this->providerConfigRepository->upsert([[
            'id' => $config->id ?? Uuid::randomHex(),
            'providerTechnicalName' => $provider,
            'enabled' => $enabled,
            'credentials' => $this->cipher->encrypt($credentials),
            'settings' => $settings,
        ]], Context::createDefaultContext());

        return new JsonResponse([
            'provider' => $this->presentProvider($definition, $this->loadProviderConfig($provider), $this->loadChannelConfigs($provider)),
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
        $config = $this->loadProviderConfig($provider);

        if ($config !== null) {
            $this->providerConfigRepository->delete([['id' => $config->id]], Context::createDefaultContext());
        }

        return new JsonResponse([
            'provider' => $this->presentProvider($definition, null, $this->loadChannelConfigs($provider)),
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

        if (!$definition->hasChannel($channel)) {
            throw PaymentException::unknownChannel($channel);
        }

        $channelDefinition = $definition->channels[$channel];

        $payload = $this->payload($request);
        $enabled = (bool) ($payload['enabled'] ?? false);

        $config = $this->loadChannelConfig($provider, $channel);
        $params = $this->mergeValues($config === null ? [] : $this->cipher->decrypt($config->params), $payload['params'] ?? []);
        $settings = array_merge($config->settings ?? [], $this->mapOf($payload['settings'] ?? null));

        if ($enabled) {
            $this->assertComplete($channel, $channelDefinition->params, $params);
        }

        $this->channelConfigRepository->upsert([[
            'id' => $config->id ?? Uuid::randomHex(),
            'providerTechnicalName' => $provider,
            'channelTechnicalName' => $channel,
            'enabled' => $enabled,
            'params' => $this->cipher->encrypt($params),
            'settings' => $settings,
        ]], Context::createDefaultContext());

        return new JsonResponse([
            'channel' => $this->presentChannel($channelDefinition, $this->loadChannelConfig($provider, $channel)),
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

        if (!$definition->hasChannel($channel)) {
            throw PaymentException::unknownChannel($channel);
        }

        $config = $this->loadChannelConfig($provider, $channel);

        if ($config !== null) {
            $this->channelConfigRepository->delete([['id' => $config->id]], Context::createDefaultContext());
        }

        return new JsonResponse([
            'channel' => $this->presentChannel($definition->channels[$channel], null),
        ]);
    }

    /**
     * @param array<string, PaymentChannelConfigEntity> $channelConfigs
     *
     * @return array<string, mixed>
     */
    private function presentProvider(PaymentProvider $provider, ?PaymentProviderConfigEntity $config, array $channelConfigs): array
    {
        $credentials = $config === null ? [] : $this->cipher->decrypt($config->credentials);

        $channels = [];
        foreach ($provider->channels as $channel) {
            $channels[] = $this->presentChannel($channel, $channelConfigs[$channel->technicalName] ?? null);
        }

        return [
            'technicalName' => $provider->technicalName,
            'label' => $provider->label,
            'enabled' => $config !== null && $config->enabled,
            'configured' => $credentials !== [],
            'credentialFields' => $this->presentFields($provider->credentials, $credentials),
            'settings' => $config->settings ?? [],
            'channels' => $channels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentChannel(PaymentChannel $channel, ?PaymentChannelConfigEntity $config): array
    {
        $params = $config === null ? [] : $this->cipher->decrypt($config->params);

        return [
            'channel' => $channel->technicalName,
            'label' => $channel->label,
            'flow' => $channel->flow->value,
            'currencies' => $channel->currencies,
            'devices' => $channel->devices,
            'runtimeParams' => $channel->runtimeParams,
            'paramFields' => $this->presentFields($channel->params, $params),
            'enabled' => $config !== null && $config->enabled,
            'configured' => $params !== [],
            'settings' => $config->settings ?? [],
        ];
    }

    /**
     * The fields of a configuration as the form renders them.
     *
     * A secret field is never echoed back - the form starts empty and only learns whether a value is
     * stored. Every other field is handed over with its stored value, so the operator sees what the
     * interface runs on.
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
                'value' => $field->secret ? null : ($hasValue ? (string) $stored : null),
                'configured' => $hasValue,
            ];
        }

        return $presented;
    }

    /**
     * An interface or channel that is switched on has to be complete, otherwise the first payment of an
     * app would fail with a message only the log sees.
     *
     * @param list<PaymentConfigField> $fields
     * @param array<string, mixed> $values
     */
    private function assertComplete(string $technicalName, array $fields, array $values): void
    {
        foreach ($fields as $field) {
            if (!$field->required) {
                continue;
            }

            if (!isset($values[$field->name]) || $values[$field->name] === '') {
                throw PaymentException::missingCredentials($technicalName, $field->name);
            }
        }
    }

    /**
     * The values that are not sent stay untouched: an empty input means "keep what is stored", which is
     * the only way to edit an interface whose secrets are never sent to the browser.
     *
     * @param array<string, mixed> $current
     *
     * @return array<string, mixed>
     */
    private function mergeValues(array $current, mixed $incoming): array
    {
        foreach ($this->mapOf($incoming) as $name => $value) {
            if ((string) $value === '') {
                continue;
            }

            $current[$name] = $value;
        }

        return $current;
    }

    /**
     * @return array<string, string>
     */
    private function mapOf(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $name => $item) {
            if (!\is_string($name) || !\is_scalar($item)) {
                continue;
            }

            $map[$name] = (string) $item;
        }

        return $map;
    }

    private function loadProviderConfig(string $provider): ?PaymentProviderConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('providerTechnicalName', $provider));
        $criteria->setLimit(1);

        $config = $this->providerConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $config instanceof PaymentProviderConfigEntity ? $config : null;
    }

    /**
     * @return array<string, PaymentChannelConfigEntity>
     */
    private function loadChannelConfigs(string $provider): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('providerTechnicalName', $provider));

        $configs = [];
        foreach ($this->channelConfigRepository->search($criteria, Context::createDefaultContext())->getEntities() as $config) {
            if ($config instanceof PaymentChannelConfigEntity) {
                $configs[$config->channelTechnicalName] = $config;
            }
        }

        return $configs;
    }

    private function loadChannelConfig(string $provider, string $channel): ?PaymentChannelConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('providerTechnicalName', $provider),
            new EqualsFilter('channelTechnicalName', $channel)
        );
        $criteria->setLimit(1);

        $config = $this->channelConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $config instanceof PaymentChannelConfigEntity ? $config : null;
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
