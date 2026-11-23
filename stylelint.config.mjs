export default {
    extends: ['stylelint-config-recommended', 'stylelint-config-recommended-vue'],
    overrides: [
        {
            files: ['resources/css/app.css'],
            rules: {
                'at-rule-prelude-no-invalid': [true, { ignoreAtRules: ['apply'] }],
                'at-rule-no-unknown': [true, { ignoreAtRules: ['apply', 'custom-variant', 'source', 'theme'] }],
            },
        },
    ],
};
