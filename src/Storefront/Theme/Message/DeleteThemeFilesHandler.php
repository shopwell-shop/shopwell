<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\Message;

use League\Flysystem\FilesystemOperator;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Theme\AbstractThemePathBuilder;
use Shopwell\Storefront\Theme\ScheduledTask\DeleteThemeFilesTask;
use Shopwell\Storefront\Theme\ScheduledTask\DeleteThemeFilesTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed. Unused theme files are now deleted with a scheduled task.
 * @see DeleteThemeFilesTask
 * @see DeleteThemeFilesTaskHandler
 */
#[Package('discovery')]
#[AsMessageHandler]
final readonly class DeleteThemeFilesHandler
{
    public function __construct(
        private FilesystemOperator $filesystem,
        private AbstractThemePathBuilder $pathBuilder,
    ) {
    }

    public function __invoke(DeleteThemeFilesMessage $message): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $currentPath = $this->pathBuilder->assemblePath($message->getSalesChannelId(), $message->getThemeId());
        if ($currentPath === $message->getThemePath()) {
            return;
        }

        $this->filesystem->deleteDirectory('theme' . \DIRECTORY_SEPARATOR . $message->getThemePath());
    }
}
