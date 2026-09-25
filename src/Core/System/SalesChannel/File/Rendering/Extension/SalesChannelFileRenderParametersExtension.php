<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\File\Rendering\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @public this class is used as type-hint for event listeners, so the class string is "public consumable" API
 *
 * @description Allows adding file-specific Twig parameters while rendering a sales channel file.
 * Subscribe to `SalesChannelFileRenderParametersExtension::onPost()` and add custom values to `$extension->result`.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<array<string, mixed>>
 */
#[Package('framework')]
final class SalesChannelFileRenderParametersExtension extends Extension
{
    public const NAME = 'sales-channel-file.render-parameters';

    /**
     * @internal Shopwell owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The sales channel file currently being rendered
         */
        public readonly SalesChannelFile $file,

        /**
         * @public
         *
         * @description The current sales channel context
         */
        public readonly SalesChannelContext $context,

        /**
         * @public
         *
         * @description The sales channel entity loaded for rendering
         */
        public readonly SalesChannelEntity $salesChannel,
    ) {
    }
}
