<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginNotFoundException extends PluginException
{
    public function __construct(string $pluginName)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            'FRAMEWORK__PLUGIN_NOT_FOUND',
            'Plugin by name "{{ name }}" not found.',
            ['name' => $pluginName]
        );
    }
}
