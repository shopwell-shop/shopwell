<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Document\Twig;

use Shopwell\Core\Checkout\Document\DocumentGenerator\Counter;
use Shopwell\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopwell\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    description: 'Template rendering is internal to DocumentV2. Templates are resolved by convention from the document type technical name.',
)]
class DocumentTemplateRenderer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly TemplateFinder $templateFinder,
        private readonly TwigEnvironment $twig,
        private readonly AbstractTranslator $translator,
        private readonly AbstractSalesChannelContextFactory $contextFactory,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(
        string $view,
        array $parameters = [],
        ?Context $context = null,
        ?string $salesChannelId = null,
        ?string $languageId = null,
        ?string $locale = null
    ): string {
        $salesChannelContext = null;

        // If parameters for specific language setting provided, inject to translator
        if ($context !== null && $salesChannelId !== null && $languageId !== null && $locale !== null) {
            $this->translator->injectSettings(
                $salesChannelId,
                $languageId,
                $locale,
                $context
            );
            $salesChannelContext = $this->contextFactory->create(
                Uuid::randomHex(),
                $salesChannelId,
                [SalesChannelContextService::LANGUAGE_ID => $languageId]
            );

            $parameters['context'] = $salesChannelContext;
        }

        $documentTemplateRendererParameterEvent = new DocumentTemplateRendererParameterEvent($parameters);
        $this->eventDispatcher->dispatch($documentTemplateRendererParameterEvent);
        $parameters['extensions'] = $documentTemplateRendererParameterEvent->getExtensions();

        $parameters['counter'] = new Counter();

        $view = $this->resolveView($view);

        $rendered = $this->twig->renderWithTimezoneOverride(
            $view,
            $parameters,
            $salesChannelContext?->getSalesChannel()->getBusinessTimeZone(),
        );

        // If injected translator reject it
        if ($context !== null && $salesChannelId !== null && $languageId !== null && $locale !== null) {
            $this->translator->resetInjection();
        }

        return $rendered;
    }

    /**
     * @throws LoaderError
     */
    private function resolveView(string $view): string
    {
        $this->templateFinder->reset();

        return $this->templateFinder->find($view);
    }
}
