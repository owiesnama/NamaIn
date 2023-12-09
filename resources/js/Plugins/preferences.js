import { usePage } from "@inertiajs/vue3";
export default {
    install: (app) => {
        let preferences = (key, defaultValue = "") => {
            let preferences = usePage().props.preferences;
            if (!preferences.hasOwnProperty(key)) {
                return defaultValue;
            }
            return preferences[key] ? preferences[key] : defaultValue;
        };
        app.config.globalProperties.preferences = window.preferences =
            preferences;
        app.provide("preferences", preferences);
        app.preferences = preferences;
    },
};
