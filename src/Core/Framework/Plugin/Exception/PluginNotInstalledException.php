<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginNotInstalledException extends PluginException
{
    public function __construct(string $pluginName)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'FRAMEWORK__PLUGIN_NOT_INSTALLED',
            'Plugin "{{ plugin }}" is not installed.',
            ['plugin' => $pluginName]
        );
    }
}
