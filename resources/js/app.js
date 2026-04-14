import "./bootstrap";

import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ZiggyVue } from "../../vendor/tightenco/ziggy/dist/index.esm.js";
import translations from "./Plugins/translations";
import preferences from "./Plugins/preferences";
import { autoAnimatePlugin } from "@formkit/auto-animate/vue";
import CustomSelect from './Components/CustomSelect.vue'


import { translate } from "./Plugins/translations";
window.__ = translate;

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
            .component('CustomSelect', CustomSelect)
            .component('VueMultiselect', CustomSelect)
            .component('VueSelect', CustomSelect)
            .mount(el);
    }
});
