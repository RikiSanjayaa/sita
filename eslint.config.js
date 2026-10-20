import js from '@eslint/js';
import prettier from 'eslint-config-prettier/flat';
import importPlugin from 'eslint-plugin-import';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import typescript from 'typescript-eslint';

const paletteHues =
    'red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
const paletteUtilities =
    'bg|text|border|border-[xytblrse]|ring|ring-offset|outline|fill|stroke|from|via|to|divide|shadow|accent|caret|decoration|placeholder';
// Matches e.g. `bg-red-500`, `dark:text-amber-300`, `border-emerald-200/60`, `!bg-sky-50`.
const rawPaletteRegex = `/(^|\\W)(${paletteUtilities})-(${paletteHues})-[0-9]{2,3}(\\W|$)/`;
const rawPaletteMessage =
    'Raw Tailwind palette color. Use semantic tokens (success, warning, danger, info, primary, muted), StatusBadge from @/components/patterns/status-badge, or statusClassName from @/lib/status-styles. See docs/DESIGN_SYSTEM.md.';
const legacyChatPage = 'resources/js/pages/dosen/pesan-bimbingan.tsx';

/** @type {import('eslint').Linter.Config[]} */
export default [
    // Preserve the existing general-lint exemption, not a palette-guard exemption.
    ...[
        js.configs.recommended,
        reactHooks.configs.flat.recommended,
        ...typescript.configs.recommended,
        {
            ...react.configs.flat.recommended,
            ...react.configs.flat['jsx-runtime'], // Required for React 17+
            languageOptions: {
                globals: {
                    ...globals.browser,
                },
            },
            rules: {
                'react/react-in-jsx-scope': 'off',
                'react/prop-types': 'off',
                'react/no-unescaped-entities': 'off',
            },
            settings: {
                react: {
                    version: 'detect',
                },
            },
        },
        {
            ...importPlugin.flatConfigs.recommended,
            settings: {
                'import/resolver': {
                    typescript: true,
                    node: true,
                },
            },
            rules: {
                'import/order': [
                    'error',
                    {
                        groups: [
                            'builtin',
                            'external',
                            'internal',
                            'parent',
                            'sibling',
                            'index',
                        ],
                        'newlines-between': 'always',
                        alphabetize: {
                            order: 'asc',
                            caseInsensitive: true,
                        },
                    },
                ],
            },
        },
        {
            ...importPlugin.flatConfigs.typescript,
            files: ['**/*.{ts,tsx}'],
        },
    ].map((config) => ({
        ...config,
        ignores: [...(config.ignores ?? []), legacyChatPage],
    })),
    {
        // Warning-only guard: pages must use semantic status tokens
        // (success/warning/danger/info/primary/muted) or patterns from
        // @/components/patterns, not raw Tailwind palette hues. See docs/DESIGN_SYSTEM.md.
        files: ['resources/js/pages/**/*.tsx'],
        languageOptions: { parser: typescript.parser },
        rules: {
            'no-restricted-syntax': [
                'warn',
                {
                    selector: `Literal[value=${rawPaletteRegex}]`,
                    message: rawPaletteMessage,
                },
                {
                    selector: `TemplateElement[value.raw=${rawPaletteRegex}]`,
                    message: rawPaletteMessage,
                },
            ],
        },
    },
    {
        ignores: [
            'vendor',
            'node_modules',
            'public',
            'bootstrap/ssr',
            'tailwind.config.js',
            'resources/js/actions',
            'resources/js/routes',
            'tmp-filament',
            'playwright-report',
            'test-results',
            '.*',
            'tmp-*',
        ],
    },
    prettier, // Turn off all rules that might conflict with Prettier
];
