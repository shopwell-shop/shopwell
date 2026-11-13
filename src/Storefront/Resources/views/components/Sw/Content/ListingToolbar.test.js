import { afterEach, describe, expect, it } from 'vitest';
import 'shopwell';
import ListingToolbar from './ListingToolbar';

function mount(markup = '<div class="offcanvas"><select><option value="all">All</option><option value="paid">Paid</option></select><input type="checkbox"></div>') {
    const form = document.createElement('form');
    form.innerHTML = markup;
    document.body.appendChild(form);
    const component = new ListingToolbar(form);
    component.init();
    return { form, component, sheet: form.querySelector('.offcanvas') };
}

describe('Sw:Content:ListingToolbar', () => {
    afterEach(() => { document.body.innerHTML = ''; });

    it('restores unsubmitted choices when the sheet closes', () => {
        const { form, sheet, component } = mount();
        sheet.dispatchEvent(new Event('show.bs.offcanvas'));
        form.querySelector('select').value = 'paid';
        form.querySelector('input').checked = true;
        sheet.dispatchEvent(new Event('hidden.bs.offcanvas'));
        expect(form.querySelector('select').value).toBe('all');
        expect(form.querySelector('input').checked).toBe(false);
        component.destroy();
    });

    it('supports a toolbar with filters replaced by inline content', () => {
        const { component } = mount('<input type="search">');
        expect(() => component.destroy()).not.toThrow();
    });

    it('ignores hidden events without an opening snapshot', () => {
        const { sheet, component } = mount();
        expect(() => sheet.dispatchEvent(new Event('hidden.bs.offcanvas'))).not.toThrow();
        component.destroy();
    });

    it('removes listeners when destroyed', () => {
        const { form, sheet, component } = mount();
        sheet.dispatchEvent(new Event('show.bs.offcanvas'));
        component.destroy();
        form.querySelector('select').value = 'paid';
        sheet.dispatchEvent(new Event('hidden.bs.offcanvas'));
        expect(form.querySelector('select').value).toBe('paid');
    });
});
