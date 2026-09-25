---
title: Rework Document Generator
issue: NEXT-17708
---
# Core
* Added new composer's dependencies `tecnickcom/tcpdf:6.4.4` and `setasign/fpdi:2.3.6` to merge generated documents
* Added a new service `Shopwell\Core\Checkout\Document\Service\DocumentGenerator`
* Added new renderer classes in
    * `\Shopwell\Core\Checkout\Document\Renderer\AbstractDocumentRenderer`
    * `\Shopwell\Core\Checkout\Document\Renderer\InvoiceRenderer`
    * `\Shopwell\Core\Checkout\Document\Renderer\StornoRenderer`
    * `\Shopwell\Core\Checkout\Document\Renderer\CreditNoteRenderer`
    * `\Shopwell\Core\Checkout\Document\Renderer\DeliveryNoteRenderer`
* Deprecated `Shopwell\Core\Checkout\Document\DocumentService`, use `Shopwell\Core\Checkout\Document\Service\DocumentGenerator` instead
* Deprecated `\Shopwell\Core\Checkout\Document\DocumentGeneratorController::createDocument`, use `createDocuments` instead
* Deprecated`\Shopwell\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorInterface` and its implementations use the `AbstractDocumentRenderer` instead
* Deprecated`\Shopwell\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorRegistry` use the `DocumentRendererRegistry` instead
* Added a new service `\Shopwell\Core\Checkout\Document\Service\DocumentConfigLoader` to load document's config
* Added a new service `\Shopwell\Core\Checkout\Document\Service\DocumentMerger` to combine generated document into one pdf file
* Added a new Struct class for Order Document in `Shopwell\Core\Checkout\Document\Struct\DocumentGenerateOperation`
* Added a new route `Shopwell\Core\Checkout\Document\SalesChannel\DocumentRoute` to provide store-api route for downloading generated document's content
* Deprecated`\Shopwell\Core\Checkout\Document\Event\DocumentOrderCriteriaEvent`
* Added these new events after fetching orders in renderer classes:
    * `Shopwell\Core\Checkout\Document\Event\DocumentOrderEvent`   
    * `Shopwell\Core\Checkout\Document\Event\InvoiceOrdersEvent`   
    * `Shopwell\Core\Checkout\Document\Event\CreditNoteOrdersEvent`   
    * `Shopwell\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent`   
    * `Shopwell\Core\Checkout\Document\Event\StornoOrdersEvent`
* Added a new service `Shopwell\Core\Checkout\Document\Service\ReferenceInvoiceLoader` to load the reference invoice of a given order
* Added new method `downloadBulkDocuments` in `Core/Checkout/Document/Controller/DocumentController.php` to allow downloading multiple documents
___
# API
* Added new `POST` endpoint `/api/_action/order/document/{documentType}/create` in `\Shopwell\Core\Checkout\Document\DocumentGeneratorController`. This endpoint is used for generating order documents in bulk
* Deprecated `POST` endpoint `/api/_action/order/{orderId}/document/{documentTypeName}` in `\Shopwell\Core\Checkout\Document\DocumentGeneratorController`. Use `/api/_action/order/document/{documentType}/create` instead
* Added new `POST` endpoint `/api/_action/order/document/download` in `\Shopwell\Core\Checkout\Document\DocumentGeneratorController`. This endpoint is used for merging order documents and downloading them in one pdf file
* Added new store-api route `/store-api/document/download/{documentId}/{deepLinkCode}` to download generated document
___
# Administration
* Changed method `createDocument` in `src/core/service/api/document.api.service.js` to use the new create documents endpoint
___
# Storefront
* Deprecated `Shopwell\Storefront\Page\Account\Document\DocumentPageLoader` and `Shopwell\Storefront\Page\Account\Document\DocumentPage` and `Shopwell\Storefront\Page\Account\Document\DocumentPageLoadedEvent` due to unused
* Changed `Shopwell\Storefront\Controller\DocumentController` to use `Shopwell\Core\Checkout\Document\SalesChannel\DocumentRoute` to load document blob file
___
# Upgrade Information

## Deprecate old document generation endpoint, introduce new bulk order's documents generator endpoint

* Endpoint and payload:
```
POST /api/_action/order/document/{documentType}/create
[
    {
        "fileType": "pdf",
        "orderId": "012cd563cf8e4f0384eed93b5201cc98",
        "static": true,
        "config": {
            "documentComment": "Some comment",
            "documentNumber": "1002",
            "documentDate": "2021-12-13T00:00:00.000Z"
        }
    }, 
    {        
        "fileType": "pdf",
        "orderId": "012cd563cf8e4f0384eed93b5201cc99",
        "static": true,
        "config": {
            "documentComment": "Another comment",
            "documentNumber": "1003",
            "documentDate": "2021-12-13T00:00:00.000Z"
        }
    }
]
```

## New bulk order's documents downloading endpoint

This endpoint is used for merging multiple documents at one pdf file and download the merged pdf file

* Endpoint and payload:
```
POST /api/_action/order/document/download
{
    "documentIds": [
        "012cd563cf8e4f0384eed93b5201cc98",
        "075fb241b769444bb72431f797fd5776",
    ],
}
```

## New Store-Api route to download document

* Use `/store-api/document/download/{documentId}/{deepLinkCode}` route to download generated document of the given id

## Deprecation of DocumentPageLoader

* The `\Shopwell\Storefront\Page\Account\Document\DocumentPageLoader` and its page, page loaded event was deprecated and will be removed in v6.5.0.0 due to unused, please use the newly added `\Shopwell\Core\Checkout\Document\SalesChannel\DocumentRoute` instead to download generated document. 

## Deprecation of Document generators, introduce Document renderer services

* All the document generators in `Shopwell\Core\Checkout\Document\DocumentGenerator` (tagged as `document.generator`) will be deprecated and will be removed in v6.5.0.0, please adjust your changes if you're touching these services, you might want to decorate `Shopwell\Core\Checkout\Document\Renderer\*` (tagged as `document.renderer`) instead
* If you need to manipulate the fetched orders in renderer services, you can listen to according events which extends from `Shopwell\Core\Checkout\Document\Event\DocumentOrderEvent`
