<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Channel;

use Shopwell\Core\Framework\Log\Package;

/**
 * How the payer is handed over to the channel once a payment order is opened.
 */
#[Package('framework')]
enum PaymentFlow: string
{
    /**
     * The app redirects the payer to a url of the channel.
     */
    case REDIRECT = 'redirect';

    /**
     * The app renders a qr code the payer scans.
     */
    case QRCODE = 'qrcode';

    /**
     * The app opens the channel natively, the parameters are handed over to the app instead of a url.
     */
    case SDK = 'sdk';
}
