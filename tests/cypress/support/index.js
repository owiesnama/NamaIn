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

before(() => {
    cy.task('activateCypressEnvFile', {}, { log: false });
    cy.artisan('config:clear', {}, { log: false });
    cy.refreshRoutes();

    // SAFETY GUARD: the suite runs destructive migrate:fresh. Refuse to run
    // unless the live app is actually connected to an isolated test database.
    // If the .env swap didn't take effect (e.g. a warm php-fpm worker still
    // holding the dev env), this aborts the run instead of wiping real data.
    cy.php('return config("database.connections.".config("database.default").".database");').then((db) => {
        expect(
            String(db),
            `Refusing to run: connected DB "${db}" is not an isolated test database (expected a name containing "cypress" or "_test"). Restart php-fpm after the env swap.`
        ).to.match(/cypress|_test/i);
    });
});

after(() => {
    cy.task('activateLocalEnvFile', {}, { log: false });
    cy.artisan('config:clear', {}, { log: false });
});
