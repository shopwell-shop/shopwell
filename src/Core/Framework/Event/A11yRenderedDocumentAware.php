<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
#[IsFlowEventAware]
interface A11yRenderedDocumentAware
{
    public const A11Y_DOCUMENTS = 'a11yDocuments';

    public const A11Y_DOCUMENT_IDS = 'a11yDocumentIds';

    /**
     * @return array<string>
     */
    public function getA11yDocumentIds(): array;
}
