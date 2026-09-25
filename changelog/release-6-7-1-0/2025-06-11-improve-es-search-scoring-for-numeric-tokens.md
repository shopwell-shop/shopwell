---
title: Improve ES search scoring for numeric tokens
author: thuong.le
author_email: levienthuong@gmail.com
author_github: @thuong.le
---
# Core
* Changed the logic in `\Shopwell\Elasticsearch\TokenQueryBuilder::build` to omit the fuzziness for numeric tokens query to improve search relevance
* Added new method `\Shopwell\Elasticsearch\Product\SearchFieldConfig::getFuzziness` to return the fuzziness of the search config DTO