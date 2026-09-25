<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Api;

use Shopwell\Core\Content\Media\File\DownloadResponseGenerator;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Media\MediaException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class MediaDownloadController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly DownloadResponseGenerator $downloadResponseGenerator
    ) {
    }

    #[Route(
        path: '/api/_action/media/{mediaId}/download/prepare',
        name: 'api.action.media.download.prepare',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['media:read']],
        methods: [Request::METHOD_GET]
    )]
    public function prepareMediaDownload(string $mediaId, Context $context): JsonResponse
    {
        $media = $this->getMedia($mediaId, $context);
        $response = $this->downloadResponseGenerator->getResponseByContext($media, $context);

        if ($response instanceof RedirectResponse) {
            return new JsonResponse([
                'type' => 'external',
                'url' => $response->getTargetUrl(),
            ]);
        }

        return new JsonResponse(['type' => 'blob']);
    }

    #[Route(
        path: '/api/_action/media/{mediaId}/download',
        name: 'api.action.media.download',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['media:read']],
        methods: [Request::METHOD_GET]
    )]
    public function downloadMediaFile(string $mediaId, Context $context): Response
    {
        $media = $this->getMedia($mediaId, $context);

        return $this->downloadResponseGenerator->getResponseByContext($media, $context);
    }

    private function getMedia(string $mediaId, Context $context): MediaEntity
    {
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $context)->getEntities()->first();

        if (!$media instanceof MediaEntity) {
            throw MediaException::mediaNotFound($mediaId);
        }

        return $media;
    }
}
