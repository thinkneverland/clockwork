#!/usr/bin/env node

/**
 * E2E Test Runner for Tapped Extension
 * 
 * This script orchestrates the setup and execution of end-to-end tests
 * for the Tapped browser extension across multiple browsers.
 */

const { spawn, execSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const chalk = require('chalk');

// Configuration
const browsers = ['chromium', 'firefox'];
const testAppPort = 8000;
const testTimeout = 300000; // 5 minutes timeout

// Paths
const rootDir = path.resolve(__dirname, '../..');
const buildDir = path.join(rootDir, 'build');
const e2eDir = path.join(rootDir, 'tests/e2e');
const testAppDir = path.join(e2eDir, 'test-app');

// Check if extension is built
function checkExtensionBuilds() {
  console.log(chalk.blue('Checking extension builds...'));
  
  const chromeExtPath = path.join(buildDir, 'chrome-mv3-prod');
  const firefoxExtPath = path.join(buildDir, 'firefox-mv2-prod');
  
  if (!fs.existsSync(chromeExtPath)) {
    console.log(chalk.yellow('Chrome extension build not found. Building now...'));
    execSync('pnpm run build', { cwd: rootDir, stdio: 'inherit' });
  }
  
  if (!fs.existsSync(firefoxExtPath)) {
    console.log(chalk.yellow('Firefox extension build not found. Building now...'));
    execSync('pnpm run build:firefox', { cwd: rootDir, stdio: 'inherit' });
  }
  
  console.log(chalk.green('✓ Extension builds verified'));
}

// Check if test app is running
async function checkTestApp() {
  console.log(chalk.blue('Checking if test app is running...'));
  
  try {
    const response = await fetch(`http://localhost:${testAppPort}`);
    if (response.ok) {
      console.log(chalk.green('✓ Test app is running'));
      return true;
    }
  } catch (error) {
    console.log(chalk.yellow('Test app is not running. Starting now...'));
    return false;
  }
}

// Start test app
function startTestApp() {
  console.log(chalk.blue(`Starting test app on port ${testAppPort}...`));
  
  const testApp = spawn('php', ['artisan', 'serve', `--port=${testAppPort}`], {
    cwd: testAppDir,
    stdio: 'pipe',
    detached: true
  });
  
  // Log output
  testApp.stdout.on('data', (data) => {
    console.log(chalk.dim(`[Test App] ${data.toString().trim()}`));
  });
  
  testApp.stderr.on('data', (data) => {
    console.error(chalk.red(`[Test App Error] ${data.toString().trim()}`));
  });
  
  // Wait for app to start
  return new Promise((resolve) => {
    setTimeout(() => {
      console.log(chalk.green('✓ Test app started'));
      resolve(testApp);
    }, 5000);
  });
}

// Run E2E tests for a specific browser
function runBrowserTests(browser) {
  console.log(chalk.blue(`Running E2E tests for ${browser}...`));
  
  return new Promise((resolve, reject) => {
    const testProcess = spawn('jest', [
      '--config', 'jest.e2e.config.js',
      `--testMatch`, `**/tests/e2e/${browser.charAt(0).toUpperCase() + browser.slice(1)}*.e2e.ts`
    ], {
      cwd: rootDir,
      stdio: 'inherit',
      env: {
        ...process.env,
        BROWSER: browser,
        TEST_APP_URL: `http://localhost:${testAppPort}`
      }
    });
    
    testProcess.on('close', (code) => {
      if (code === 0) {
        console.log(chalk.green(`✓ ${browser} tests completed successfully`));
        resolve();
      } else {
        console.error(chalk.red(`✗ ${browser} tests failed with code ${code}`));
        resolve(); // Continue with other browsers even if one fails
      }
    });
    
    testProcess.on('error', (error) => {
      console.error(chalk.red(`Error running ${browser} tests: ${error.message}`));
      resolve(); // Continue with other browsers
    });
  });
}

// Main function
async function main() {
  console.log(chalk.bold.blue('Starting Tapped E2E Test Suite'));
  console.log(chalk.dim('=============================='));
  
  // Check extension builds
  checkExtensionBuilds();
  
  // Check and start test app if needed
  const isTestAppRunning = await checkTestApp();
  let testApp;
  if (!isTestAppRunning) {
    testApp = await startTestApp();
  }
  
  // Run tests for each browser
  console.log(chalk.bold.blue('Running tests across browsers'));
  console.log(chalk.dim('=============================='));
  
  let hasFailures = false;
  for (const browser of browsers) {
    try {
      await runBrowserTests(browser);
    } catch (error) {
      console.error(chalk.red(`Error running tests for ${browser}: ${error.message}`));
      hasFailures = true;
    }
  }
  
  // Cleanup
  if (testApp) {
    console.log(chalk.blue('Shutting down test app...'));
    // On Windows use different approach
    process.platform === 'win32' ? process.kill(testApp.pid) : process.kill(-testApp.pid);
  }
  
  console.log(chalk.bold.blue('E2E Test Suite Complete'));
  console.log(chalk.dim('=============================='));
  
  // Generate report path
  const reportPath = path.join(rootDir, 'coverage/e2e/e2e-report.html');
  if (fs.existsSync(reportPath)) {
    console.log(chalk.blue(`E2E test report available at: ${reportPath}`));
  }
  
  // Exit with appropriate code
  process.exit(hasFailures ? 1 : 0);
}

// Run the script
main().catch(error => {
  console.error(chalk.red(`Fatal error: ${error.message}`));
  process.exit(1);
});
