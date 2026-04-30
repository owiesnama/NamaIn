const { defineConfig } = require('cypress')
const herdTenant = require('./tests/cypress/plugins/herd-tenant')

module.exports = defineConfig({
    chromeWebSecurity: false,
    retries: 2,
    defaultCommandTimeout: 5000,
    watchForFileChanges: false,
    viewportWidth: 1280,
    viewportHeight: 800,
    videosFolder: 'tests/cypress/videos',
    screenshotsFolder: 'tests/cypress/screenshots',
    fixturesFolder: 'tests/cypress/fixture',
    e2e: {
        setupNodeEvents(on, config) {
            // Link the test tenant site in Herd before Cypress validates baseUrl
            herdTenant.linkTenantSite('cypress-test')

            require('./tests/cypress/plugins/index.js')(on, config)

            on('after:run', () => {
                herdTenant.unlinkTenantSite('cypress-test')
            })
        },
        baseUrl: 'https://cypress-test.namain.test',
        specPattern: 'tests/cypress/integration/**/*.cy.{js,jsx,ts,tsx}',
        supportFile: 'tests/cypress/support/index.js',
    },
})
