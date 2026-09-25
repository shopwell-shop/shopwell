---
title: Change log_level of template rendering errors for ProductExport and MailService
issue: NEXT-26878
---
# Core
* Added `Shopwell\Core\Content\ProductExport\ProductExportException`
* Changed `Shopwell\Core\Content\ProductExport\Service\ProductExportRenderer` to use the domain exceptions of `ProductExportException`
* Changed log_level of exceptions which are caused by incorrect templates in `src/Core/Framework/Resources/config/packages/framework.yaml`
* Changed log_level of exceptions which are caused by incorrect templates in `Shopwell\Core\Content\Mail\Service\MailService`
* Changed log_level of exceptions which are caused by incorrect templates in `Shopwell\Core\Content\ProductExport\Service\ProductExportGenerator`
* Deprecated `Shopwell\Core\Content\ProductExport\Exception\RenderFooterException` will be removed with v6.6.0
* Deprecated `Shopwell\Core\Content\ProductExport\Exception\RenderHeaderException` will be removed with v6.6.0
* Deprecated `Shopwell\Core\Content\ProductExport\Exception\ProductExportException` will be removed with v6.6.0
