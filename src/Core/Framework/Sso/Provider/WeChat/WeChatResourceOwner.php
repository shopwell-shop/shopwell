<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Provider\WeChat;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Shopwell\Core\Framework\Log\Package;

/**
 * The userinfo response of WeChat: the id is `unionid` when it is returned and `openid` otherwise.
 * The unionid is stable across all apps of one open platform account, the openid only per app, so
 * the binding survives a replacement of the app credentials.
 */
#[Package('framework')]
readonly class WeChatResourceOwner implements ResourceOwnerInterface
{
    /**
     * @param array<string, mixed> $response
     */
    public function __construct(private array $response)
    {
    }

    public function getId(): string
    {
        return (string) ($this->response['unionid'] ?? $this->response['openid'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->response;
    }
}
