<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Content\Media\Upload\MediaUploadParameters;
use Shopwell\Core\Content\Media\Upload\MediaUploadService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopwell\Core\Framework\Mcp\Context\McpContextProvider;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-media-upload',
    title: 'Media Upload',
    description: 'Upload any image or file — including product cover images — to Shopwell\'s media library from a URL. url is the only required parameter; productId, fileName, and mediaFolderId are all optional. Call this tool immediately with just the URL whenever the user asks to upload, import, or add an image. Returns the new mediaId.'
)]
#[McpToolGroup('media')]
#[McpToolRequires('media:create')]
#[McpToolRequires('product:update')]
class MediaUploadTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MediaUploadService $mediaUploadService,
        private readonly McpContextProvider $contextProvider,
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    public function __invoke(
        string $url,
        string $fileName = '',
        string $mediaFolderId = '',
        string $productId = '',
    ): string {
        $context = $this->contextProvider->getContext();

        $requiredPrivileges = ['media:create'];
        if ($productId !== '') {
            $requiredPrivileges[] = 'product:update';
        }

        if ($error = $this->requirePrivilege($context, ...$requiredPrivileges)) {
            return $error;
        }

        $params = new MediaUploadParameters(
            mediaFolderId: $mediaFolderId !== '' ? $mediaFolderId : null,
            fileName: $fileName !== '' ? $fileName : null,
        );

        try {
            $mediaId = $this->mediaUploadService->uploadFromURL($url, $context, $params);
        } catch (\Throwable $e) {
            return $this->error('Upload failed: ' . $e->getMessage());
        }

        $result = ['mediaId' => $mediaId];

        if ($productId !== '') {
            try {
                $this->assignToProduct($mediaId, $productId, $context);
                $result['productId'] = $productId;
                $result['assignedAsCover'] = true;
            } catch (\Throwable $e) {
                return $this->error('Media uploaded (ID: ' . $mediaId . ') but product assignment failed: ' . $e->getMessage());
            }
        }

        return $this->success($result);
    }

    private function assignToProduct(string $mediaId, string $productId, Context $context): void
    {
        $productMediaId = Uuid::randomHex();

        $this->registry->getRepository('product')->upsert([
            [
                'id' => $productId,
                'media' => [
                    [
                        'id' => $productMediaId,
                        'mediaId' => $mediaId,
                    ],
                ],
                'coverId' => $productMediaId,
            ],
        ], $context);
    }
}
