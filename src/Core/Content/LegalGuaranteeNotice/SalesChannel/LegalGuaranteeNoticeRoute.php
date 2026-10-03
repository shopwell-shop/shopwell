<?php declare(strict_types=1);

namespace Shopwell\Core\Content\LegalGuaranteeNotice\SalesChannel;

use Shopwell\Core\Content\LegalGuaranteeNotice\Extension\LegalGuaranteeNoticeRouteExtension;
use Shopwell\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LegalGuaranteeNoticeRoute extends AbstractLegalGuaranteeNoticeRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LegalGuaranteeNoticeRenderer $renderer,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractLegalGuaranteeNoticeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/legal-guarantee-notice',
        name: 'store-api.legal-guarantee-notice',
        methods: [Request::METHOD_GET]
    )]
    public function load(SalesChannelContext $context): LegalGuaranteeNoticeRouteResponse
    {
        return $this->extensions->publish(
            name: LegalGuaranteeNoticeRouteExtension::NAME,
            extension: new LegalGuaranteeNoticeRouteExtension($context),
            function: $this->_load(...),
        );
    }

    private function _load(SalesChannelContext $context): LegalGuaranteeNoticeRouteResponse
    {
        if (!$this->systemConfigService->getBool('core.cart.showLegalGuaranteeNotice', $context->getSalesChannelId())) {
            return new LegalGuaranteeNoticeRouteResponse(null, null);
        }

        return new LegalGuaranteeNoticeRouteResponse(
            $this->renderer->renderForLanguage($context->getLanguageId()),
            $this->renderer->linkForLanguage($context->getLanguageId()),
        );
    }
}
