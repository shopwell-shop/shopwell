<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\File\Rendering;

use Shopwell\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopwell\Core\System\SalesChannel\File\Rendering\Extension\SalesChannelFileRenderParametersExtension;
use Shopwell\Core\System\SalesChannel\File\SalesChannelFileTemplateResolver;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Twig\Environment;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileRenderer
{
    private const USER_PROVIDED_CONTENT_OVERRIDE_KEY = 'user_provided_content';

    private const USER_PROVIDED_CONTENT_TEMPLATE = '@SalesChannelFileUserProvidedContent/%s';

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly SalesChannelFileTemplateResolver $templateResolver,
        private readonly SalesChannelFileTemplateOverrideLoader $templateOverrideLoader,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly EntityRepository $salesChannelRepository,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * @param array<string, mixed> $templateOverrides
     */
    public function render(SalesChannelFile $file, SalesChannelContext $context, array $templateOverrides = []): string
    {
        $templates = $this->templateResolver->resolveTemplateChain($file, $context->getSalesChannelId());
        $overrideTemplates = $this->buildOverrideTemplates($templates, $templateOverrides);
        $overrideTemplates += $this->buildCaseVariantAliases($file, $templates);
        $parameters = $this->buildParameters($file, $context);
        $templateName = $this->getRenderTemplateName($file, $templates);

        $userProvidedContent = $this->getUserProvidedContent($templateOverrides);
        if ($userProvidedContent !== null) {
            $parentTemplateName = $templateName;
            $templateName = \sprintf(self::USER_PROVIDED_CONTENT_TEMPLATE, $file->templatePath);
            $overrideTemplates[$templateName] = $this->buildUserProvidedContentTemplate($userProvidedContent, $parentTemplateName);
        }

        $content = $this->templateOverrideLoader->withTemplateOverrides(
            $overrideTemplates,
            fn (): string => $this->twig->render($templateName, $parameters)
        );

        return $this->seoUrlPlaceholderHandler->replace($content, '', $context);
    }

    /**
     * @param array<string, string> $templates
     */
    private function getRenderTemplateName(SalesChannelFile $file, array $templates): string
    {
        return array_first($templates) ?? $file->baseTemplateName;
    }

    /**
     * @param array<string, string> $templates
     * @param array<string, mixed> $templateOverrides
     *
     * @return array<string, string>
     */
    private function buildOverrideTemplates(array $templates, array $templateOverrides): array
    {
        $overrideTemplates = [];

        foreach ($templates as $twigNamespace => $templateName) {
            $override = $templateOverrides[$twigNamespace] ?? null;

            if (!\is_string($override)) {
                continue;
            }

            $overrideTemplates[$templateName] = $override;
        }

        return $overrideTemplates;
    }

    /**
     * @param array<string, string> $templates
     *
     * @return array<string, string>
     */
    private function buildCaseVariantAliases(SalesChannelFile $file, array $templates): array
    {
        $aliases = [];

        foreach ($templates as $twigNamespace => $templateName) {
            foreach ($file->templatePaths as $templatePath) {
                $alias = '@' . $twigNamespace . '/' . $templatePath;

                if ($alias !== $templateName) {
                    $encodedTemplateName = json_encode($templateName, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
                    \assert(\is_string($encodedTemplateName));
                    $aliases[$alias] = \sprintf('{%% extends %s %%}', $encodedTemplateName);
                }
            }
        }

        return $aliases;
    }

    /**
     * @param array<string, mixed> $templateOverrides
     */
    private function getUserProvidedContent(array $templateOverrides): ?string
    {
        $userProvidedContent = $templateOverrides[self::USER_PROVIDED_CONTENT_OVERRIDE_KEY] ?? null;

        if (!\is_string($userProvidedContent) || trim($userProvidedContent) === '') {
            return null;
        }

        return $userProvidedContent;
    }

    private function buildUserProvidedContentTemplate(string $userProvidedContent, string $parentTemplateName): string
    {
        $encodedContent = json_encode($userProvidedContent, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        \assert(\is_string($encodedContent));

        // The generated override namespace is not part of the normal namespace hierarchy.
        // Resolve the render entry first, then extend that concrete template.
        return \sprintf(
            "{%% extends '%s' %%}\n\n{%% block user_provided_content %%}{{ %s|raw }}{%% endblock %%}",
            $parentTemplateName,
            $encodedContent
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParameters(SalesChannelFile $file, SalesChannelContext $context): array
    {
        $salesChannel = $this->loadSalesChannel($context);

        return $this->extensions->publish(
            name: SalesChannelFileRenderParametersExtension::NAME,
            extension: new SalesChannelFileRenderParametersExtension($file, $context, $salesChannel),
            function: $this->buildDefaultParameters(...),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDefaultParameters(SalesChannelFile $file, SalesChannelContext $context, SalesChannelEntity $salesChannel): array
    {
        return [
            'context' => $context,
            'salesChannel' => $salesChannel,
            'salesChannelFile' => $file,
        ];
    }

    private function loadSalesChannel(SalesChannelContext $context): SalesChannelEntity
    {
        $criteria = new Criteria([$context->getSalesChannelId()]);
        $criteria->setTitle('sales-channel-file-renderer::sales-channel');
        $criteria->addAssociation('languages.translationCode');
        $criteria->addAssociation('currencies');
        $criteria->addAssociation('domains');
        $criteria->getAssociation('languages')->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->getAssociation('currencies')->addSorting(new FieldSorting('isoCode', FieldSorting::ASCENDING));
        $criteria->getAssociation('domains')->addSorting(new FieldSorting('url', FieldSorting::ASCENDING));

        $salesChannel = $this->salesChannelRepository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$salesChannel instanceof SalesChannelEntity) {
            return $context->getSalesChannel();
        }

        return $salesChannel;
    }
}
