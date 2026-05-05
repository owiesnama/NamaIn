/// <reference types="cypress" />

/**
 * @type {Cypress.PluginConfig}
 */
module.exports = (on, config) => {
    on('task', {
        ...require('./swap-env'),
    });
};
