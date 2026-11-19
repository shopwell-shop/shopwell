<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Configuration;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class StartNodeConfiguration implements NodeConfiguration
{
    public function __construct(public string $requestType)
    {
    }

    /**
     * @return array{requestType: string}
     */
    public function jsonSerialize(): array
    {
        return ['requestType' => $this->requestType];
    }
}
