<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\OAuth\User;

use League\OAuth2\Server\Entities\UserEntityInterface;
use Shopwell\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopwell\Core\Framework\Log\Package;

/**
 * OAuth integrations should rely on {@see UserEntityInterface} instead of this concrete Shopwell class.
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class User implements UserEntityInterface
{
    /**
     * @param non-empty-string $userId
     */
    public function __construct(private readonly string $userId)
    {
    }

    /**
     * Return the user's identifier.
     *
     * @return non-empty-string
     */
    public function getIdentifier(): string
    {
        return $this->userId;
    }
}
