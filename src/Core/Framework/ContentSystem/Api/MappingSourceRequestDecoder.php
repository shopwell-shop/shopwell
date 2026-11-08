<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Api;

use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopwell\Core\Framework\Log\Package;

/**
 * Converts a client-supplied mapping source without exposing stored-layout codec errors as HTTP 500.
 *
 * @internal
 */
#[Package('framework')]
final class MappingSourceRequestDecoder
{
    /**
     * @param array<string, mixed> $source
     */
    public static function decode(array $source): MappingSourceReference
    {
        try {
            return MappingSourceReference::fromArray($source, 'source');
        } catch (ContentSystemException $exception) {
            throw ContentSystemException::invalidMappingSource($exception);
        }
    }
}
