export default class PurchaseProduct {
    constructor(attributes = {}) {
        this.product = "";
        this.price = 0;
        this.quantity = 1;
        this.storage = "";
        this.description = null;
        Object.assign(this, attributes);
    }

    total() {
        return this.price * this.quantity;
    }
}
