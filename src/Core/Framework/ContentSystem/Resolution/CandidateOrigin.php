<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Resolution;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum CandidateOrigin: string
{
    case Parent = 'parent';
    case Loader = 'loader';
    case Stored = 'stored';
    case Root = 'root';
}
