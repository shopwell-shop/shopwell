<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\PlatformRequest;

/**
 * @extends StoreApiResponse<ArrayStruct<array{redirectUrl: string|null}>>
 */
#[Package('framework')]
class ContextTokenResponse extends StoreApiResponse
{
    public function __construct(
        string $token,
        ?string $redirectUrl = null
    ) {
        parent::__construct(new ArrayStruct([
            'redirectUrl' => $redirectUrl,
        ]));

        $this->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    public function getToken(): string
    {
        return $this->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
    }

    public function getRedirectUrl(): ?string
    {
        return $this->object->get('redirectUrl');
    }
}
