<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Upload;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class FileMetadataResult
{
    public function __construct(
        public int $size,
        public \DateTimeImmutable $lastModified,
        public ?string $etag = null,
        public ?string $contentType = null,
    ) {
    }
}
