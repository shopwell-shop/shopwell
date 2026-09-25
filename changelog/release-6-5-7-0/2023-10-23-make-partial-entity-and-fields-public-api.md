---
title: Make PartialEntity and Criteria fields public API
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
issue: NEXT-31262
---
# Core
* Removed `internal` PHP docs from `\Shopwell\Core\Framework\DataAbstractionLayer\PartialEntity`, `\Shopwell\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent`, `\Shopwell\Core\System\SalesChannel\Entity\PartialSalesChannelEntityLoadedEvent`, `\Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria::addFields` and `\Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria::getFields`
