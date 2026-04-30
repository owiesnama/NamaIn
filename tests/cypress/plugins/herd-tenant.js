/**
 * Cypress plugin tasks for managing Herd tenant site links on Windows.
 *
 * Herd's `herd link` command requires UAC to modify the Windows hosts file,
 * which fails in non-interactive subprocess environments (like Cypress setupNodeEvents).
 *
 * This module handles both steps manually:
 *  1. Junction creation via `mklink /J` — no elevation required.
 *  2. Hosts file entry via a SYSTEM-scheduled task — no UAC prompt.
 */

const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const PROJECT_ROOT = path.resolve(__dirname, '../../../');
const HERD_SITES_DIR = path.join(
    process.env.USERPROFILE || process.env.HOME,
    '.config', 'herd', 'config', 'valet', 'Sites',
);
const HOSTS_FILE = 'C:\\Windows\\System32\\drivers\\etc\\hosts';

/**
 * Add a hosts file entry using a SYSTEM-privileged scheduled task.
 * SYSTEM can write to the hosts file without a UAC prompt.
 */
function addHostsEntry(hostname) {
    const entry = `127.0.0.1 ${hostname}`;

    // Skip if already present
    try {
        const hosts = fs.readFileSync(HOSTS_FILE, 'utf8');
        if (hosts.includes(hostname)) {
            return;
        }
    } catch (_) {
        // Can't read hosts file — try to add anyway
    }

    const psEscaped = entry.replace(/"/g, '""');
    const script = `Add-Content '${HOSTS_FILE}' '${entry}'`;
    const taskName = `HerdCypressLink_${Date.now()}`;

    try {
        execSync(
            `schtasks /CREATE /TN "${taskName}" /TR "powershell -NoProfile -Command \\"${script.replace(/"/g, '\\"')}\\"" /SC ONCE /ST 00:00 /RU SYSTEM /F`,
            { stdio: 'pipe', shell: true },
        );
        execSync(`schtasks /RUN /TN "${taskName}"`, { stdio: 'pipe', shell: true });
        // Give it a moment to run
        execSync('timeout /t 2 /nobreak > nul', { stdio: 'pipe', shell: true });
    } catch (_) {
        // Ignore — hosts entry might already exist or schtasks unavailable
    } finally {
        try {
            execSync(`schtasks /DELETE /TN "${taskName}" /F`, { stdio: 'pipe', shell: true });
        } catch (_) {}
    }
}

/**
 * Remove a hosts file entry, also via a SYSTEM-privileged scheduled task.
 */
function removeHostsEntry(hostname) {
    const taskName = `HerdCypressUnlink_${Date.now()}`;
    const script = `(Get-Content '${HOSTS_FILE}') | Where-Object { $_ -notmatch '${hostname}' } | Set-Content '${HOSTS_FILE}'`;

    try {
        execSync(
            `schtasks /CREATE /TN "${taskName}" /TR "powershell -NoProfile -Command \\"${script.replace(/"/g, '\\"')}\\"" /SC ONCE /ST 00:00 /RU SYSTEM /F`,
            { stdio: 'pipe', shell: true },
        );
        execSync(`schtasks /RUN /TN "${taskName}"`, { stdio: 'pipe', shell: true });
        execSync('timeout /t 2 /nobreak > nul', { stdio: 'pipe', shell: true });
    } catch (_) {
        // Ignore
    } finally {
        try {
            execSync(`schtasks /DELETE /TN "${taskName}" /F`, { stdio: 'pipe', shell: true });
        } catch (_) {}
    }
}

/**
 * Create a junction for {name} → PROJECT_ROOT in the Herd Sites directory.
 * mklink /J does not require elevated privileges.
 */
function createJunction(name) {
    const linkPath = path.join(HERD_SITES_DIR, name);
    if (!fs.existsSync(linkPath)) {
        try {
            execSync(`cmd /c mklink /J "${linkPath}" "${PROJECT_ROOT}"`, { stdio: 'pipe', shell: true });
        } catch (_) {
            // Ignore — junction may already exist
        }
    }
}

/**
 * Remove the junction for {name} from the Herd Sites directory.
 */
function removeJunction(name) {
    const linkPath = path.join(HERD_SITES_DIR, name);
    if (fs.existsSync(linkPath)) {
        try {
            execSync(`cmd /c rmdir "${linkPath}"`, { stdio: 'pipe', shell: true });
        } catch (_) {
            // Ignore
        }
    }
}

module.exports = {
    /**
     * Link {slug}.namain → project root in Herd Sites and register DNS.
     * @param {string} slug  e.g. 'cypress-test'
     */
    linkTenantSite(slug) {
        const name = `${slug}.namain`;
        createJunction(name);
        addHostsEntry(`${name}.test`);
        return null;
    },

    /**
     * Remove the {slug}.namain Herd link and DNS entry.
     * @param {string} slug
     */
    unlinkTenantSite(slug) {
        const name = `${slug}.namain`;
        removeJunction(name);
        removeHostsEntry(`${name}.test`);
        return null;
    },
};
