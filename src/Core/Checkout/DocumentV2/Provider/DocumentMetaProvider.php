<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DocumentV2\Provider;

use Shopwell\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopwell\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopwell\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopwell\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Provides the {@see DocumentMetaRenderData} shared by every document type, so type-specific
 * providers only contribute their own fields.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentMetaProvider extends AbstractDocumentDataProvider
{
    final public const KEY = 'meta';

    public function __construct(
        private DocumentConfigLoader $documentConfigLoader,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function supports(string $documentType): bool
    {
        return true;
    }

    public function provideRenderingData(
        ProviderInput $input,
        Context $context,
    ): DocumentMetaRenderData {
        $generationRequest = $input->generationRequest;
        $documentNumber = $generationRequest->documentNumber;

        if ($documentNumber === null) {
            throw DocumentV2Exception::missingDocumentNumber($generationRequest->documentType);
        }

        $bundle = $this->documentConfigLoader->load(
            $generationRequest->documentType,
            $input->order->getSalesChannelId(),
            $context,
        );

        return new DocumentMetaRenderData(
            config: $bundle->config,
            company: $bundle->company,
            display: $bundle->display,
            documentDate: $generationRequest->documentDate,
            documentNumber: $documentNumber,
            documentComment: $generationRequest->documentComment,
            legacyConfig: $bundle->legacyConfig,
        );
    }
}
