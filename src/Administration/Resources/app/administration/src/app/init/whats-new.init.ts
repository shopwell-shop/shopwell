/**
 * @private
 * @sw-package framework
 */
export default function initializeWhatsNew(): void {
    Shopwell.Service('loginService').addOnLogoutListener(() => {
        Shopwell.Store.get('whatsNew').$reset();
    });
}
