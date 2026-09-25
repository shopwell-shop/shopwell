---
title: Translations of versions are not reliable read through the DAL
issue: NEXT-13274
author: Jan Pietrzyk
author_email: j.pietrzyk@shopwell.com
author_github: JanPietrzyk
---
# Core
* Changed method `join` in `\Shopwell\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\TranslatedJoinBuilder` to build a query selecting versions if definition is version aware.
