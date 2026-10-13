<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Struct\OAuth2Identity;

/**
 * Fired after a login through an external account authenticated an existing bound account.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class OAuth2LoginSucceededEvent implements ShopwellEvent
{
    /**
     * @param 'storefront'|'admin' $area
     * @param 'customer'|'user' $accountType
     * @param array<string, mixed> $extra
     */
    public function __construct(
        private readonly OAuth2Identity $identity,
        private readonly string $area,
        private readonly string $accountType,
        private readonly string $accountId,
        private readonly Context $context,
        private array $extra = [],
    ) {
    }

    public function getIdentity(): OAuth2Identity
    {
        return $this->identity;
    }

    public function getArea(): string
    {
        return $this->area;
    }

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function setExtra(array $extra): void
    {
        $this->extra = $extra;
    }
}
