/**
 * Setup file for E2E tests
 * 
 * This file configures the environment for running E2E tests with Playwright.
 * It handles browser-specific setup, extension loading, and global test configuration.
 */

import { chromium, firefox, webkit, Browser, BrowserContext } from 'playwright';
import path from 'path';
import fs from 'fs';

// Extend the global Jest types
declare global {
  namespace jest {
    interface Matchers<R> {
      toBeVisible(): R;
      toHaveText(text: string): R;
    }
  }
  
  // Add global variables for browser testing
  var browser: Browser;
  var context: BrowserContext;
  var extensionPath: string;
  var testAppUrl: string;
}

// Define paths to extension build for each browser
const EXTENSION_PATH = path.join(__dirname, '../../build');
const CHROME_EXTENSION_PATH = path.join(EXTENSION_PATH, 'chrome-mv3-prod');
const FIREFOX_EXTENSION_PATH = path.join(EXTENSION_PATH, 'firefox-mv2-prod');

// URL to the test Livewire application
const TEST_APP_URL = process.env.TEST_APP_URL || 'http://localhost:8000';

// Verify extension builds exist
const verifyExtensionBuilds = () => {
  if (!fs.existsSync(CHROME_EXTENSION_PATH)) {
    throw new Error(`Chrome extension build not found at ${CHROME_EXTENSION_PATH}. Please run 'pnpm build' first.`);
  }
  
  if (!fs.existsSync(FIREFOX_EXTENSION_PATH)) {
    throw new Error(`Firefox extension build not found at ${FIREFOX_EXTENSION_PATH}. Please run 'pnpm build:firefox' first.`);
  }
};

// Setup before all tests
beforeAll(async () => {
  // Verify extension builds
  verifyExtensionBuilds();
  
  // Store extension path based on browser
  const browserName = process.env.BROWSER || 'chromium';
  if (browserName === 'chromium') {
    global.extensionPath = CHROME_EXTENSION_PATH;
  } else if (browserName === 'firefox') {
    global.extensionPath = FIREFOX_EXTENSION_PATH;
  } else {
    throw new Error(`Unsupported browser for extension testing: ${browserName}`);
  }
  
  // Set test app URL
  global.testAppUrl = TEST_APP_URL;
  
  // Launch browser with extension
  if (browserName === 'chromium') {
    global.browser = await chromium.launch({
      headless: false, // Extensions require headful mode
      slowMo: process.env.SLOW_MO ? parseInt(process.env.SLOW_MO, 10) : 0,
      args: [
        `--disable-extensions-except=${global.extensionPath}`,
        `--load-extension=${global.extensionPath}`,
        '--no-sandbox'
      ]
    });
    
    // Create context
    global.context = await global.browser.newContext();
  } else if (browserName === 'firefox') {
    // Firefox requires a special way to load extensions
    global.browser = await firefox.launch({
      headless: false,
      slowMo: process.env.SLOW_MO ? parseInt(process.env.SLOW_MO, 10) : 0,
      firefoxUserPrefs: {
        'xpinstall.signatures.required': false
      }
    });
    
    // Create context with extension
    global.context = await global.browser.newContext({
      // Firefox extension loading is different
      viewport: { width: 1280, height: 720 }
    });
    
    // We would need to use web-ext or similar to load Firefox extensions
    // This is a simplified example
  } else {
    throw new Error(`Unsupported browser for extension testing: ${browserName}`);
  }
  
  // Add custom matchers
  expect.extend({
    toBeVisible(received) {
      const pass = received.isVisible();
      return {
        message: () => `expected ${received} ${pass ? 'not ' : ''}to be visible`,
        pass
      };
    },
    toHaveText(received, text) {
      const pass = received.textContent().includes(text);
      return {
        message: () => `expected ${received} ${pass ? 'not ' : ''}to have text ${text}`,
        pass
      };
    }
  });
});

// Teardown after all tests
afterAll(async () => {
  // Close browser
  if (global.browser) {
    await global.browser.close();
  }
});
