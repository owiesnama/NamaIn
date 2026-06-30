/**
 * E2E tests: Stock transfer print page
 *
 * Seeds a stock transfer, opens its Show page, clicks Print, and
 * verifies the standalone print page renders the header and items
 * table. The auto-triggered window.print() dialog is stubbed so it
 * does not block the run.
 */

let transferId;

before(() => {
    Cypress.session.clearAllSavedSessions();
    cy.refreshDatabase();
    cy.tenantLogin();

    cy.php(`
        $tenant = App\\Models\\Tenant::where('slug', 'cypress')->first();
        app()->instance('currentTenant', $tenant);

        $from = App\\Models\\Storage::factory()->create(['name' => 'Transfer Source']);
        $to = App\\Models\\Storage::factory()->create(['name' => 'Transfer Destination']);
        $product = App\\Models\\Product::factory()->create(['name' => 'Transfer Widget']);
        $user = App\\Models\\User::first();

        $transfer = App\\Models\\StockTransfer::create([
            'tenant_id' => $tenant->id,
            'from_storage_id' => $from->id,
            'to_storage_id' => $to->id,
            'created_by' => $user->id,
            'transferred_at' => now(),
            'notes' => 'Cypress transfer note',
        ]);

        App\\Models\\StockTransferLine::create([
            'tenant_id' => $tenant->id,
            'stock_transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        return ['id' => $transfer->id];
    `).then((result) => {
        transferId = result.id;
    });
});

beforeEach(() => {
    cy.tenantLogin();
});

describe('Stock Transfer Print', () => {
    it('exposes a print action on the transfer show page', () => {
        cy.visit(`/stock-transfers/${transferId}`);
        cy.contains('a', 'Print')
            .should('have.attr', 'href')
            .and('include', `/stock-transfers/${transferId}/print`);
    });

    it('opens the print page and renders the header and items table', () => {
        // Stub the print dialog that the print page triggers on mount.
        cy.on('window:before:load', (win) => {
            cy.stub(win, 'print').as('printDialog');
        });

        cy.visit(`/stock-transfers/${transferId}`);

        // Same-tab navigation so Cypress can follow the link.
        cy.contains('a', 'Print').invoke('removeAttr', 'target').click();

        cy.url().should('include', `/stock-transfers/${transferId}/print`);

        // Header
        cy.contains('h1', 'Stock Transfer').should('be.visible');
        cy.contains('Transfer Source').should('be.visible');
        cy.contains('Transfer Destination').should('be.visible');

        // Items table
        cy.get('table tbody tr').should('have.length.at.least', 1);
        cy.get('table tbody').contains('Transfer Widget').should('be.visible');

        // The print dialog was auto-triggered.
        cy.get('@printDialog').should('have.been.called');
    });
});
