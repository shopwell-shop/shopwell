<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;

#[Package('framework')]
class ContextTokenStruct extends Struct
{
    public function __construct(protected string $token)
    {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        unset($data['token']);

        $data[PlatformRequest::HEADER_CONTEXT_TOKEN] = $this->getToken();

        return $data;
    }

    public function getApiAlias(): string
    {
        return 'context_token';
    }
}
