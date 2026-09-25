---
title: Fix mail template timezone
issue: NEXT-12450
author: Hans Höchtl
author_email: hhoechtl@1drop.de 
author_github: @hhoechtl
---
# Core
* Changed `\Shopwell\Core\Framework\Adapter\Twig\StringTemplateRenderer`, extensions of the private twig instance are
  now in sync with the global twig instance, which fixes a problem with timezone rendering in mail templates.
