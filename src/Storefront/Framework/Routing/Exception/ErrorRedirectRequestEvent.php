<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Routing\Exception;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
class ErrorRedirectRequestEvent implements ShopwellEvent
{
    public function __construct(
        private readonly Request $request,
        private readonly \Throwable $exception,
        private readonly Context $context,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
