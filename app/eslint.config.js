import js from '@eslint/js'
import { defineConfig } from 'eslint/config'
import stylistic from '@stylistic/eslint-plugin'
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'
import tseslint from 'typescript-eslint'

export default defineConfig(
  {
    ignores: ['dist/', 'dev-dist/', 'node_modules/', '.wrangler/'],
  },
  js.configs.recommended,
  tseslint.configs.recommended,
  pluginVue.configs['flat/recommended'],
  {
    files: ['**/*.vue'],
    languageOptions: {
      parserOptions: {
        parser: tseslint.parser,
      },
    },
  },
  {
    languageOptions: {
      globals: {
        ...globals.browser,
        // Compile-time constant defined in vite.config.ts.
        __WEB_TARGET__: 'readonly',
      },
    },
    plugins: {
      '@stylistic': stylistic,
    },
    rules: {
      // House style: two spaces, no semicolons, single quotes.
      '@stylistic/indent': ['error', 2],
      '@stylistic/semi': ['error', 'never'],
      '@stylistic/quotes': ['error', 'single', { avoidEscape: true }],
      // Blocks go template, then script, then style. The Vue default is the
      // other way round, so this rule is set explicitly.
      'vue/block-order': ['error', { order: ['template', 'script', 'style'] }],
      // View components are named by their route, for example LoginView.
      'vue/multi-word-component-names': 'off',
    },
  },
)
