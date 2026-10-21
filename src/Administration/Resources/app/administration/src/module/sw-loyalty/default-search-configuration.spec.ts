/**
 * @sw-package checkout
 */

import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import defaultSearchConfiguration from './default-search-configuration';

describe('src/module/sw-loyalty/default-search-configuration', () => {
    it('is not searchable as a whole', () => {
        expect(defaultSearchConfiguration._searchable).toBe(false);
    });

    it('searches the name of a tier with the high ranking', () => {
        expect(defaultSearchConfiguration.name._searchable).toBe(true);
        expect(defaultSearchConfiguration.name._score).toBe(searchRankingPoint.HIGH_SEARCH_RANKING);
    });
});
