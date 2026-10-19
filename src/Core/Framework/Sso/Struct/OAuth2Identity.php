<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * Normalized user information a platform returns after a successful authorization. Fields may be
 * missing depending on the platform and the granted scopes.
 */
#[Package('framework')]
class OAuth2Identity extends Struct
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        protected string $technicalName,
        protected string $providerUserId,
        protected ?string $email = null,
        protected bool $emailVerified = false,
        protected ?string $nickname = null,
        protected ?string $avatar = null,
        protected array $raw = [],
    ) {
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    /**
     * The user id the platform uses for this account, e.g. the `sub` of Google.
     */
    public function getProviderUserId(): string
    {
        return $this->providerUserId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    public function getApiAlias(): string
    {
        return 'oauth2_identity_struct';
    }
}
