<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\OAuth\Scope;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Shopwell\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
#[BecomesFinal(version: 'v6.8.0')]
class UserVerifiedScope implements ScopeEntityInterface
{
    final public const IDENTIFIER = 'user-verified';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function jsonSerialize(): mixed
    {
        return self::IDENTIFIER;
    }
}
