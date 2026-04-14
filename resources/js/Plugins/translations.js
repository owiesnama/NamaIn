import { usePage } from "@inertiajs/vue3";

export function translate(key, replace = {}) {
    const translations = usePage().props.translations || {};
    let translation = translations[key] ? translations[key] : key;
    Object.keys(replace).forEach(function (key) {
        translation = translation.replace(":" + key, replace[key]);
    });

    return translation;
}

export default {
    install: (app) => {
        app.config.globalProperties.__ = translate;
        app.provide("__", translate);
    },
};
