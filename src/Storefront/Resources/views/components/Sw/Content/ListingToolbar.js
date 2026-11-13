export default class ListingToolbar extends window.ShopwellComponent {
    init() {
        this.sheet = this.el.querySelector('.offcanvas');
        if (!this.sheet) {
            return;
        }

        this.controls = [...this.sheet.querySelectorAll('input, select, textarea')];
        this.onShow = this.saveDraft.bind(this);
        this.onHide = this.restoreDraft.bind(this);
        this.sheet.addEventListener('show.bs.offcanvas', this.onShow);
        this.sheet.addEventListener('hidden.bs.offcanvas', this.onHide);
    }

    saveDraft() {
        this.values = this.controls.map(control => ({ value: control.value, checked: control.checked }));
    }

    restoreDraft() {
        if (!this.values) {
            return;
        }

        this.controls.forEach((control, index) => {
            control.value = this.values[index].value;
            if ('checked' in control) {
                control.checked = this.values[index].checked;
            }
        });
    }

    destroy() {
        this.sheet?.removeEventListener('show.bs.offcanvas', this.onShow);
        this.sheet?.removeEventListener('hidden.bs.offcanvas', this.onHide);
    }
}
