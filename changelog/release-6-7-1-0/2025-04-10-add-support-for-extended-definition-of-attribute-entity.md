---
title: Add support for extended definition of attribute entity
issue: #8393
author: Thuy Le
author_email: thuy.le@shopwell.com
author_github: @thuylt
---
# Core
* Added `shopwell.entity.definition` tag to `AttributeEntityDefinition`, `AttributeTranslationDefinition` and `AttributeMappingDefinition` in `Shopwell\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass`.
* Changed `Shopwell\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass` to add metadata when creating an instance of `AttributeEntityDefinition` class.
* Changed `Shopwell\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass` to skip adding a repository definition to compiled container for `AttributeEntityDefinition` class.
* Changed the compiler pass priority for `AttributeEntityCompilerPass` from `beforeRemoving` to `beforeOptimization` in `Shopwell\Core\Framework\Framework`.
