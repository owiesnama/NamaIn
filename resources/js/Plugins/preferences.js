import { usePage } from "@inertiajs/vue3";

const USER_LEVEL_KEYS = ['language', 'timezone', 'dateFormat'];

export default {
    install: (app) => {
        let preferences = (key, defaultValue = "") => {
            const page = usePage().props;

            if (USER_LEVEL_KEYS.includes(key)) {
                const userValue = page.userPreferences?.[key];
                if (userValue !== undefined && userValue !== null) {
                    return userValue;
                }
            }

            const tenantPrefs = page.preferences;
            if (tenantPrefs && tenantPrefs.hasOwnProperty(key)) {
                return tenantPrefs[key] ? tenantPrefs[key] : defaultValue;
            }

            return defaultValue;
        };
        app.config.globalProperties.preferences = window.preferences =
            preferences;
        app.provide("preferences", preferences);
        app.preferences = preferences;
    },
};
