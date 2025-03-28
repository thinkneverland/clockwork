/**
 * ThemeManager - Cross-browser compatible theme management
 * 
 * Features:
 * - Detects system theme preferences
 * - Provides theme toggling capability
 * - Persists theme preferences across sessions
 * - Ensures proper event listener cleanup
 * - Uses browser abstraction layer for cross-browser compatibility
 */

import { Storage } from "@plasmohq/storage";
import { EventEmitter } from "./EventUtils";

export enum Theme {
  LIGHT = "light",
  DARK = "dark",
  SYSTEM = "system"
}

interface ThemeOptions {
  defaultTheme?: Theme;
  storageKey?: string;
  darkClass?: string;
  lightClass?: string;
  targetElement?: HTMLElement | null;
}

export class ThemeManager extends EventEmitter {
  private currentTheme: Theme;
  private defaultTheme: Theme;
  private storage: Storage;
  private storageKey: string;
  private darkClass: string;
  private lightClass: string;
  private targetElement: HTMLElement;
  private systemThemeMediaQuery: MediaQueryList | null = null;
  private browserType: string;

  /**
   * Creates a new ThemeManager instance
   */
  constructor(options: ThemeOptions = {}) {
    super();
    
    // Initialize with defaults
    this.defaultTheme = options.defaultTheme || Theme.SYSTEM;
    this.storageKey = options.storageKey || "tapped-theme-preference";
    this.darkClass = options.darkClass || "dark-theme";
    this.lightClass = options.lightClass || "light-theme";
    this.targetElement = options.targetElement || document.documentElement;
    this.currentTheme = this.defaultTheme;
    this.storage = new Storage();
    this.browserType = this.detectBrowser();
    
    // Initialize
    this.initialize();
  }
  
  /**
   * Initialize the theme manager
   */
  private async initialize() {
    // Set up system theme detection
    this.setupSystemThemeDetection();
    
    // Load saved theme preference
    await this.loadThemePreference();
    
    // Apply the current theme
    this.applyTheme();
  }
  
  /**
   * Set up system theme detection
   */
  private setupSystemThemeDetection() {
    // Create media query for prefers-color-scheme
    this.systemThemeMediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
    
    // Add event listener for theme changes
    const systemThemeChangeHandler = (e: MediaQueryListEvent) => {
      if (this.currentTheme === Theme.SYSTEM) {
        this.applyTheme();
        this.emit("theme:changed", { theme: this.getEffectiveTheme() });
      }
    };
    
    // Add event listener with browser compatibility considerations
    if (this.systemThemeMediaQuery.addEventListener) {
      this.systemThemeMediaQuery.addEventListener("change", systemThemeChangeHandler);
    } else if ("addListener" in this.systemThemeMediaQuery) {
      // Older browsers (mainly Safari) only support addListener
      // @ts-ignore - addListener exists in older browsers but not in newer types
      this.systemThemeMediaQuery.addListener(systemThemeChangeHandler);
    }
  }
  
  /**
   * Load saved theme preference from storage
   */
  private async loadThemePreference() {
    try {
      const savedTheme = await this.storage.get(this.storageKey) as Theme | undefined;
      
      if (savedTheme && Object.values(Theme).includes(savedTheme as Theme)) {
        this.currentTheme = savedTheme as Theme;
      } else {
        this.currentTheme = this.defaultTheme;
      }
    } catch (error) {
      console.error("Error loading theme preference:", error);
      this.currentTheme = this.defaultTheme;
    }
  }
  
  /**
   * Save current theme preference to storage
   */
  private async saveThemePreference() {
    try {
      await this.storage.set(this.storageKey, this.currentTheme);
    } catch (error) {
      console.error("Error saving theme preference:", error);
    }
  }
  
  /**
   * Apply the current theme to the target element
   */
  public applyTheme() {
    const effectiveTheme = this.getEffectiveTheme();
    
    // Apply theme class to target element
    if (effectiveTheme === Theme.DARK) {
      this.targetElement.classList.add(this.darkClass);
      this.targetElement.classList.remove(this.lightClass);
      
      // Set CSS variables for theming
      this.targetElement.style.setProperty("--tapped-bg-color", "#1e1e1e");
      this.targetElement.style.setProperty("--tapped-text-color", "#f5f6fa");
      this.targetElement.style.setProperty("--tapped-border-color", "#444");
      this.targetElement.style.setProperty("--tapped-primary-color", "#00a8ff");
      this.targetElement.style.setProperty("--tapped-secondary-color", "#8c8c8c");
      this.targetElement.style.setProperty("--tapped-success-color", "#4cd137");
      this.targetElement.style.setProperty("--tapped-warning-color", "#e1b12c");
      this.targetElement.style.setProperty("--tapped-error-color", "#e84118");
      this.targetElement.style.setProperty("--tapped-header-height", "50px");
    } else {
      this.targetElement.classList.add(this.lightClass);
      this.targetElement.classList.remove(this.darkClass);
      
      // Set CSS variables for theming
      this.targetElement.style.setProperty("--tapped-bg-color", "#ffffff");
      this.targetElement.style.setProperty("--tapped-text-color", "#2f3640");
      this.targetElement.style.setProperty("--tapped-border-color", "#dcdde1");
      this.targetElement.style.setProperty("--tapped-primary-color", "#00a8ff");
      this.targetElement.style.setProperty("--tapped-secondary-color", "#8c8c8c");
      this.targetElement.style.setProperty("--tapped-success-color", "#4cd137");
      this.targetElement.style.setProperty("--tapped-warning-color", "#e1b12c");
      this.targetElement.style.setProperty("--tapped-error-color", "#e84118");
      this.targetElement.style.setProperty("--tapped-header-height", "50px");
    }
  }
  
  /**
   * Get the effective theme based on current theme setting and system preference
   */
  public getEffectiveTheme(): Theme {
    if (this.currentTheme === Theme.SYSTEM) {
      return this.systemThemeMediaQuery?.matches ? Theme.DARK : Theme.LIGHT;
    }
    
    return this.currentTheme;
  }
  
  /**
   * Set the current theme
   */
  public async setTheme(theme: Theme) {
    if (!Object.values(Theme).includes(theme)) {
      throw new Error(`Invalid theme: ${theme}`);
    }
    
    this.currentTheme = theme;
    this.applyTheme();
    await this.saveThemePreference();
    
    this.emit("theme:changed", { theme: this.getEffectiveTheme() });
  }
  
  /**
   * Toggle between light and dark themes
   */
  public async toggleTheme() {
    const currentEffective = this.getEffectiveTheme();
    const newTheme = currentEffective === Theme.DARK ? Theme.LIGHT : Theme.DARK;
    
    await this.setTheme(newTheme);
    return newTheme;
  }
  
  /**
   * Get the current theme preference
   */
  public getCurrentTheme(): Theme {
    return this.currentTheme;
  }
  
  /**
   * Detect the current browser
   */
  private detectBrowser(): string {
    const userAgent = navigator.userAgent;
    
    if (userAgent.indexOf('Chrome') > -1) {
      return 'chrome';
    } else if (userAgent.indexOf('Firefox') > -1) {
      return 'firefox';
    } else if (userAgent.indexOf('Edge') > -1) {
      return 'edge';
    } else if (userAgent.indexOf('Safari') > -1) {
      return 'safari';
    } else {
      return 'unknown';
    }
  }
  
  /**
   * Get browser-specific information
   */
  public getBrowserInfo() {
    return {
      type: this.browserType,
      isDark: this.getEffectiveTheme() === Theme.DARK,
      supportsModernMediaQueries: Boolean(this.systemThemeMediaQuery?.addEventListener),
      systemPrefersDark: this.systemThemeMediaQuery?.matches || false
    };
  }
  
  /**
   * Clean up resources
   */
  public cleanup() {
    // Remove system theme event listener with browser compatibility
    if (this.systemThemeMediaQuery) {
      if (this.systemThemeMediaQuery.removeEventListener) {
        this.systemThemeMediaQuery.removeEventListener("change", () => {});
      } else if ("removeListener" in this.systemThemeMediaQuery) {
        // Older browsers (mainly Safari) only support removeListener
        // @ts-ignore - removeListener exists in older browsers but not in newer types
        this.systemThemeMediaQuery.removeListener(() => {});
      }
    }
    
    // Remove all event listeners
    this.removeAllEventListeners();
  }
}

export default ThemeManager;
