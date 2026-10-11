import { searchRankingPoint } from 'src/app/service/search-ranking.service';

/**
 * @sw-package checkout
 */

const defaultSearchConfiguration = {
    _searchable: true,
    customerNumber: {
        _searchable: true,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
    name: {
        _searchable: true,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
    company: {
        _searchable: true,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
    email: {
        _searchable: true,
        _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
    },
    defaultBillingAddress: {
        name: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
        country: {
            name: {
                _searchable: true,
                _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
            },
        },
        city: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
        zipcode: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
        company: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
        street: {
            _searchable: false,
            _score: searchRankingPoint.HIGH_SEARCH_RANKING,
        },
        phoneNumber: {
            _searchable: false,
            _score: searchRankingPoint.HIGH_SEARCH_RANKING,
        },
        additionalAddressLine1: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
        additionalAddressLine2: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
    },
    defaultShippingAddress: {
        name: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
        country: {
            name: {
                _searchable: true,
                _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
            },
        },
        city: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
        zipcode: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
        company: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
        street: {
            _searchable: false,
            _score: searchRankingPoint.HIGH_SEARCH_RANKING,
        },
        phoneNumber: {
            _searchable: false,
            _score: searchRankingPoint.HIGH_SEARCH_RANKING,
        },
        additionalAddressLine1: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
        additionalAddressLine2: {
            _searchable: false,
            _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
        },
    },
    tags: {
        name: {
            _searchable: true,
            _score: searchRankingPoint.HIGH_SEARCH_RANKING,
        },
    },
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defaultSearchConfiguration;
