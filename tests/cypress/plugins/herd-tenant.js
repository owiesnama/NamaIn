/**
 * Cypress plugin tasks for managing Herd tenant site links.
 *
 * Each tenant subdomain needs a symlink in the Herd Sites directory before
 * Cypress can visit it. These tasks create and remove that link by calling
 * `herd link` / `herd unlink` from the project root.
 */

const { execSync } = require('child_process');
const path = require('path');

const PROJECT_ROOT = path.resolve(__dirname, '../../../');
const HERD_BIN = path.join(
    process.env.USERPROFILE || process.env.HOME,
    '.config', 'herd', 'bin', 'herd.bat'
);

function runHerd(args) {
    const cmd = `"${HERD_BIN}" ${args}`;
    try {
        execSync(cmd, { cwd: PROJECT_ROOT, stdio: 'pipe', shell: true });
    } catch (_) {
        // Ignore errors (e.g. already linked / already unlinked)
    }
    return null;
}

module.exports = {
    /**
     * Link {slug}.namain → project root in Herd Sites.
     * @param {string} slug  e.g. 'cypress-test'
     */
    linkTenantSite(slug) {
        return runHerd(`link ${slug}.namain`);
    },

    /**
     * Remove the {slug}.namain Herd link.
     * @param {string} slug
     */
    unlinkTenantSite(slug) {
        return runHerd(`unlink ${slug}.namain`);
    },
};
