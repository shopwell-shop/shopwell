<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Manifest;

use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Source\SourceResolver;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ManifestFactory
{
    public function __construct(private readonly SourceResolver $sourceResolver)
    {
    }

    public function createFromXmlFile(string $file): Manifest
    {
        return Manifest::createFromXmlFile($file);
    }

    public function createFromApp(AppEntity $app): Manifest
    {
        $filesystem = $this->sourceResolver->filesystemForApp($app);

        return $this->createFromXmlFile($filesystem->path('manifest.xml'));
    }
}
