<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Service;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;

/**
 * The fields the administration shows for a channel, in the shape `sw-form-field-renderer` renders.
 *
 * Two halves make up the form: the credentials and the scopes every channel has, which are built
 * here, and the parameters a channel adds on top, which it declares itself. The administration
 * therefore renders the form of a channel it knows nothing about, which is what lets an extension
 * add one without touching the administration - the same reason the channels themselves are data.
 *
 * The field names double as the keys of the values, the `target` says where a value belongs:
 * `clientId`, `clientSecret` and `scopes` are columns of the `oauth2_client` row, everything else
 * goes into its `settings` column. Only the fields declared here are read back from there and
 * written to it, so a channel that drops a parameter in a newer version does not keep feeding the
 * stored value to the provider.
 *
 * @internal
 */
#[Package('framework')]
final class OAuth2ChannelFormSchema
{
    final public const TARGET_CLIENT_ID = 'clientId';
    final public const TARGET_CLIENT_SECRET = 'clientSecret';
    final public const TARGET_SCOPES = 'scopes';
    final public const TARGET_SETTINGS = 'settings';

    public function __construct(
        private readonly OAuth2ClientFactory $clientFactory,
    ) {
    }

    /**
     * @return list<array{name: string, type: string, target: string, config: array<string, mixed>}>
     */
    public function build(OAuth2Channel $channel): array
    {
        $fields = [
            [
                'name' => self::TARGET_CLIENT_ID,
                'type' => 'text',
                'target' => self::TARGET_CLIENT_ID,
                'config' => [
                    'label' => 'sw-settings-oauth2-login.channel.clientId',
                    'required' => true,
                ],
            ],
        ];

        if ($this->clientFactory->needsClientSecret($channel)) {
            $fields[] = [
                'name' => self::TARGET_CLIENT_SECRET,
                'type' => 'password',
                'target' => self::TARGET_CLIENT_SECRET,
                'config' => [
                    'label' => 'sw-settings-oauth2-login.channel.clientSecret',
                    'required' => true,
                ],
            ];
        }

        // A channel that takes one fixed scope value has nothing to choose, and a field the admin can
        // only get wrong is worse than no field: the platform answers a wrong scope with an
        // authorization error the shop never sees the reason of.
        if ($channel->offersScopeChoice()) {
            $fields[] = [
                'name' => self::TARGET_SCOPES,
                'type' => 'multi-select',
                'target' => self::TARGET_SCOPES,
                'config' => [
                    'label' => 'sw-settings-oauth2-login.channel.scopes',
                    'placeholder' => implode(', ', $channel->defaultScopes),
                    'options' => $channel->scopeChoices,
                ],
            ];
        }

        foreach ($channel->settings as $setting) {
            $fields[] = [
                'name' => $setting['name'],
                'type' => $setting['type'],
                'target' => self::TARGET_SETTINGS,
                'config' => $setting['config'],
            ];
        }

        return $fields;
    }
}
