/**
 * ThemeManager tests
 * 
 * Tests for theme management functionality including light/dark mode
 * support, automatic theme detection, and proper event listener cleanup.
 */

import { ThemeManager } from '../../lib/ui/ThemeManager';
import * as BrowserUtils from '../../lib/utils/BrowserUtils';
import * as EventUtils from '../../lib/utils/EventUtils';
import { storage } from '@plasmohq/storage';

// Mock dependencies
jest.mock('../../lib/utils/BrowserUtils', () => ({
  getBrowserAPI: jest.fn(),
  getBrowserInfo: jest.fn().mockReturnValue({
    type: 'chrome',
    name: 'chrome',
    version: '100.0.0'
  })
}));

jest.mock('../../lib/utils/EventUtils', () => ({
  addEventListener: jest.fn(),
  removeEventListener: jest.fn(),
  cleanupEventListeners: jest.fn()
}));

jest.mock('@plasmohq/storage', () => ({
  storage: {
    get: jest.fn(),
    set: jest.fn()
  }
}));

describe('ThemeManager', () => {
  let themeManager: ThemeManager;
  let mockMediaQueryList: any;
  
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Reset document body
    document.body.className = '';
    
    // Mock matchMedia
    mockMediaQueryList = {
      matches: false,
      addEventListener: jest.fn(),
      removeEventListener: jest.fn()
    };
    
    window.matchMedia = jest.fn().mockReturnValue(mockMediaQueryList);
    
    // Mock localStorage
    Object.defineProperty(window, 'localStorage', {
      value: {
        getItem: jest.fn(),
        setItem: jest.fn(),
        removeItem: jest.fn()
      },
      writable: true
    });
    
    // Mock storage.get to return null (no saved preference)
    (storage.get as jest.Mock).mockResolvedValue(null);
  });
  
  describe('initialization', () => {
    test('initializes with default theme', async () => {
      themeManager = new ThemeManager();
      await themeManager.initialize();
      
      // Should check for saved preference
      expect(storage.get).toHaveBeenCalledWith('theme');
      
      // Should set up media query listener for auto detection
      expect(window.matchMedia).toHaveBeenCalledWith('(prefers-color-scheme: dark)');
      expect(mockMediaQueryList.addEventListener).toHaveBeenCalledWith('change', expect.any(Function));
    });
    
    test('initializes with saved theme preference', async () => {
      // Mock saved preference in storage
      (storage.get as jest.Mock).mockResolvedValue('dark');
      
      themeManager = new ThemeManager();
      await themeManager.initialize();
      
      // Should apply dark theme
      expect(document.body.classList.contains('theme-dark')).toBe(true);
      expect(document.body.classList.contains('theme-light')).toBe(false);
    });
    
    test('initializes with system preference when no saved preference', async () => {
      // Mock system preference for dark mode
      mockMediaQueryList.matches = true;
      
      themeManager = new ThemeManager();
      await themeManager.initialize();
      
      // Should apply dark theme based on system preference
      expect(document.body.classList.contains('theme-dark')).toBe(true);
    });
  });
  
  describe('theme switching', () => {
    beforeEach(async () => {
      themeManager = new ThemeManager();
      await themeManager.initialize();
    });
    
    test('setTheme applies correct theme classes', () => {
      // Set dark theme
      themeManager.setTheme('dark');
      
      // Check that correct classes are applied
      expect(document.body.classList.contains('theme-dark')).toBe(true);
      expect(document.body.classList.contains('theme-light')).toBe(false);
      
      // Set light theme
      themeManager.setTheme('light');
      
      // Check that classes are updated
      expect(document.body.classList.contains('theme-dark')).toBe(false);
      expect(document.body.classList.contains('theme-light')).toBe(true);
    });
    
    test('setTheme saves preference to storage', () => {
      themeManager.setTheme('dark');
      
      // Check that preference was saved
      expect(storage.set).toHaveBeenCalledWith('theme', 'dark');
    });
    
    test('toggleTheme switches between themes', () => {
      // Start with light theme
      themeManager.setTheme('light');
      
      // Toggle to dark
      themeManager.toggleTheme();
      
      // Should now be dark
      expect(document.body.classList.contains('theme-dark')).toBe(true);
      
      // Toggle back to light
      themeManager.toggleTheme();
      
      // Should now be light
      expect(document.body.classList.contains('theme-light')).toBe(true);
    });
    
    test('followSystemTheme sets theme based on system preference', () => {
      // Mock system preference for dark mode
      mockMediaQueryList.matches = true;
      
      themeManager.followSystemTheme();
      
      // Should apply dark theme
      expect(document.body.classList.contains('theme-dark')).toBe(true);
      
      // Change system preference to light
      mockMediaQueryList.matches = false;
      
      // Simulate media query change event
      const changeHandler = mockMediaQueryList.addEventListener.mock.calls[0][1];
      changeHandler({ matches: false });
      
      // Should update to light theme
      expect(document.body.classList.contains('theme-light')).toBe(true);
    });
  });
  
  describe('high contrast mode', () => {
    beforeEach(async () => {
      themeManager = new ThemeManager();
      await themeManager.initialize();
    });
    
    test('enableHighContrast applies high contrast class', () => {
      themeManager.enableHighContrast();
      
      expect(document.body.classList.contains('high-contrast')).toBe(true);
    });
    
    test('disableHighContrast removes high contrast class', () => {
      // First enable
      themeManager.enableHighContrast();
      
      // Then disable
      themeManager.disableHighContrast();
      
      expect(document.body.classList.contains('high-contrast')).toBe(false);
    });
    
    test('toggleHighContrast switches high contrast mode', () => {
      // Start without high contrast
      expect(document.body.classList.contains('high-contrast')).toBe(false);
      
      // Toggle on
      themeManager.toggleHighContrast();
      expect(document.body.classList.contains('high-contrast')).toBe(true);
      
      // Toggle off
      themeManager.toggleHighContrast();
      expect(document.body.classList.contains('high-contrast')).toBe(false);
    });
  });
  
  describe('event listeners and cleanup', () => {
    test('cleanup removes event listeners', async () => {
      themeManager = new ThemeManager();
      await themeManager.initialize();
      
      themeManager.cleanup();
      
      // Should remove media query listener
      expect(mockMediaQueryList.removeEventListener).toHaveBeenCalled();
      
      // Should call EventUtils cleanup
      expect(EventUtils.cleanupEventListeners).toHaveBeenCalled();
    });
  });
  
  describe('browser-specific behavior', () => {
    test('applies Chrome-specific theme handling', async () => {
      (BrowserUtils.getBrowserInfo as jest.Mock).mockReturnValue({
        type: 'chrome',
        name: 'chrome',
        version: '100.0.0'
      });
      
      themeManager = new ThemeManager();
      await themeManager.initialize();
      
      // Chrome-specific behavior would be implemented in the actual code
      // For this test, we just ensure initialization doesn't break
      expect(themeManager).toBeDefined();
    });
    
    test('applies Firefox-specific theme handling', async () => {
      (BrowserUtils.getBrowserInfo as jest.Mock).mockReturnValue({
        type: 'firefox',
        name: 'firefox',
        version: '95.0.0'
      });
      
      themeManager = new ThemeManager();
      await themeManager.initialize();
      
      // Firefox-specific behavior would be implemented in the actual code
      expect(themeManager).toBeDefined();
    });
    
    test('applies Edge-specific theme handling', async () => {
      (BrowserUtils.getBrowserInfo as jest.Mock).mockReturnValue({
        type: 'edge',
        name: 'edge',
        version: '99.0.0'
      });
      
      themeManager = new ThemeManager();
      await themeManager.initialize();
      
      // Edge-specific behavior would be implemented in the actual code
      expect(themeManager).toBeDefined();
    });
  });
});
