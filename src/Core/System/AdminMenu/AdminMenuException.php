<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@framework')]
class AdminMenuException extends HttpException
{
    public const INVALID_MENU_ITEM_ID = 'ADMIN_MENU__INVALID_MENU_ITEM_ID';

    public static function invalidMenuItemId(mixed $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_MENU_ITEM_ID,
            'The value {{ id }} is not a valid menu item id.',
            ['id' => $id]
        );
    }
}
