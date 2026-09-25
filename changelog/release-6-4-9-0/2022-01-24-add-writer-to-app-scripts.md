---
title: Add writer to custom endpoint AppScripts
issue: NEXT-19487
---
# Core
* Added `\Shopwell\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacade` to provide `write` functionality to app scripts.
* Changed `\Shopwell\Core\Framework\Script\Api\ApiHook` and `\Shopwell\Core\Framework\Script\Api\StoreApiHook` to provide access to the new `writer` service.
