<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Channel;

use Shopwell\Core\Framework\Log\Package;

/**
 * One channel the shop offers for login, i.e. one entry the admin can enable and configure.
 *
 * A channel is data, not code. It selects one of the provider types oauth2-client-bundle ships and
 * adds what a login needs on top of the credentials - label, icon, scopes and the parameters of the
 * channel itself. The channels the platform ships are declared in the DI file of the module, so an
 * extension adds a channel of its own by declaring one of these and tagging it as
 * `shopwell.sso.oauth2_channel`, without touching the platform and without a new class per channel.
 *
 * Everything the admin gets to fill in is declared here as well, so the administration renders the
 * form of a channel it has never heard of, see {@see \Shopwell\Core\Framework\Sso\Service\OAuth2ChannelFormSchema}.
 *
 * A channel oauth2-client-bundle does not ship passes its own league provider class through the
 * `generic` type, see {@see OAuth2Channel::$bundleConfiguration}.
 */
#[Package('framework')]
final readonly class OAuth2Channel
{
    /**
     * @param list<string> $defaultScopes scopes requested when the admin configured none for this
     *                                    channel
     * @param list<array{value: string, label: array<string, string|null>}> $scopeChoices the scopes
     *                                                                                    the channel accepts, in the order they should be offered.
     *                                                                                    Most channels take exactly one value - WeChat Open Platform
     *                                                                                    only `snsapi_login`, Google only its profile scopes - and
     *                                                                                    leave this empty, because there is nothing to choose and a
     *                                                                                    field for it would only invite the admin to break the
     *                                                                                    login. Channels that do have a choice, like the WeChat
     *                                                                                    official account with `snsapi_base` (silent, openid only)
     *                                                                                    against `snsapi_userinfo` (consent screen, nickname and
     *                                                                                    avatar), declare the alternatives here.
     * @param list<array{name: string, type: string, config: array<string, mixed>}> $settings the
     *                                                                                        parameters of the channel besides the credentials, written
     *                                                                                        in the notation of a system config input field: `name` is
     *                                                                                        the key the value is stored under, `type` one of the types
     *                                                                                        `sw-form-field-renderer` renders, `config` its props with
     *                                                                                        `label`, `helpText` and `required`. Labels are inline
     *                                                                                        snippets (locale => text) - a channel carries its own
     *                                                                                        translations that way and needs no snippet file of ours,
     *                                                                                        which is what lets an extension ship its own labels.
     *                                                                                        The names are the ones the configuration tree of the
     *                                                                                        shape builds expects: keycloak takes `auth_server_url` and
     *                                                                                        `realm`, apple takes `team_id`, `key_file_id` and
     *                                                                                        `key_file_path`. A name the tree does not know makes the
     *                                                                                        login fail, so take them from the configurator of the
     *                                                                                        bundle type.
     * @param array<string, mixed> $bundleConfiguration additional keys of the bundle configuration
     *                                                  tree of the selected type, e.g. `provider_class` and
     *                                                  `provider_options` for `generic`. These take the same shape
     *                                                  as a static `knpu_oauth2_client.clients` entry.
     */
    public function __construct(
        public string $technicalName,
        public string $bundleType,
        public string $labelSnippet,
        public ?string $icon = null,
        public array $defaultScopes = [],
        public bool $providesVerifiedEmail = false,
        public bool $supportsAdminLogin = false,
        public array $bundleConfiguration = [],
        public array $settings = [],
        public array $scopeChoices = [],
    ) {
    }

    /**
     * Whether the admin has a scope to choose at all. A channel that takes one fixed scope value -
     * the common case - must not offer a field for it: the only thing an admin could do with it is
     * get it wrong, and the platform answers a wrong scope with an authorization error the shop
     * never sees the reason of.
     */
    public function offersScopeChoice(): bool
    {
        return \count($this->scopeChoices) > 1;
    }
}
