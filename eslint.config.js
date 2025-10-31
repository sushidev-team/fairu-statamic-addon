import html from '@html-eslint/eslint-plugin';
import htmlParser from '@html-eslint/parser';
import eslintConfigPrettier from 'eslint-config-prettier';
import eslintPluginPrettier from 'eslint-plugin-prettier';
import pluginVue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';

export default [
  {
    ignores: ['vendor/', 'public/', 'content/', 'addons/'],
    rules: {
      'prettier/prettier': 'error',
    },
    plugins: {
      prettier: eslintPluginPrettier,
    },
  },
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['**/*.vue'],
    languageOptions: {
      parser: vueParser,
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
      },
    },
    rules: {
      'prettier/prettier': 'error',
      'vue/multi-word-component-names': 'off',
    },
  },
  {
    files: ['**/*.html'],
    plugins: {
      '@html-eslint': html,
      prettier: eslintPluginPrettier,
    },
    languageOptions: {
      parser: htmlParser,
    },
    rules: {
      'prettier/prettier': 'error',
      'no-multiple-empty-lines': ['error', { max: 1, maxEOF: 0 }],
    },
  },
  eslintConfigPrettier,
];
