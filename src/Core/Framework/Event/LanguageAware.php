<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
#[IsFlowEventAware]
interface LanguageAware
{
    public const LANGUAGE_ID = 'languageId';

    public function getLanguageId(): ?string;
}
