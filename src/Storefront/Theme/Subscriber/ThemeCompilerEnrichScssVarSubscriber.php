<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\Subscriber;

use Doctrine\DBAL\Exception as DBALException;
use Shopwell\Core\DevOps\Environment\EnvironmentHelper;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\IOStreamHelper;
use Shopwell\Core\System\SystemConfig\DTO\SystemConfigElement;
use Shopwell\Core\System\SystemConfig\Service\ConfigurationService;
use Shopwell\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeCompilerEnrichScssVarSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly StorefrontPluginRegistry $storefrontPluginRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ThemeCompilerEnrichScssVariablesEvent::class => 'enrichExtensionVars',
        ];
    }

    /**
     * @internal
     */
    public function enrichExtensionVars(ThemeCompilerEnrichScssVariablesEvent $event): void
    {
        $allConfigs = [];

        if ($this->storefrontPluginRegistry->getConfigurations()->count() === 0) {
            return;
        }

        try {
            foreach ($this->storefrontPluginRegistry->getConfigurations() as $configuration) {
                $allConfigs = array_merge(
                    $allConfigs,
                    $this->configurationService->getResolvedSystemConfigDefinition(
                        $configuration->getTechnicalName() . '.config',
                        $event->getContext(),
                        $event->getSalesChannelId()
                    )
                );
            }
        } catch (DBALException $e) {
            if (!EnvironmentHelper::getVariable('TESTS_RUNNING')) {
                IOStreamHelper::writeError('Warning: Failed to load plugin css configuration. Ignoring plugin css customizations.', $e);
            }
        }

        foreach ($allConfigs as $tab) {
            foreach ($tab->cards as $card) {
                foreach ($card->elements as $element) {
                    $cssValue = $this->getCssValue($element);

                    if ($cssValue === null) {
                        continue;
                    }

                    $event->addVariable($element->config['css'], $cssValue);
                }
            }
        }
    }

    private function getCssValue(SystemConfigElement $element): ?string
    {
        if (!isset($element->config['css'])) {
            return null;
        }

        if ($element->value === null) {
            return '';
        }

        return \is_string($element->value) ? $element->value : null;
    }
}
