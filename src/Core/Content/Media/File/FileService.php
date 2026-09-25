<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\File;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class FileService
{
    private const ALLOWED_PROTOCOLS = ['http', 'https', 'ftp', 'sftp'];

    public function isUrl(string $url): bool
    {
        return (bool) filter_var($url, \FILTER_VALIDATE_URL) && $this->isProtocolAllowed($url);
    }

    private function isProtocolAllowed(string $url): bool
    {
        $fragments = explode(':', $url);

        return \in_array($fragments[0], self::ALLOWED_PROTOCOLS, true);
    }
}
