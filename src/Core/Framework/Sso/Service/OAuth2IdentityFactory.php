<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Service;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\SsoException;
use Shopwell\Core\Framework\Sso\Struct\OAuth2Identity;

/**
 * Maps the resource owner of a platform onto the normalized identity the binding is stored with.
 *
 * The id is taken from ResourceOwnerInterface::getId(), so a platform with a deviating id is
 * handled by its own resource owner class, see WeChatResourceOwner. The optional fields have no
 * counterpart in the league interface and are read from the raw response with the field names the
 * platforms commonly use.
 *
 * @internal
 */
#[Package('framework')]
final class OAuth2IdentityFactory
{
    public function fromResourceOwner(OAuth2Channel $channel, ResourceOwnerInterface $owner): OAuth2Identity
    {
        $providerUserId = (string) $owner->getId();

        if ($providerUserId === '') {
            throw SsoException::oauth2IdentityMissing($channel->technicalName);
        }

        $raw = $owner->toArray();
        $emailVerified = $raw['email_verified'] ?? null;

        return new OAuth2Identity(
            $channel->technicalName,
            $providerUserId,
            $this->firstString($raw, 'email'),
            $emailVerified === null ? $channel->providesVerifiedEmail : filter_var($emailVerified, \FILTER_VALIDATE_BOOLEAN),
            $this->firstString($raw, 'name', 'nickname', 'display_name', 'preferred_username'),
            $this->firstString($raw, 'picture', 'avatar', 'avatar_url', 'headimgurl'),
            $raw,
        );
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function firstString(array $raw, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $raw[$key] ?? null;

            if (\is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
