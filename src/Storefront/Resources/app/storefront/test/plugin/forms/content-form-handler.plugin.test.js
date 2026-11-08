import ContentFormHandlerPlugin from 'src/plugin/forms/content-form-handler.plugin';

const template = `
    <div class="sw-content-form">
      <form id="test-form">
        <button type="submit">Submit</button>
      </form>
    </div>
`.trim();

describe('Form CMS Handler tests', () => {
    let contentFormHandlerPlugin = undefined;
    let formElement = undefined;
    let submitButtonElement = undefined;

    beforeEach(() => {
        document.body.innerHTML = template;

        formElement = document.getElementById('test-form');
        formElement.parentElement.scrollIntoView = jest.fn(); // Used by content-form-handler plugin, but not implemented by jsdom.

        submitButtonElement = formElement.querySelector('button[type=submit]');

        contentFormHandlerPlugin = new ContentFormHandlerPlugin(formElement);
    });

    test('content form handler plugin exists', () => {
        expect(typeof contentFormHandlerPlugin).toBe('object');
    });

    test('content form handler stops submitting after it was destroyed', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('[{"type":"success","alert":""}]'),
            }),
        );

        contentFormHandlerPlugin.destroy();

        formElement.dispatchEvent(new Event('submit'));
        await new Promise(process.nextTick);

        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('content form handler resets form after successful ajax submission', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('[{"type":"success","alert":""}]'),
            }),
        );

        const resetSpy = jest.spyOn(formElement, 'reset');

        formElement.dispatchEvent(new Event('submit'));
        await new Promise(process.nextTick);

        expect(global.fetch).toHaveBeenCalled();
        expect(resetSpy).toHaveBeenCalled();
    });

    test('content form handler does not reset after unsuccessful ajax submission', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('[{"type":"danger","alert":""}]'),
            }),
        );

        const resetSpy = jest.spyOn(formElement, 'reset');

        formElement.dispatchEvent(new Event('submit'));
        await new Promise(process.nextTick);

        expect(global.fetch).toHaveBeenCalled();
        expect(resetSpy).not.toHaveBeenCalled();
    });

    test('content form handler disables submit button while request is pending', () => {
        global.fetch = jest.fn(() => new Promise(() => {}));

        formElement.dispatchEvent(new Event('submit'));

        expect(submitButtonElement.disabled).toBe(true);
    });

    test('content form handler re-enables submit button after error response', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                text: () => Promise.resolve('[{"type":"danger","alert":""}]'),
            }),
        );

        formElement.dispatchEvent(new Event('submit'));

        expect(submitButtonElement.disabled).toBe(true);

        await new Promise(process.nextTick);

        expect(submitButtonElement.disabled).toBe(false);
    });
});
