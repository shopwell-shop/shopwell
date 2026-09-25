<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MailTemplateEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class MailTemplateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'mail_template_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateEntity::class;
    }
}
