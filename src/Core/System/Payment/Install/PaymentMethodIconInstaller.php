<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Install;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * Uploads the picture of each payment method the gateway brings.
 *
 * The icons are part of the product, not of the shop: they ship with this domain and are uploaded
 * into the media of an installation when it is installed, the same way the administration uploads a
 * picture a merchant picks. Nothing writes a file to a path of its own - the media service owns where
 * a file ends up, which is what makes the icon a first class medium: it shows up in the media module,
 * a merchant can replace it, and it is served by the storage adapter the shop is configured with.
 *
 * A picture is only uploaded for a method that has none, so an icon a merchant replaced is never
 * overwritten by a later run.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentMethodIconInstaller
{
    /**
     * The icon of each method the gateway seeds, by the technical name of the method. The files are
     * `Resources/payment-icons/<file name>`.
     */
    final public const ICONS = [
        'alipay' => 'payment-alipay.svg',
        'wechat' => 'payment-wechat.svg',
        'paypal' => 'payment-paypal.svg',
        'stripe' => 'payment-stripe.svg',
    ];

    /**
     * The default media folder the icons are filed in. It is the folder of the payment methods, which
     * the installation creates itself, so the icons appear beside the pictures of the merchant.
     */
    final public const MEDIA_FOLDER = 'payment_method';

    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SystemInstallCompletedEvent $event): void
    {
        $this->install($event->getContext());
    }

    /**
     * Uploads the missing icons and points the methods at them.
     *
     * An icon must never break an installation: a shop whose storage rejects the upload keeps its
     * methods, it keeps them without a picture.
     */
    public function install(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('technicalName', array_keys(self::ICONS)));

        $methods = $this->paymentMethodRepository->search($criteria, $context);

        foreach ($methods->getEntities() as $method) {
            \assert($method instanceof PaymentMethodEntity);

            if ($method->getMediaId() !== null) {
                continue;
            }

            $fileName = self::ICONS[$method->getTechnicalName()] ?? null;

            if ($fileName === null) {
                continue;
            }

            try {
                $mediaId = $this->mediaIdOfIcon($fileName, $context);
            } catch (\Throwable $exception) {
                // a storage that refuses the upload must not take the installation down with it, but
                // a method that ends up without a picture has to be traceable
                $this->logger->warning('The icon of the payment method {method} could not be uploaded', [
                    'method' => $method->getTechnicalName(),
                    'exception' => $exception,
                ]);

                continue;
            }

            if ($mediaId === null) {
                continue;
            }

            $this->paymentMethodRepository->update([
                ['id' => $method->getId(), 'mediaId' => $mediaId],
            ], $context);
        }
    }

    /**
     * The medium holding the icon, uploaded when the shop does not have it yet.
     *
     * An installation that has the icon already reuses its medium by the name of the file, so a second
     * run neither uploads the file twice nor leaves two media of the same picture behind. `null` when
     * the icon did not come along with the deployment - the method then stays without a picture
     * instead of failing the installation.
     */
    private function mediaIdOfIcon(string $fileName, Context $context): ?string
    {
        $name = substr($fileName, 0, -4);

        $existing = $this->mediaRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('fileName', $name)),
            $context
        )->firstId();

        if (\is_string($existing) && $existing !== '') {
            return $existing;
        }

        $file = __DIR__ . '/../Resources/payment-icons/' . $fileName;

        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        // the media service takes the bytes and decides on the path, the thumbnails and the storage
        return $this->mediaService->saveFile(
            $contents,
            'svg',
            'image/svg+xml',
            $name,
            $context,
            self::MEDIA_FOLDER,
            null,
            false
        );
    }
}
