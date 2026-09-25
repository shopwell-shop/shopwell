/**
 * @sw-package framework
 *
 * @private
 * @memberOf module:core/service/locale
 * @constructor
 * @method createShortcutService
 * @returns {Object}
 */
export default class LocaleHelperService {
    _localeRepository;

    _localeFactory;

    _snippetService;

    _Shopwell;

    constructor({ Shopwell, localeRepository, snippetService, localeFactory }) {
        this._Shopwell = Shopwell;
        this._snippetService = snippetService;
        this._localeFactory = localeFactory;
        this._localeRepository = localeRepository;
    }

    async setLocaleWithId(localeId) {
        const { code } = await this._localeRepository.get(localeId, this._Shopwell.Context.api);

        await this.setLocaleWithCode(code);
    }

    async setLocaleWithCode(localeCode) {
        await this._snippetService.getSnippets(this._localeFactory, localeCode);
        await this._Shopwell.Store.get('session').setAdminLocale(localeCode);
    }
}
