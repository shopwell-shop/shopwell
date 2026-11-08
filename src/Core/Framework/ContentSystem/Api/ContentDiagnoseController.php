<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Api;

use Shopwell\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopwell\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopwell\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * No SalesChannelContext is built; diagnosis needs only the admin Context, passed straight through.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ContentDiagnoseController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DraftLayoutDecoder $decoder,
        private readonly RootSourceRegistry $rootSourceRegistry,
        private readonly LayoutDiagnostics $diagnostics,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/diagnose', name: 'api.action.content_system.layout.diagnose', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function diagnose(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentDiagnoseRequest $payload,
        Context $context,
    ): Response {
        [$tree, $decodeViolations] = $this->decoder->decodeLintable($payload->layout);

        $rootSource = $payload->rootSource === '' ? null : $payload->rootSource;
        $rootContext = $this->rootSourceRegistry->resolveGated($rootSource, $context);

        // resolveGated() has already rejected an unknown non-empty source. When no source is bound,
        // diagnostics still report inline tokens that require one; whole-field mappings remain unjudged.
        $analysis = $this->diagnostics->analyze($tree, $rootContext, $rootSource);

        $report = new DiagnosticsReport([...$decodeViolations, ...$analysis->report->violations]);

        return new JsonResponse(DiagnoseResponse::fromReport($analysis->resolutions, $report));
    }
}
