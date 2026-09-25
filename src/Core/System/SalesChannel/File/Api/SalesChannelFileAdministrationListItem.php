<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\File\Api;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class SalesChannelFileAdministrationListItem
{
    public function __construct(
        public string $fileFamily,
        public string $fileName,
        public string $contentType,
        public ?SalesChannelFileAdministrationConfiguration $configuration,
    ) {
    }
}
