import BlogReadingPlugin from 'src/plugin/blog-reading/blog-reading.plugin';

/**
 * @sw-package discovery
 */
describe('BlogReadingPlugin share links', () => {
    const originalMatchMedia = window.matchMedia;
    let pluginInstance;

    beforeEach(() => {
        jest.useFakeTimers();
        window.matchMedia = jest.fn().mockReturnValue({ matches: false });
        window.history.replaceState({}, '', '/content-system/preview?token=private-preview-token');
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText: jest.fn().mockResolvedValue(undefined) },
        });
        document.body.innerHTML = `
            <article data-blog-reading>
                <button data-blog-share-copy
                        data-blog-share-copy-url="https://shopwell.cn/blog/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
                        data-blog-share-copy-copied-label="Copied">Copy link</button>
            </article>
        `;
        pluginInstance = new BlogReadingPlugin(document.querySelector('[data-blog-reading]'));
    });

    afterEach(() => {
        pluginInstance.destroy();
        jest.clearAllTimers();
        jest.useRealTimers();
        window.matchMedia = originalMatchMedia;
        window.history.replaceState({}, '', '/');
        delete navigator.clipboard;
        document.body.innerHTML = '';
    });

    test('copies the public article URL instead of a private preview URL', () => {
        document.querySelector('[data-blog-share-copy]').click();

        expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
            'https://shopwell.cn/blog/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        );
    });

    test('keeps the current URL fallback for legacy templates', () => {
        const button = document.querySelector('[data-blog-share-copy]');
        button.removeAttribute('data-blog-share-copy-url');
        button.click();

        expect(navigator.clipboard.writeText).toHaveBeenCalledWith(window.location.href);
    });
});
