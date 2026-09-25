<?php

namespace PHPSTORM_META {
    expectedArguments(
        \Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria::setTotalCountMode(),
        0,
        \Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria::TOTAL_COUNT_MODE_NONE,
        \Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria::TOTAL_COUNT_MODE_EXACT,
        \Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria::TOTAL_COUNT_MODE_NEXT_PAGES
    );

    expectedArguments(
        \Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting::__construct(),
        1,
        \Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting::ASCENDING,
        \Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting::DESCENDING
    );

}
