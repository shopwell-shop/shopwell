<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use \Shopwell\Core\Framework\Plugin\PluginException::pluginComposerRequire instead
 */
#[Package('framework')]
class PluginComposerRequireException extends ShopwellHttpException
{
    public function __construct(
        string $pluginName,
        string $pluginComposerName,
        string $output
    ) {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            \sprintf('Could not execute "composer require" for plugin "{{ pluginName }} ({{ pluginComposerName }}). Output:%s{{ output }}', \PHP_EOL),
            [
                'pluginName' => $pluginName,
                'pluginComposerName' => $pluginComposerName,
                'output' => $output,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_COMPOSER_REQUIRE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
