---
title: Improved extensibility of CMS resolvers
issue: NEXT-7505
author: Krzykawski
author_email: m.krzykawski@shopwell.com
author_github: @MartinKrzykawski
---
# Core
* Changed `Shopwell\Core\Content\Cms\DataResolver\CmsSlotsDataResolver` to dispatch the following event-based extensions: `CmsSlotsDataResolveExtension`, `CmsSlotsDataCollectExtension`, and `CmsSlotsDataEnrichExtension`.
* Added `Shopwell\Core\Content\Cms\Extension\CmsSlotsDataResolveExtension` to allow interception of the CMS slots data resolution process.
* Added `Shopwell\Core\Content\Cms\Extension\CmsSlotsDataCollectExtension` to allow interception of the CMS slots criteria collection process.
* Added `Shopwell\Core\Content\Cms\Extension\CmsSlotsDataEnrichExtension` to allow interception of the CMS slots data enrichment process.
