<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Service;

use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use KnpU\OAuth2ClientBundle\DependencyInjection\KnpUOAuth2ClientExtension;
use KnpU\OAuth2ClientBundle\DependencyInjection\ProviderFactory;
use KnpU\OAuth2ClientBundle\DependencyInjection\Providers\ProviderConfiguratorInterface;
use KnpU\OAuth2ClientBundle\DependencyInjection\Providers\ProviderWithoutClientSecretConfiguratorInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Channel\OAuth2ChannelRegistry;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2ClientEntity;
use Shopwell\Core\Framework\Sso\SsoException;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds the oauth2-client-bundle client of a configured channel.
 *
 * oauth2-client-bundle configures its clients statically in `knpu_oauth2_client.clients`, this shop
 * configures them in the admin instead. The three contributions of a bundle provider configurator -
 * provider class, client class and provider options - are therefore collected per request from the
 * configurator of the channel type, with the credentials of the entity and the route the platform
 * redirects back to.
 *
 * Because it is the one asking the bundle what a channel type wants, it is also where the rest of
 * the platform learns what a channel needs, see {@see needsClientSecret()} and
 * {@see OAuth2ChannelFormSchema}.
 *
 * @internal
 */
#[Package('framework')]
final class OAuth2ClientFactory
{
    public function __construct(
        private readonly ProviderFactory $bundleProviderFactory,
        private readonly KnpUOAuth2ClientExtension $bundleExtension,
        private readonly OAuth2ChannelRegistry $channels,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param string $callbackRouteName the route the platform redirects back to, filled up with the
     *                                  channel as `provider` parameter
     */
    public function create(OAuth2ClientEntity $client, string $callbackRouteName): OAuth2Client
    {
        $channel = $this->channels->get($client->technicalName);
        $configurator = $this->bundleExtension->getConfigurator($channel->bundleType);
        $config = $this->buildConfiguration($channel, $client, $callbackRouteName, $configurator);

        /** @var class-string<OAuth2Client> $clientClass */
        $clientClass = $configurator->getClientClass($config);

        return new $clientClass(
            $this->createProvider($configurator, $config, $callbackRouteName, $channel->technicalName),
            $this->requestStack
        );
    }

    /**
     * Scopes requested during authorization: the configured ones or the channel defaults.
     *
     * @return list<string>
     */
    public function getScopes(OAuth2ClientEntity $client): array
    {
        return $client->scopes ?: $this->channels->get($client->technicalName)->defaultScopes;
    }

    /**
     * Whether the channel is authenticated with a shared secret at all.
     *
     * Most providers are, so the administration only asks for a secret when this says so. Providers
     * that sign their requests with a key of their own are not: Apple, for one, generates the client
     * secret out of its key file on every request, so there is no static secret to store and the
     * bundle does not require one either.
     */
    public function needsClientSecret(OAuth2Channel $channel): bool
    {
        return $this->configuratorNeedsClientSecret($this->bundleExtension->getConfigurator($channel->bundleType));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createProvider(
        ProviderConfiguratorInterface $configurator,
        array $config,
        string $callbackRouteName,
        string $technicalName
    ): AbstractProvider {
        $provider = $this->bundleProviderFactory->createProvider(
            $configurator->getProviderClass($config),
            $configurator->getProviderOptions($config),
            $callbackRouteName,
            ['provider' => $technicalName],
        );

        // the factory instantiates whatever provider class it is given, the bundle can not type it
        \assert($provider instanceof AbstractProvider);

        return $provider;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConfiguration(
        OAuth2Channel $channel,
        OAuth2ClientEntity $client,
        string $callbackRouteName,
        ProviderConfiguratorInterface $configurator
    ): array {
        $this->assertConfigured($channel, $client, $configurator);

        $tree = new TreeBuilder('oauth2_client');
        $options = $tree->getRootNode()->children();

        // the base configuration the bundle builds for every of its clients, see
        // KnpUOAuth2ClientExtension::buildConfigurationForType()
        $options
            ->scalarNode('client_id')->isRequired()->end()
            ->scalarNode('redirect_route')->isRequired()->end()
            ->arrayNode('redirect_params')->prototype('scalar')->end()->end()
            ->booleanNode('use_state')->defaultValue(true)->end();

        $configuration = [
            'client_id' => $client->clientId,
            'redirect_route' => $callbackRouteName,
            'redirect_params' => ['provider' => $client->technicalName],
            'use_state' => true,
        ];

        // the bundle asks for the secret only where the provider has one
        if ($this->configuratorNeedsClientSecret($configurator)) {
            $options
                ->scalarNode('client_secret')->isRequired()->end();

            $configuration['client_secret'] = $client->clientSecret;
        }

        // lets the type add the options it needs, e.g. `access_type` for google
        $configurator->buildConfiguration($options);
        $options->end();

        $configuration += $this->getDeclaredSettings($channel, $client) + $channel->bundleConfiguration;

        return (new Processor())->process($tree->buildTree(), [$configuration]);
    }

    /**
     * A channel can be saved by an api client, by an older administration or by a version of the
     * shop that did not know a parameter yet, so the rules the admin form checks have to hold here
     * as well - and this is the copy that counts.
     */
    private function assertConfigured(
        OAuth2Channel $channel,
        OAuth2ClientEntity $client,
        ProviderConfiguratorInterface $configurator
    ): void {
        if (!$client->clientId) {
            throw SsoException::oauth2ProviderNotConfigured($client->technicalName);
        }

        if ($this->configuratorNeedsClientSecret($configurator) && !$client->clientSecret) {
            throw SsoException::oauth2ProviderNotConfigured($client->technicalName);
        }

        foreach ($channel->settings as $setting) {
            if (($setting['config']['required'] ?? false) !== true) {
                continue;
            }

            $value = $client->settings[$setting['name']] ?? null;

            if ($value === null || $value === '') {
                throw SsoException::oauth2ProviderNotConfigured($client->technicalName);
            }
        }
    }

    /**
     * Only the settings the channel declares are passed on. The column is a free json blob, a channel
     * that dropped a parameter in a newer version would otherwise keep feeding the stored value to
     * the provider.
     *
     * @return array<string, mixed>
     */
    private function getDeclaredSettings(OAuth2Channel $channel, OAuth2ClientEntity $client): array
    {
        $settings = [];

        foreach ($channel->settings as $setting) {
            $name = $setting['name'];

            if (isset($client->settings[$name])) {
                $settings[$name] = $client->settings[$name];
            }
        }

        return $settings;
    }

    private function configuratorNeedsClientSecret(ProviderConfiguratorInterface $configurator): bool
    {
        return !$configurator instanceof ProviderWithoutClientSecretConfiguratorInterface
            || $configurator->needsClientSecret();
    }
}
