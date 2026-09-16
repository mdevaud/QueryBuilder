import { Controller } from '@hotwired/stimulus';

/**
 * Shows the parameters that belong to the selected predefined action: display
 * actions take a limit and a persistence, ApplyDiscount the discount fields,
 * ApplyCartDiscount the cart discount fields and no product selection tree.
 */
export default class extends Controller {
    static targets = ['code', 'displayParams', 'discountParams', 'cartDiscountParams', 'treeGroup'];

    static values = {
        discountCode: String,
        cartDiscountCode: String,
    };

    connect() {
        this.sync();
    }

    sync() {
        const code = this.codeTarget.value;
        const isDiscount = code === this.discountCodeValue;
        const isCartDiscount = code === this.cartDiscountCodeValue;

        this.displayParamsTarget.hidden = isCartDiscount;
        this.discountParamsTarget.hidden = !isDiscount;
        this.cartDiscountParamsTarget.hidden = !isCartDiscount;
        // The cart discount has no product target: its selection tree is meaningless
        this.treeGroupTarget.hidden = isCartDiscount;
    }
}
