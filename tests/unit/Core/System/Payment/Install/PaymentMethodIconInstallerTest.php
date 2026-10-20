<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Install;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Install\PaymentMethodIconInstaller;

/**
 * Guards how the icons of the seeded methods reach the media of a shop.
 *
 * The point of the class is that it uploads through the media service instead of writing a file to a
 * path of its own - a test that only checked that a method ends up with a media id would pass for the
 * wrong implementation as well, so every case below asserts what the service was asked to do.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentMethodIconInstaller::class)]
class PaymentMethodIconInstallerTest extends TestCase
{
    public function testUploadsTheIconOfAMethodThatHasNone(): void
    {
        $method = $this->method('alipay');

        $mediaService = $this->createMock(MediaService::class);

        $methods = $this->createMock(EntityRepository::class);
        $methods->method('search')->willReturn($this->searchResult($method));

        // the icon is uploaded, not written somewhere: the service owns the path, the folder and the
        // storage, and it is told the folder of the payment methods so the icon shows up beside the
        // pictures the merchant uploaded
        $mediaService->expects($this->once())
            ->method('saveFile')
            ->with(
                static::stringContains('<svg'),
                'svg',
                'image/svg+xml',
                'payment-alipay',
                static::isInstanceOf(Context::class),
                'payment_method',
                null,
                // a public medium: the storefront serves the icon to every visitor of the checkout
                false
            )
            ->willReturn('0190f0f0f0f0f0f0f0f0f0f0f0f0f0f0');

        $methods->expects($this->once())
            ->method('update')
            ->with([['id' => $method->getId(), 'mediaId' => '0190f0f0f0f0f0f0f0f0f0f0f0f0f0f0']]);

        $this->installer($mediaService, $methods)->install(Context::createDefaultContext());
    }

    public function testKeepsTheIconAMethodAlreadyCarries(): void
    {
        $method = $this->method('paypal', '0190f0f0f0f0f0f0f0f0f0f0f0f0f0f1');

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('saveFile');

        $methods = $this->createMock(EntityRepository::class);
        $methods->method('search')->willReturn($this->searchResult($method));
        $methods->expects($this->never())->method('update');

        // an icon a merchant picked is theirs - the installer does not even look for another one
        $media = $this->createMock(EntityRepository::class);
        $media->expects($this->never())->method('searchIds');

        $this->installer($mediaService, $methods, $media)->install(Context::createDefaultContext());
    }

    public function testReusesAnIconTheShopAlreadyHas(): void
    {
        $method = $this->method('stripe');

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('saveFile');

        $methods = $this->createMock(EntityRepository::class);
        $methods->method('search')->willReturn($this->searchResult($method));
        $methods->expects($this->once())
            ->method('update')
            ->with([['id' => $method->getId(), 'mediaId' => '0190f0f0f0f0f0f0f0f0f0f0f0f0f0f2']]);

        $media = static::createStub(EntityRepository::class);
        $media->method('searchIds')->willReturn($this->idSearchResult('0190f0f0f0f0f0f0f0f0f0f0f0f0f0f2'));

        // the file is not uploaded twice: the medium of the same picture already sits in the folder
        $this->installer($mediaService, $methods, $media)->install(Context::createDefaultContext());
    }

    public function testLeavesAMethodWithoutAPictureWhenTheStorageRefusesTheUpload(): void
    {
        $method = $this->method('wechat');

        $mediaService = static::createStub(MediaService::class);
        $mediaService->method('saveFile')->willThrowException(new \RuntimeException('storage is down'));

        $methods = $this->createMock(EntityRepository::class);
        $methods->method('search')->willReturn($this->searchResult($method));
        $methods->expects($this->never())->method('update');

        $media = static::createStub(EntityRepository::class);
        $media->method('searchIds')->willReturn($this->idSearchResult());

        // an icon is not worth an installation: the method keeps no picture, but the install goes on
        // and the reason is written to the log
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('could not be uploaded'));

        $this->installer($mediaService, $methods, $media, $logger)->install(Context::createDefaultContext());
    }

    /**
     * @param EntityRepository<PaymentMethodCollection> $methods
     * @param EntityRepository<MediaCollection>|null $media
     */
    private function installer(
        MediaService $mediaService,
        EntityRepository $methods,
        ?EntityRepository $media = null,
        ?LoggerInterface $logger = null
    ): PaymentMethodIconInstaller {
        return new PaymentMethodIconInstaller(
            $mediaService,
            $methods,
            $media ?? static::createStub(EntityRepository::class),
            $logger ?? static::createStub(LoggerInterface::class)
        );
    }

    private function method(string $technicalName, ?string $mediaId = null): PaymentMethodEntity
    {
        $method = new PaymentMethodEntity();
        $method->setId('0190f0f0f0f0f0f0f0f0f0f0f0f0f0f' . substr($technicalName, 0, 2));
        $method->setTechnicalName($technicalName);
        $method->setMediaId($mediaId);
        $method->setHandlerIdentifier('handler');

        return $method;
    }

    /**
     * @return EntitySearchResult<PaymentMethodCollection>
     */
    private function searchResult(PaymentMethodEntity ...$methods): EntitySearchResult
    {
        $collection = new PaymentMethodCollection($methods);

        return new EntitySearchResult(
            'payment_method',
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    private function idSearchResult(?string $mediaId = null): IdSearchResult
    {
        if ($mediaId === null) {
            return new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());
        }

        return new IdSearchResult(1, [
            $mediaId => ['primaryKey' => $mediaId, 'data' => ['fileName' => 'payment-stripe']],
        ], new Criteria(), Context::createDefaultContext());
    }
}
