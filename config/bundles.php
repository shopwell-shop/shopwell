<?php declare(strict_types=1);

use Composer\InstalledVersions;

$bundles = [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    Shopwell\Core\Profiling\Profiling::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true, 'test' => true],
    Shopwell\Core\Framework\Framework::class => ['all' => true],
    Shopwell\Core\System\System::class => ['all' => true],
    Shopwell\Core\Content\Content::class => ['all' => true],
    Shopwell\Core\Checkout\Checkout::class => ['all' => true],
    Shopwell\Core\DevOps\DevOps::class => ['all' => true],
    Shopwell\Core\Maintenance\Maintenance::class => ['all' => true],
    Shopwell\Administration\Administration::class => ['all' => true],
    Shopwell\Storefront\Storefront::class => ['all' => true],
    Shopwell\Elasticsearch\Elasticsearch::class => ['all' => true],
    Shopwell\Core\Service\Service::class => ['all' => true],
];

if (InstalledVersions::isInstalled('symfony/web-profiler-bundle')) {
    $bundles[Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class] = ['dev' => true, 'test' => true, 'phpstan_dev' => true];
}

$bundles[Symfony\AI\McpBundle\McpBundle::class] = ['all' => true];

if (InstalledVersions::isInstalled('swag/mcp-dev-tools')) {
    $bundles[Swag\McpDevTools\SwagMcpDevToolsBundle::class] = ['all' => true];
}

return $bundles;
