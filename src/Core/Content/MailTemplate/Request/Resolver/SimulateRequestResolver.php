<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Request\Resolver;

use Shopwell\Core\Content\MailTemplate\MailTemplateException;
use Shopwell\Core\Content\MailTemplate\Request\SimulateRequest;
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
readonly class SimulateRequestResolver extends AbstractMailTemplateRequestResolver implements ValueResolverInterface
{
    public function __construct(
        private SalesChannelProvider $salesChannelProvider,
    ) {
    }

    /**
     * @return \Generator<SimulateRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== SimulateRequest::class) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return;
        }

        yield $this->make(new RequestDataBag($request->request->all()), $context);
    }

    private function make(RequestDataBag $request, Context $context): SimulateRequest
    {
        $templateParts = $this->normalizeArrayParameter('templateParts', $request->get('templateParts'));

        $eventName = $this->normalizeStringParameter('eventName', $request->get('eventName'));
        if ($eventName === null) {
            throw MailTemplateException::invalidRequestParameterType('eventName', 'string', get_debug_type($eventName));
        }

        $salesChannel = null;
        $salesChannelId = $this->normalizeStringParameter('salesChannelId', $request->get('salesChannelId'));
        if ($salesChannelId !== null) {
            $salesChannel = $this->salesChannelProvider->getData($salesChannelId, $context);
            if ($salesChannel === null) {
                throw MailTemplateException::invalidSalesChannelId($salesChannelId);
            }
        }

        $strictRendering = $this->normalizeBoolParameter('strictRendering', $request->get('strictRendering', true));

        return new SimulateRequest($templateParts, $eventName, $salesChannel, $strictRendering);
    }
}
