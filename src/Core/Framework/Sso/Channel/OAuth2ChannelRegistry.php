<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Channel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\SsoException;

/**
 * The channels the shop can offer, collected from the services tagged as
 * `shopwell.sso.oauth2_channel`.
 *
 * Which of them are actually usable is decided by the admin: a channel without a
 * {@see \Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2ClientEntity} row, or with a
 * disabled one, is offered nowhere.
 *
 * @internal
 */
#[Package('framework')]
final class OAuth2ChannelRegistry
{
    /**
     * @var array<string, OAuth2Channel>
     */
    private array $channels = [];

    /**
     * @param iterable<OAuth2Channel> $channels
     */
    public function __construct(iterable $channels)
    {
        foreach ($channels as $channel) {
            $this->channels[$channel->technicalName] = $channel;
        }
    }

    /**
     * @return array<string, OAuth2Channel>
     */
    public function all(): array
    {
        return $this->channels;
    }

    public function has(string $technicalName): bool
    {
        return isset($this->channels[$technicalName]);
    }

    public function get(string $technicalName): OAuth2Channel
    {
        return $this->channels[$technicalName] ?? throw SsoException::oauth2ProviderNotAvailable($technicalName);
    }
}
