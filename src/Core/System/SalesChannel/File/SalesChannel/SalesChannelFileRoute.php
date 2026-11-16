<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\File\SalesChannel;

use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopwell\Core\System\SalesChannel\File\Extension\SalesChannelFileRouteExtension;
use Shopwell\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopwell\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class SalesChannelFileRoute
{
    public function __construct(
        private readonly SalesChannelFileLoader $loader,
        private readonly SalesChannelFileRequestPathResolver $requestPathResolver,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/sales-channel-file/{fileName}',
        name: 'store-api.sales-channel-file',
        requirements: ['fileName' => '.+'],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET],
    )]
    public function load(string $fileName, SalesChannelContext $context): Response
    {
        return $this->extensions->publish(
            name: SalesChannelFileRouteExtension::NAME,
            extension: new SalesChannelFileRouteExtension($fileName, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $fileName, SalesChannelContext $context): Response
    {
        $templatePath = $this->requestPathResolver->buildTemplatePath(SalesChannelFile::DEFAULT_FILE_FAMILY, $fileName);

        $file = $this->loader->load($templatePath, $context);
        if ($file === null) {
            throw SalesChannelException::salesChannelFileNotFound(SalesChannelFile::DEFAULT_FILE_FAMILY, $fileName);
        }

        return new Response($file->content, Response::HTTP_OK, ['content-type' => $file->contentType]);
    }
}
