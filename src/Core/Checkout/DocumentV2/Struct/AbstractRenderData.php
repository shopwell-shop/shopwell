<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DocumentV2\Struct;

use Shopwell\Core\Framework\Log\Package;

/**
 * Marker base for provider-specific render-data DTOs stored in {@see RenderInput}.
 *
 * Each document data provider returns its own subtype so renderers can consume typed, precomputed
 * input instead of reaching back into the data loading layer. The base holds no state: shared data
 * lives in {@see \Shopwell\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData}
 *
 * @experimental stableVersion:v6.8.0 feature:DOCUMENT_GENERATION_REWORK
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Checkout\DocumentV2\Renderer\DocumentRendererSnapshotTest
 */
#[Package('after-sales')]
abstract readonly class AbstractRenderData
{
}
