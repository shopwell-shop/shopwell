---
title: Fix snippet search for very long snippets
issue: NEXT-34419
author: Altay Akkus
author_email: altayakkus1993@gmail.com
author_github: @AltayAkkus
---
# Core
* Changed `Shopwell\Core\System\Snippet\Filter\TermFilter` and `Shopwell\Core\System\Snippet\Filter\NamespaceFilter` so they can handle large snippets.
* Deprecated `Shopwell\Core\System\Snippet\Exception\FilterNotFoundException`, which will be removed in v6.7.0.0. Use `Shopwell\Core\System\Snippet\SnippetException::filterNotFound` instead.
* Deprecated `Shopwell\Core\System\Snippet\Exception\InvalidSnippetFileException`, which will be removed in v6.7.0.0. Use `Shopwell\Core\System\Snippet\SnippetException::invalidSnippetFile` instead.
___
# Next Major Version Changes
## Removal of deprecated exceptions
* Removed `Shopwell\Core\System\Snippet\Exception\FilterNotFoundException`. Use `Shopwell\Core\System\Snippet\SnippetException::filterNotFound` instead.
* Removed `Shopwell\Core\System\Snippet\Exception\InvalidSnippetFileException`. Use `Shopwell\Core\System\Snippet\SnippetException::invalidSnippetFile` instead.
