/**
 * @sw-package framework
 */
import { watch } from 'vue';

let isInitialized = false;

/**
 * @private
 */
export default function LanguageAutoFetchingService() {
    if (isInitialized) return;
    isInitialized = true;

    // initial loading of the language
    loadLanguage(Shopwell.Context.api.languageId);

    // load the language Entity
    async function loadLanguage(newLanguageId) {
        const languageRepository = Shopwell.Service('repositoryFactory').create('language');
        const newLanguage = await languageRepository.get(newLanguageId, {
            ...Shopwell.Context.api,
            inheritance: true,
        });

        Shopwell.Store.get('context').api.language = newLanguage;
    }

    watch(() => Shopwell.Store.get('context').api.languageId, loadLanguage);
}
