<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopwell\Core\Checkout\DocumentV2\Provider\ReferencesDocument;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class StaticReferencingDocumentDataProvider extends StaticDocumentDataProvider implements ReferencesDocument
{
}
