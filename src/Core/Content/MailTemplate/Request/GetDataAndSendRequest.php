<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Request;

use Shopwell\Core\Content\Mail\Payload\MailPayload;
use Shopwell\Core\Content\MailTemplate\MailTemplateEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
readonly class GetDataAndSendRequest
{
    /**
     * @param array<string,string> $entityMapping Associative array where the key is the variable name used in the template
     *                                            and the value is the corresponding entity ID.
     * @param array<string,mixed> $templateData Associative array where the key is the variable name used in the template
     *                                          and the value is the corresponding data to be used during rendering.
     */
    public function __construct(
        public MailTemplateEntity $mailTemplate,
        public array $entityMapping = [],
        public array $templateData = [],
        public MailPayload $mailPayload = new MailPayload(),
    ) {
    }
}
