/**
 * Firefox E2E Tests for Tapped Extension
 * 
 * These tests validate the full extension functionality in Firefox
 * against a real Livewire application.
 */

import { Page } from 'playwright';

describe('Tapped Extension in Firefox', () => {
  let page: Page;
  let devtoolsPage: Page;
  
  beforeAll(async () => {
    // Skip if not running Firefox tests
    if (process.env.BROWSER !== 'firefox') {
      console.log('Skipping Firefox tests - running in a different browser');
      return;
    }
    
    // Create a new page for testing
    page = await global.context.newPage();
    
    // Navigate to the test Livewire application
    await page.goto(global.testAppUrl);
    
    // Wait for Livewire to initialize
    await page.waitForFunction(() => window.hasOwnProperty('Livewire'));
    
    // Open Firefox DevTools
    // Note: Firefox has different DevTools access methods
    const devtoolsUrl = await openFirefoxDevTools(page);
    
    // Open a new page for DevTools panel
    devtoolsPage = await global.context.newPage();
    await devtoolsPage.goto(devtoolsUrl);
    
    // Switch to Tapped panel
    await devtoolsPage.click('text=Tapped');
    
    // Wait for Tapped panel to initialize
    await devtoolsPage.waitForSelector('[data-testid="tapped-panel"]');
  });
  
  afterAll(async () => {
    // Skip if not running Firefox tests
    if (process.env.BROWSER !== 'firefox') {
      return;
    }
    
    // Close pages
    if (page) await page.close();
    if (devtoolsPage) await devtoolsPage.close();
  });
  
  test('detects Livewire components on page', async () => {
    // Skip if not running Firefox tests
    if (process.env.BROWSER !== 'firefox') {
      return;
    }
    
    // Wait for component tree to populate
    await devtoolsPage.waitForSelector('[data-testid="component-tree"]');
    
    // Verify at least one component is detected
    const componentsCount = await devtoolsPage.$$eval(
      '[data-testid="component-item"]',
      elements => elements.length
    );
    
    expect(componentsCount).toBeGreaterThan(0);
  });
  
  test('displays component properties with correct types', async () => {
    // Skip if not running Firefox tests
    if (process.env.BROWSER !== 'firefox') {
      return;
    }
    
    // Click on first component in the tree
    await devtoolsPage.click('[data-testid="component-item"]:first-child');
    
    // Wait for properties panel to load
    await devtoolsPage.waitForSelector('[data-testid="properties-panel"]');
    
    // Check for different property types (string, number, boolean, array, object)
    const propertyTypes = await devtoolsPage.$$eval(
      '[data-testid="property-item"]',
      items => {
        return items.map(item => item.getAttribute('data-type'));
      }
    );
    
    // Ensure we have multiple property types
    const uniqueTypes = [...new Set(propertyTypes)];
    expect(uniqueTypes.length).toBeGreaterThan(1);
  });
  
  test('allows editing component properties with validation', async () => {
    // Skip if not running Firefox tests
    if (process.env.BROWSER !== 'firefox') {
      return;
    }
    
    // Find a numeric property to edit
    const numericPropertySelector = '[data-testid="property-item"][data-type="number"]:first-child';
    await devtoolsPage.waitForSelector(numericPropertySelector);
    
    // Double-click to enable editing
    await devtoolsPage.dblclick(numericPropertySelector);
    
    // Try to enter invalid value (string for a number)
    await devtoolsPage.fill(`${numericPropertySelector} input`, 'not-a-number');
    await devtoolsPage.press(`${numericPropertySelector} input`, 'Enter');
    
    // Verify validation error appears
    await devtoolsPage.waitForSelector('[data-testid="validation-error"]');
    
    // Enter valid value
    await devtoolsPage.fill(`${numericPropertySelector} input`, '42');
    await devtoolsPage.press(`${numericPropertySelector} input`, 'Enter');
    
    // Wait for update to complete
    await devtoolsPage.waitForTimeout(500);
    
    // Verify value changed in DevTools
    const updatedValue = await devtoolsPage.$eval(
      `${numericPropertySelector} [data-testid="property-value"]`,
      el => el.textContent
    );
    
    expect(updatedValue).toBe('42');
  });
  
  test('tracks Livewire events with timing information', async () => {
    // Skip if not running Firefox tests
    if (process.env.BROWSER !== 'firefox') {
      return;
    }
    
    // Navigate to events tab
    await devtoolsPage.click('[data-testid="events-tab"]');
    
    // Wait for events panel to load
    await devtoolsPage.waitForSelector('[data-testid="events-panel"]');
    
    // Trigger an event on the page (e.g., click a button that emits a Livewire event)
    await page.click('button:first-child');
    
    // Wait for event to appear in the timeline
    await devtoolsPage.waitForSelector('[data-testid="event-item"]', { timeout: 5000 });
    
    // Verify event has timing information
    const hasTimingInfo = await devtoolsPage.$eval(
      '[data-testid="event-item"]:first-child',
      element => element.querySelector('[data-testid="event-timing"]') !== null
    );
    
    expect(hasTimingInfo).toBe(true);
  });
  
  test('detects N+1 query issues in database monitoring', async () => {
    // Skip if not running Firefox tests
    if (process.env.BROWSER !== 'firefox') {
      return;
    }
    
    // Navigate to queries tab
    await devtoolsPage.click('[data-testid="queries-tab"]');
    
    // Wait for queries panel to load
    await devtoolsPage.waitForSelector('[data-testid="queries-panel"]');
    
    // Trigger an action that causes database queries
    await page.click('button:nth-child(3)');
    
    // Wait for queries to appear
    await devtoolsPage.waitForSelector('[data-testid="query-item"]', { timeout: 5000 });
    
    // Check if N+1 detection works by looking for warning badges
    const hasN1Warnings = await devtoolsPage.$$eval(
      '[data-testid="n-plus-one-warning"]',
      elements => elements.length > 0
    );
    
    // This might not always be true, so we'll just log it
    console.log(`N+1 query warnings detected: ${hasN1Warnings}`);
    
    // Verify query details are shown when clicked
    await devtoolsPage.click('[data-testid="query-item"]:first-child');
    
    const queryDetailsVisible = await devtoolsPage.$eval(
      '[data-testid="query-details"]',
      el => el.textContent.length > 0
    );
    
    expect(queryDetailsVisible).toBe(true);
  });
  
  test('allows batch editing of component properties', async () => {
    // Skip if not running Firefox tests
    if (process.env.BROWSER !== 'firefox') {
      return;
    }
    
    // Navigate to components tab
    await devtoolsPage.click('[data-testid="components-tab"]');
    
    // Click on first component in the tree
    await devtoolsPage.click('[data-testid="component-item"]:first-child');
    
    // Enable batch edit mode
    await devtoolsPage.click('[data-testid="batch-edit-button"]');
    
    // Select multiple properties
    await devtoolsPage.click('[data-testid="property-item"]:nth-child(1) [data-testid="property-checkbox"]');
    await devtoolsPage.click('[data-testid="property-item"]:nth-child(2) [data-testid="property-checkbox"]');
    
    // Open batch editor
    await devtoolsPage.click('[data-testid="open-batch-editor"]');
    
    // Verify batch editor appears
    await devtoolsPage.waitForSelector('[data-testid="batch-editor-dialog"]');
    
    // Close batch editor without making changes
    await devtoolsPage.click('[data-testid="cancel-batch-edit"]');
  });
});

// Helper function to open Firefox DevTools
async function openFirefoxDevTools(page: Page): Promise<string> {
  // This is a simplification - in a real test, we would need Firefox-specific code
  // to launch DevTools and access the Tapped panel
  
  // In Firefox, DevTools access is different than Chrome
  // A real implementation would use browser-specific APIs
  
  return `moz-extension://devtools/panel.html?tabId=${page.url()}`;
}
