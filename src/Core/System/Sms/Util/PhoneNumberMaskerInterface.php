<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Util;

use Shopwell\Core\Framework\Log\Package;

/**
 * Renders the number of a delivery record in a form that can be shown and stored.
 *
 * It is a contract of its own so the display format of a masked number has one owner: the record of a
 * message and the log of a development gateway must not drift apart.
 */
#[Package('framework')]
interface PhoneNumberMaskerInterface
{
    /**
     * The masked number of a delivery record, e.g. +86138****0000.
     */
    public function mask(PhoneNumber $phone): string;
}
