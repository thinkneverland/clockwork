/**
 * Chrome E2E Tests for Tapped Extension
 * 
 * These tests validate the full extension functionality in Chrome
 * against a real Livewire application.
 */

import { Page } from 'playwright';

describe('Tapped Extension in Chrome', () => {
  let page: Page;
  let devtoolsPage: Page;
  
  beforeAll(async () => {
    // Create a new page for testing
    page = await global.context.newPage();
    
    // Navigate to the test Livewire application
    await page.goto(global.testAppUrl);
    
    // Wait for Livewire to initialize
    await page.waitForFunction(() => window.hasOwnProperty('Livewire'));
    
    // Open Chrome DevTools
    const devtoolsUrl = await openDevTools(page);
    
    // Open a new page for DevTools panel
    devtoolsPage = await global.context.newPage();
    await devtoolsPage.goto(devtoolsUrl);
    
    // Switch to Tapped panel
    await devtoolsPage.click('text=Tapped');
    
    // Wait for Tapped panel to initialize
    await devtoolsPage.waitForSelector('[data-testid="tapped-panel"]');
  });
  
  afterAll(async () => {
    // Close pages
    if (page) await page.close();
    if (devtoolsPage) await devtoolsPage.close();
  });
  
  test('detects Livewire components on page', async () => {
    // Wait for component tree to populate
    await devtoolsPage.waitForSelector('[data-testid="component-tree"]');
    
    // Verify at least one component is detected
    const componentsCount = await devtoolsPage.$$eval(
      '[data-testid="component-item"]',
      elements => elements.length
    );
    
    expect(componentsCount).toBeGreaterThan(0);
  });
  
  test('displays component properties', async () => {
    // Click on first component in the tree
    await devtoolsPage.click('[data-testid="component-item"]:first-child');
    
    // Wait for properties panel to load
    await devtoolsPage.waitForSelector('[data-testid="properties-panel"]');
    
    // Verify properties are shown
    const propertiesCount = await devtoolsPage.$$eval(
      '[data-testid="property-item"]',
      elements => elements.length
    );
    
    expect(propertiesCount).toBeGreaterThan(0);
  });
  
  test('allows editing component properties', async () => {
    // Find a string property to edit
    const stringPropertySelector = '[data-testid="property-item"][data-type="string"]:first-child';
    await devtoolsPage.waitForSelector(stringPropertySelector);
    
    // Double-click to enable editing
    await devtoolsPage.dblclick(stringPropertySelector);
    
    // Get current value
    const originalValue = await devtoolsPage.$eval(
      `${stringPropertySelector} [data-testid="property-value"]`,
      el => el.textContent
    );
    
    // Enter new value
    const newValue = 'E2E Test Value';
    await devtoolsPage.fill(`${stringPropertySelector} input`, newValue);
    await devtoolsPage.press(`${stringPropertySelector} input`, 'Enter');
    
    // Wait for update to complete
    await devtoolsPage.waitForTimeout(500);
    
    // Verify value changed in DevTools
    const updatedValue = await devtoolsPage.$eval(
      `${stringPropertySelector} [data-testid="property-value"]`,
      el => el.textContent
    );
    
    expect(updatedValue).toBe(newValue);
    
    // Verify the change affected the page
    // This assumes the property is rendered in the UI
    const pageContainsValue = await page.evaluate((val) => {
      return document.body.textContent.includes(val);
    }, newValue);
    
    expect(pageContainsValue).toBe(true);
  });
  
  test('tracks Livewire events', async () => {
    // Navigate to events tab
    await devtoolsPage.click('[data-testid="events-tab"]');
    
    // Wait for events panel to load
    await devtoolsPage.waitForSelector('[data-testid="events-panel"]');
    
    // Trigger an event on the page (e.g., click a button that emits a Livewire event)
    await page.click('button:first-child');
    
    // Wait for event to appear in the timeline
    await devtoolsPage.waitForSelector('[data-testid="event-item"]', { timeout: 5000 });
    
    // Verify event was captured
    const eventsCount = await devtoolsPage.$$eval(
      '[data-testid="event-item"]',
      elements => elements.length
    );
    
    expect(eventsCount).toBeGreaterThan(0);
  });
  
  test('monitors AJAX requests', async () => {
    // Navigate to requests tab
    await devtoolsPage.click('[data-testid="requests-tab"]');
    
    // Wait for requests panel to load
    await devtoolsPage.waitForSelector('[data-testid="requests-panel"]');
    
    // Trigger a Livewire action that causes an AJAX request
    await page.click('button:nth-child(2)');
    
    // Wait for request to appear
    await devtoolsPage.waitForSelector('[data-testid="request-item"]', { timeout: 5000 });
    
    // Verify request was captured
    const requestsCount = await devtoolsPage.$$eval(
      '[data-testid="request-item"]',
      elements => elements.length
    );
    
    expect(requestsCount).toBeGreaterThan(0);
    
    // Click on a request to see details
    await devtoolsPage.click('[data-testid="request-item"]:first-child');
    
    // Verify request details are shown
    await devtoolsPage.waitForSelector('[data-testid="request-details"]');
    
    const hasRequestDetails = await devtoolsPage.$eval(
      '[data-testid="request-details"]',
      el => el.textContent.length > 0
    );
    
    expect(hasRequestDetails).toBe(true);
  });
  
  test('captures state snapshots for time-travel debugging', async () => {
    // Navigate to snapshots tab
    await devtoolsPage.click('[data-testid="snapshots-tab"]');
    
    // Wait for snapshots panel to load
    await devtoolsPage.waitForSelector('[data-testid="snapshots-panel"]');
    
    // Create snapshot
    await devtoolsPage.click('[data-testid="create-snapshot"]');
    
    // Wait for snapshot to appear
    await devtoolsPage.waitForSelector('[data-testid="snapshot-item"]');
    
    // Modify component state
    // Navigate back to components
    await devtoolsPage.click('[data-testid="components-tab"]');
    
    // Find a numeric property to edit
    const numericPropertySelector = '[data-testid="property-item"][data-type="number"]:first-child';
    await devtoolsPage.waitForSelector(numericPropertySelector);
    
    // Double-click to enable editing
    await devtoolsPage.dblclick(numericPropertySelector);
    
    // Get current value
    const originalValue = await devtoolsPage.$eval(
      `${numericPropertySelector} [data-testid="property-value"]`,
      el => el.textContent
    );
    
    // Enter new value
    const newValue = '999';
    await devtoolsPage.fill(`${numericPropertySelector} input`, newValue);
    await devtoolsPage.press(`${numericPropertySelector} input`, 'Enter');
    
    // Navigate back to snapshots
    await devtoolsPage.click('[data-testid="snapshots-tab"]');
    
    // Restore previous snapshot
    await devtoolsPage.click('[data-testid="snapshot-item"]:first-child');
    await devtoolsPage.click('[data-testid="restore-snapshot"]');
    
    // Navigate back to components to verify restoration
    await devtoolsPage.click('[data-testid="components-tab"]');
    
    // Verify value was restored
    await devtoolsPage.waitForSelector(numericPropertySelector);
    const restoredValue = await devtoolsPage.$eval(
      `${numericPropertySelector} [data-testid="property-value"]`,
      el => el.textContent
    );
    
    expect(restoredValue).toBe(originalValue);
  });
});

// Helper function to open Chrome DevTools
async function openDevTools(page: Page): Promise<string> {
  // This is a simplification - in a real test, we would need browser-specific code
  // to launch DevTools and access the Tapped panel
  
  // For now, we'll return a mock URL that could be used in the test environment
  // A real implementation would use CDP (Chrome DevTools Protocol) to get the actual URL
  
  // In a real implementation, we would:
  // 1. Use CDP to open DevTools
  // 2. Get the DevTools URL
  // 3. Navigate to it in another tab
  
  return `chrome-devtools://devtools/bundled/devtools_app.html?panel=tapped&id=${page.url()}`;
}
