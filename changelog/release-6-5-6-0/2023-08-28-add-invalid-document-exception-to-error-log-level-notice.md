---
title: Add invalid document exception to error log level notice
issue: NEXT-30170
---
# Core
* Added these following `error-codes` into the error log level `notice` of `shopwell.yaml`:
  * `DOCUMENT__INVALID_DOCUMENT_ID`
  * `DOCUMENT__INVALID_GENERATOR_TYPE`
  * `DOCUMENT__ORDER_NOT_FOUND`
* Added these following exception classes into the `exception` part of `framework.yaml`:
  * `Shopwell\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException`
  * `Shopwell\Core\Checkout\Document\Exception\InvalidDocumentException`
  * `Shopwell\Core\Checkout\Document\Exception\DocumentGenerationException`
  * `Shopwell\Core\Checkout\Document\Exception\DocumentNumberAlreadyExistsException`
  * `Shopwell\Core\Checkout\Document\Exception\InvalidDocumentRendererException`
  * `Shopwell\Core\Checkout\Document\Exception\InvalidFileGeneratorTypeException`
