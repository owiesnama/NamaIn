/**
 * Create a new user and log them in.
 *
 * @param {Object} attributes
 *
 * @example cy.login();
 *          cy.login({ name: 'JohnDoe' });
 *          cy.login({ attributes: { name: 'JohnDoe' }, state: 'guest', load: ['comments] });
 */
Cypress.Commands.add('login', (attributes = {}) => {
    // Are we using the new object system.
    let requestBody = attributes.attributes || attributes.state || attributes.load ? attributes : { attributes };

    return cy
        .csrfToken()
        .then((token) => {
            return cy.request({
                method: 'POST',
                url: '/__cypress__/login',
                body: { ...requestBody, _token: token },
                log: false,
            });
        })
        .then(({ body }) => {
            Cypress.Laravel.currentUser = body;

            Cypress.log({
                name: 'login',
                message: JSON.stringify(body),
                consoleProps: () => ({ user: body }),
            });
        })
        .its('body', { log: false });
});

/**
 * Fetch the currently authenticated user object.
 *
 * @example cy.currentUser();
 */
Cypress.Commands.add('currentUser', () => {
    return cy.csrfToken().then((token) => {
        return cy
            .request({
                method: 'POST',
                url: '/__cypress__/current-user',
                body: { _token: token },
                log: false,
            })
            .then((response) => {
                if (!response.body) {
                    cy.log('No authenticated user found.');
                }

                Cypress.Laravel.currentUser = response?.body;

                return response?.body;
            });
    });
});


/**
 * Logout the current user.
 *
 * @example cy.logout();
 */
Cypress.Commands.add('logout', () => {
    return cy
        .csrfToken()
        .then((token) => {
            return cy.request({
                method: 'POST',
                url: '/__cypress__/logout',
                body: { _token: token },
                log: false,
            });
        })
        .then(() => {
            Cypress.log({ name: 'logout', message: '' });
        });
});

/**
 * Fetch a CSRF token.
 *
 * @example cy.csrfToken();
 */
Cypress.Commands.add('csrfToken', () => {
    return cy
        .request({
            method: 'GET',
            url: '/__cypress__/csrf_token',
            log: false,
        })
        .its('body', { log: false });
});

/**
 * Fetch and store all named routes.
 *
 * @example cy.refreshRoutes();
 */
Cypress.Commands.add('refreshRoutes', () => {
    return cy.csrfToken().then((token) => {
        return cy
            .request({
                method: 'POST',
                url: '/__cypress__/routes',
                body: { _token: token },
                log: false,
            })
            .its('body', { log: false })
            .then((routes) => {
                cy.writeFile(Cypress.config().supportFolder + '/routes.json', routes, {
                    log: false,
                });

                Cypress.Laravel.routes = routes;
            });
    });
});

/**
 * Visit the given URL or route.
 *
 * @example cy.visit('foo/path');
 *          cy.visit({ route: 'home' });
 *          cy.visit({ route: 'team', parameters: { team: 1 } });
 */
Cypress.Commands.overwrite('visit', (originalFn, subject, options) => {
    if (subject.route) {
        return originalFn({
            url: Cypress.Laravel.route(subject.route, subject.parameters || {}),
            method: Cypress.Laravel.routes[subject.route].method[0],
            ...options
        });
    }

    return originalFn(subject, options);
});

/**
 * Create a new Eloquent factory.
 *
 * @param {String} model
 * @param {Number|null} times
 * @param {Object} attributes
 *
 * @example cy.create('App\\User');
 *          cy.create('App\\User', 2);
 *          cy.create('App\\User', 2, { active: false });
 *          cy.create('App\\User', { active: false });
 *          cy.create('App\\User', 2, { active: false });
 *          cy.create('App\\User', 2, { active: false }, ['profile']);
 *          cy.create('App\\User', 2, { active: false }, ['profile'], ['guest']);
 *          cy.create('App\\User', { active: false }, ['profile']);
 *          cy.create('App\\User', { active: false }, ['profile'], ['guest']);
 *          cy.create('App\\User', ['profile']);
 *          cy.create('App\\User', ['profile'], ['guest']);
 *          cy.create({ model: 'App\\User', state: ['guest'], relations: ['profile'], count: 2 }
 */
Cypress.Commands.add('create', (model, count = 1, attributes = {}, load = [], state = []) => {
    let requestBody = {};

    if (typeof model !== 'object') {
        if (Array.isArray(count)) {
            state = attributes;
            attributes = {};
            load = count;
            count = 1;
        }

        if (typeof count === 'object') {
            state = load;
            load = attributes;
            attributes = count;
            count = 1;
        }

        requestBody = { model, state, attributes, load, count };
    } else {
        requestBody = model;
    }

    return cy
        .csrfToken()
        .then((token) => {
            return cy.request({
                method: 'POST',
                url: '/__cypress__/factory',
                body: { ...requestBody, _token: token },
                log: false,
            });
        })
        .then((response) => {
            Cypress.log({
                name: 'create',
                message: requestBody.model + (requestBody.count > 1 ? ` (${requestBody.count} times)` : ''),
                consoleProps: () => ({ [model]: response.body }),
            });
        })
        .its('body', { log: false });
});

/**
 * Refresh the database state.
 *
 * Fast reset: TRUNCATE every table instead of running `migrate:fresh` (which
 * drops and replays every migration on each spec). The database itself is kept,
 * so Herd's persistent php-fpm connections stay valid. The schema is migrated
 * once on first use, then each reset just truncates + re-seeds the permission
 * catalog (the seeding migration only runs once, so it must be re-applied).
 *
 * @example cy.refreshDatabase();
 */
Cypress.Commands.add('refreshDatabase', () => {
    // Fast reset: TRUNCATE every table instead of migrate:fresh (which drops and
    // replays every migration). The schema persists across specs — TRUNCATE keeps
    // the tables — so we only need to migrate on a fresh database. Every other
    // reset is a single round-trip that truncates and re-seeds the permission
    // catalog (its seeding migration only runs once, so it must be re-applied).
    const reset = `
        if (! Illuminate\\Support\\Facades\\Schema::hasTable('permissions')) {
            return 'needs-migrate';
        }

        // The schema persists across specs, so newly added migrations must
        // still be applied — a stale schema otherwise 500s every page.
        if (app(Illuminate\\Database\\Migrations\\Migrator::class)->repositoryExists()) {
            Illuminate\\Support\\Facades\\Artisan::call('migrate', ['--force' => true]);
        }

        $tables = collect(Illuminate\\Support\\Facades\\DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
        ))->pluck('tablename')
          ->reject(fn ($t) => $t === 'migrations')
          ->map(fn ($t) => '"' . $t . '"')
          ->implode(', ');

        Illuminate\\Support\\Facades\\DB::statement(
            "TRUNCATE TABLE {$tables} RESTART IDENTITY CASCADE"
        );

        (new Database\\Seeders\\PermissionSeeder)->run();

        return 'reset';
    `;

    return cy.php(reset).then((result) => {
        if (result === 'needs-migrate') {
            cy.artisan('migrate', { '--force': true });
            cy.php(reset);
        }
    });
});

/**
 * Seed the database.
 *
 * @param {String} seederClass
 *
 * @example cy.seed();
 *          cy.seed('PlansTableSeeder');
 */
Cypress.Commands.add('seed', (seederClass = '') => {
    let options = {};

    if (seederClass) {
        options['--class'] = seederClass;
    }

    return cy.artisan('db:seed', options);
});

/**
 * Trigger an Artisan command.
 *
 * @param {String} command
 * @param {Object} parameters
 * @param {Object} options
 *
 * @example cy.artisan('cache:clear');
 */
Cypress.Commands.add('artisan', (command, parameters = {}, options = {}) => {
    options = Object.assign({}, { log: true }, options);

    if (options.log) {
        Cypress.log({
            name: 'artisan',
            message: (() => {
                let message = command;

                for (let key in parameters) {
                    message += ` ${key}="${parameters[key]}"`;
                }

                return message;
            })(),
            consoleProps: () => ({ command, parameters }),
        });
    }

    return cy.csrfToken().then((token) => {
        return cy.request({
            method: 'POST',
            url: '/__cypress__/artisan',
            body: { command: command, parameters: parameters, _token: token },
            log: false,
        });
    });
});

/**
 * Execute arbitrary PHP.
 *
 * @param {String} command
 *
 * @example cy.php('2 + 2');
 *          cy.php('App\\User::count()');
 */
Cypress.Commands.add('php', (command) => {
    return cy
        .csrfToken()
        .then((token) => {
            return cy.request({
                method: 'POST',
                url: '/__cypress__/run-php',
                body: { command: command, _token: token },
                log: false,
            });
        })
        .then((response) => {
            Cypress.log({
                name: 'php',
                message: command,
                consoleProps: () => ({ result: response.body.result }),
            });
        })
        .its('body.result', { log: false });
});
