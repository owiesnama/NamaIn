// ***********************************************************
// This example support/index.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

/// <reference types="./" />

import './laravel-commands';
import './laravel-routes';
import './assertions';
import './tenant-commands';

/**
 * Suppress known third-party initialisation errors that are irrelevant to
 * the test assertions but would otherwise abort the test suite.
 *
 * - Pusher: app key is intentionally empty in the Cypress env file;
 *   Echo is not under test and its absence must not break navigation tests.
 */
Cypress.on('uncaught:exception', (err) => {
    if (err.message && err.message.includes('app key')) {
        return false;
    }

    // Let all other errors propagate so real bugs are still caught.
    return true;
});

before(() => {
    cy.task('activateCypressEnvFile', {}, { log: false });
    cy.artisan('config:clear', {}, { log: false });
    cy.refreshRoutes();
});

after(() => {
    cy.task('activateLocalEnvFile', {}, { log: false });
    cy.artisan('config:clear', {}, { log: false });
});
