<?php

use Scripts\Boot\ScriptKernel;
use Shopwell\Core\Framework\Adapter\Database\MySQLFactory;
use Shopwell\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopwell\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

$classLoader = require __DIR__ . '/../../../vendor/autoload.php';

require __DIR__ . '/ScriptKernel.php';

$projectRoot = dirname(__DIR__) . '/../../';

if (class_exists(Dotenv::class) && (file_exists($projectRoot . '/.env.local.php') || file_exists($projectRoot . '/.env') || file_exists($projectRoot . '/.env.dist'))) {
    (new Dotenv())->usePutenv()->bootEnv($projectRoot . '/.env');
}

$env = $env ?? 'dev';

/** @var KernelInterface $kernel */
KernelFactory::$kernelClass = ScriptKernel::class;

$kernel = KernelFactory::create(
    environment: $env,
    debug: true,
    classLoader: $classLoader,
    pluginLoader: new DbalKernelPluginLoader(
        classLoader: $classLoader,
        pluginDir: $projectRoot . '/custom/plugins',
        connection: MySQLFactory::create([])
    )
);

$kernel->boot();

return $kernel;
