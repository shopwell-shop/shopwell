---
title: Optimize initial state id loading
issue: NEXT-20687
author: Oliver Skroblin
author_email: o.skroblin@shopwell.com
author_github: OliverSkroblin
---
# Core
* Deprecated `\Shopwell\Core\System\StateMachine\StateMachineRegistry::getInitialState`, use `\Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader::get` instead
* Added twig cache in `\Shopwell\Core\Framework\Adapter\Twig\StringTemplateRenderer` to avoid unnecessary template parsing for mail rendering and other templates. The cache key is built with the content of the provided template.