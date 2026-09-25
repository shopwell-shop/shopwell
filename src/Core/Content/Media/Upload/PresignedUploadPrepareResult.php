<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Upload;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class PresignedUploadPrepareResult
{
    public function __construct(
        public string $mediaId,
        public string $url,
        public string $path,
        public string $expiresAt,
        public bool $isDuplicate,
    ) {
    }
}
