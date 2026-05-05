/**
 * Cypress plugin tasks for managing Herd tenant site links.
 *
 * Creates symlinks directly in the Herd Sites directory instead of
 * calling `herd link` (which requires UAC elevation on Windows).
 */

const fs = require('fs');
const path = require('path');

const PROJECT_ROOT = path.resolve(__dirname, '../../../');
const SITES_DIR = path.join(
    process.env.USERPROFILE || process.env.HOME,
    '.config', 'herd', 'config', 'valet', 'Sites'
);

module.exports = {
    /**
     * Ensure {slug}.namain symlink exists in Herd Sites directory.
     * Creates it if missing; silently succeeds if already linked.
     */
    linkTenantSite(slug) {
        const linkPath = path.join(SITES_DIR, `${slug}.namain`);

        if (fs.existsSync(linkPath)) {
            return null; // Already linked
        }

        try {
            fs.symlinkSync(PROJECT_ROOT, linkPath, 'junction');
        } catch (_) {
            // Ignore — may already exist or lack permissions
        }

        return null;
    },

    /**
     * Remove the {slug}.namain symlink from Herd Sites.
     */
    unlinkTenantSite(slug) {
        const linkPath = path.join(SITES_DIR, `${slug}.namain`);

        try {
            if (fs.existsSync(linkPath)) {
                fs.unlinkSync(linkPath);
            }
        } catch (_) {
            // Ignore
        }

        return null;
    },
};
