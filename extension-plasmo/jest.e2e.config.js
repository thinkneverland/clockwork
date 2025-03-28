/**
 * Jest E2E Configuration for Tapped Extension
 * 
 * This configuration is specifically for running end-to-end tests
 * with real browsers via Playwright.
 */

module.exports = {
  preset: 'jest-playwright-preset',
  testMatch: ['**/tests/e2e/**/*.e2e.ts'],
  transform: {
    '^.+\\.tsx?$': ['ts-jest', {
      tsconfig: 'tsconfig.json',
    }],
  },
  testEnvironment: 'node',
  setupFilesAfterEnv: ['./tests/e2e/setup.ts'],
  testTimeout: 120000, // 2 minutes timeout for E2E tests
  globals: {
    'ts-jest': {
      isolatedModules: true,
    },
  },
  reporters: [
    'default',
    ['jest-html-reporters', {
      publicPath: './coverage/e2e',
      filename: 'e2e-report.html',
    }]
  ],
};
