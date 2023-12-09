module.exports = {
    env: {
        node: true,
    },
    plugins: ["unused-imports"],
    extends: ["eslint:recommended", "plugin:vue/vue3-recommended", "prettier"],
    rules: {
        "vue/require-default-prop": "off",
        "vue/multi-word-component-names": "off",
        "no-undef": "off",
        "vue/no-v-html": "off",
        "vue/no-v-text-v-html-on-component": "off",
        "unused-imports/no-unused-imports": "error",
        "unused-imports/no-unused-vars": [
            "warn",
            {
                vars: "all",
                varsIgnorePattern: "^_",
                args: "after-used",
                argsIgnorePattern: "^_",
            },
        ],
    },
};
