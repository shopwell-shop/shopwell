<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle\Update;

use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException;
use Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopwell\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Shopwell\Core\Framework\Store\Services\ExtensionDownloader;
use Shopwell\Core\Framework\Store\Struct\ExtensionStruct;

/**
 * @internal
 */
#[Package('framework')]
class AppUpdater extends AbstractAppUpdater
{
    /**
     * @param EntityRepository<AppCollection> $appRepo
     */
    public function __construct(
        private readonly AbstractExtensionDataProvider $extensionDataProvider,
        private readonly EntityRepository $appRepo,
        private readonly ExtensionDownloader $downloader,
        private readonly AbstractStoreAppLifecycleService $appLifecycle
    ) {
    }

    public function updateApps(Context $context): void
    {
        $extensions = $this->extensionDataProvider->getInstalledExtensions($context, true);
        $extensions = $extensions->filterByType(ExtensionStruct::EXTENSION_TYPE_APP);

        $outdatedApps = [];

        foreach ($extensions as $extension) {
            $id = $extension->getLocalId();
            if (!$id) {
                continue;
            }
            $localApp = $this->appRepo->search(new Criteria([$id]), $context)->getEntities()->first();
            if ($localApp === null) {
                continue;
            }

            $nextVersion = $extension->getLatestVersion();
            if (!$nextVersion) {
                continue;
            }

            if (version_compare($nextVersion, $localApp->getVersion()) > 0) {
                $outdatedApps[] = $extension;
            }
        }
        foreach ($outdatedApps as $app) {
            $this->downloader->download($app->getName(), $context);

            try {
                $this->appLifecycle->updateExtension($app->getName(), false, $context);
            } catch (ExtensionUpdateRequiresConsentAffirmationException) {
                // Ignore updates that require consent
            }
        }
    }

    protected function getDecorated(): AbstractAppUpdater
    {
        throw new DecorationPatternException(self::class);
    }
}
