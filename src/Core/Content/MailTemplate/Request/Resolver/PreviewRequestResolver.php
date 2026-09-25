<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Request\Resolver;

use Shopwell\Core\Content\MailTemplate\MailTemplateException;
use Shopwell\Core\Content\MailTemplate\Request\PreviewRequest;
use Shopwell\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class PreviewRequestResolver extends AbstractMailTemplateRequestResolver implements ValueResolverInterface
{
    public function __construct(
        private MailTemplateService $mailTemplateService,
        private SalesChannelProvider $salesChannelProvider,
    ) {
    }

    /**
     * @return \Generator<PreviewRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== PreviewRequest::class) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return;
        }

        yield $this->make(new RequestDataBag($request->request->all()), $context);
    }

    private function make(RequestDataBag $request, Context $context): PreviewRequest
    {
        $templateId = $request->getString('mailTemplateId');
        $mailTemplate = $this->mailTemplateService->loadTemplate($templateId, $context);

        $entities = $this->normalizeArrayParameter('entities', $request->get('entities', []));
        $entities = $this->filterAvailableEntities($entities, $mailTemplate);

        $templateData = $this->normalizeArrayParameter('templateData', $request->get('templateData', []));

        $salesChannel = null;
        $salesChannelId = $this->normalizeStringParameter('salesChannelId', $request->get('salesChannelId'));
        if ($salesChannelId !== null) {
            $salesChannel = $this->salesChannelProvider->getData($salesChannelId, $context);
            if ($salesChannel === null) {
                throw MailTemplateException::invalidSalesChannelId($salesChannelId);
            }
        }

        $includeHeaderFooter = $this->normalizeBoolParameter('includeHeaderFooter', $request->get('includeHeaderFooter', false));
        $strictRendering = $this->normalizeBoolParameter('strictRendering', $request->get('strictRendering', false));

        return new PreviewRequest(
            $mailTemplate,
            $salesChannel,
            $entities,
            $templateData,
            $includeHeaderFooter,
            $strictRendering,
        );
    }
}
