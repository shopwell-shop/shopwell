<?php declare(strict_types=1);

namespace Shopwell\Administration\Controller\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class AppByNameNotFoundException extends ShopwellHttpException
{
    public function __construct(string $appName)
    {
        parent::__construct(
            'The provided name {{ name }} is invalid and no app could be found.',
            ['name' => $appName]
        );
    }

    public function getErrorCode(): string
    {
        return 'ADMINISTRATION__APP_BY_NAME_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
