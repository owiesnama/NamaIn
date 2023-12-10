import "./bootstrap";

import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ZiggyVue } from "../../vendor/tightenco/ziggy/dist/vue.m";
import translations from "./Plugins/translations";
import preferences from "./Plugins/preferences";
import { autoAnimatePlugin } from "@formkit/auto-animate/vue";
import VueMultiselect from 'vue-multiselect'
import "vue-multiselect/dist/vue-multiselect.css"


const appName =
    window.document.getElementsByTagName("title")[0]?.innerText || "Laravel";

createInertiaApp({
    progress: {
        color: "#29d"
    },
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue")
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(autoAnimatePlugin)
            .use(ZiggyVue, Ziggy)
            .use(translations)
            .use(preferences)
            .component('v-select', VueMultiselect)
            .mount(el);
    }
});
