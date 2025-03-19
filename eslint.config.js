import html from '@html-eslint/eslint-plugin';
import htmlParser from '@html-eslint/parser';
import eslintConfigPrettier from 'eslint-config-prettier';
import eslintPluginPrettier from 'eslint-plugin-prettier';

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
