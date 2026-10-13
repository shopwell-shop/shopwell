<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SmsTemplateEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SmsTemplateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sms_template_collection';
    }

    protected function getExpectedClass(): string
    {
        return SmsTemplateEntity::class;
    }
}
