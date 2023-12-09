import { usePage } from "@inertiajs/vue3";

export default {
    install: (app) => {
        let transalte = (key, replace = {}) => {
            const translations = usePage().props.translations;
            let translation = translations[key] ? translations[key] : key;
            Object.keys(replace).forEach(function (key) {
                translation = translation.replace(":" + key, replace[key]);
            });

            return translation;
        };

        app.config.globalProperties.__ = window.__ = transalte;
        app.provide("__", transalte);
    },
};
