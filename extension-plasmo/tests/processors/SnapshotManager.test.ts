/**
 * SnapshotManager tests
 * 
 * Tests for the utility that manages component state snapshots
 * for time-travel debugging functionality.
 */

import { SnapshotManager } from '../../lib/processors/SnapshotManager';
import { storage } from '@plasmohq/storage';

// Mock storage
jest.mock('@plasmohq/storage', () => ({
  storage: {
    get: jest.fn(),
    set: jest.fn(),
    remove: jest.fn(),
    getAll: jest.fn()
  }
}));

describe('SnapshotManager', () => {
  let snapshotManager: SnapshotManager;
  
  // Sample component data for testing
  const mockComponent = {
    id: 'comp-123',
    name: 'Counter',
    fingerprint: 'abc123',
    state: {
      count: 5,
      title: 'Counter Component',
      items: ['Item 1', 'Item 2']
    }
  };
  
  // Mock snapshots for testing
  const mockSnapshots = [
    {
      id: 'snap-1',
      componentId: 'comp-123',
      name: 'Initial State',
      timestamp: Date.now() - 5000,
      state: {
        count: 0,
        title: 'Counter Component',
        items: ['Item 1']
      },
      isAutoSnapshot: false
    },
    {
      id: 'snap-2',
      componentId: 'comp-123',
      name: 'After Increment',
      timestamp: Date.now() - 3000,
      state: {
        count: 1,
        title: 'Counter Component',
        items: ['Item 1']
      },
      isAutoSnapshot: false
    },
    {
      id: 'snap-3',
      componentId: 'comp-123',
      name: 'Auto Snapshot',
      timestamp: Date.now() - 1000,
      state: {
        count: 5,
        title: 'Counter Component',
        items: ['Item 1', 'Item 2']
      },
      isAutoSnapshot: true
    }
  ];
  
  beforeEach(() => {
    jest.clearAllMocks();
    
    // Setup storage mock
    storage.get.mockImplementation(async (key) => {
      if (key === 'snapshots') {
        return mockSnapshots;
      }
      if (key === 'snapshotSettings') {
        return {
          autoSnapshotEnabled: true,
          autoSnapshotInterval: 5000,
          maxSnapshots: 20
        };
      }
      return null;
    });
    
    storage.getAll.mockResolvedValue({
      snapshots: mockSnapshots,
      snapshotSettings: {
        autoSnapshotEnabled: true,
        autoSnapshotInterval: 5000,
        maxSnapshots: 20
      }
    });
    
    snapshotManager = new SnapshotManager();
  });
  
  describe('initialization', () => {
    test('loads existing snapshots from storage', async () => {
      await snapshotManager.initialize();
      
      expect(storage.get).toHaveBeenCalledWith('snapshots');
      
      const snapshots = await snapshotManager.getSnapshots();
      expect(snapshots).toEqual(mockSnapshots);
    });
    
    test('loads snapshot settings from storage', async () => {
      await snapshotManager.initialize();
      
      expect(storage.get).toHaveBeenCalledWith('snapshotSettings');
      
      const settings = await snapshotManager.getSettings();
      expect(settings).toEqual({
        autoSnapshotEnabled: true,
        autoSnapshotInterval: 5000,
        maxSnapshots: 20
      });
    });
    
    test('creates default settings if none exist', async () => {
      // Mock storage.get to return null for settings
      storage.get.mockImplementation(async (key) => {
        if (key === 'snapshots') {
          return mockSnapshots;
        }
        if (key === 'snapshotSettings') {
          return null;
        }
        return null;
      });
      
      await snapshotManager.initialize();
      
      // Should have saved default settings
      expect(storage.set).toHaveBeenCalledWith(
        'snapshotSettings',
        expect.objectContaining({
          autoSnapshotEnabled: true,
          autoSnapshotInterval: expect.any(Number),
          maxSnapshots: expect.any(Number)
        })
      );
    });
  });
  
  describe('snapshot creation', () => {
    test('creates manual snapshot of component state', async () => {
      await snapshotManager.initialize();
      
      const snapshotName = 'Test Snapshot';
      const snapshot = await snapshotManager.createSnapshot(mockComponent, snapshotName);
      
      // Snapshot should have correct properties
      expect(snapshot).toEqual({
        id: expect.any(String),
        componentId: mockComponent.id,
        name: snapshotName,
        timestamp: expect.any(Number),
        state: mockComponent.state,
        isAutoSnapshot: false
      });
      
      // Should save to storage
      expect(storage.set).toHaveBeenCalledWith('snapshots', expect.any(Array));
    });
    
    test('creates auto snapshot during automatic capture', async () => {
      await snapshotManager.initialize();
      
      const snapshot = await snapshotManager.createAutoSnapshot(mockComponent);
      
      // Snapshot should have correct properties
      expect(snapshot).toEqual({
        id: expect.any(String),
        componentId: mockComponent.id,
        name: expect.stringContaining('Auto Snapshot'),
        timestamp: expect.any(Number),
        state: mockComponent.state,
        isAutoSnapshot: true
      });
      
      // Should save to storage
      expect(storage.set).toHaveBeenCalledWith('snapshots', expect.any(Array));
    });
    
    test('enforces maximum snapshot limit', async () => {
      // Mock having reached the max snapshots
      const maxSnapshots = 3;
      const oldSnapshots = Array(maxSnapshots).fill(0).map((_, i) => ({
        id: `existing-${i}`,
        componentId: mockComponent.id,
        name: `Existing ${i}`,
        timestamp: Date.now() - (10000 - i * 1000), // Older to newer
        state: { ...mockComponent.state, count: i },
        isAutoSnapshot: true
      }));
      
      storage.get.mockImplementation(async (key) => {
        if (key === 'snapshots') {
          return oldSnapshots;
        }
        if (key === 'snapshotSettings') {
          return {
            autoSnapshotEnabled: true,
            autoSnapshotInterval: 5000,
            maxSnapshots: maxSnapshots
          };
        }
        return null;
      });
      
      await snapshotManager.initialize();
      
      // Create a new snapshot
      await snapshotManager.createSnapshot(mockComponent, 'New Snapshot');
      
      // Should have removed the oldest snapshot
      const savedSnapshots = (storage.set.mock.calls[0][1] as any[]);
      expect(savedSnapshots.length).toBe(maxSnapshots);
      expect(savedSnapshots.find(s => s.id === 'existing-0')).toBeUndefined();
      expect(savedSnapshots.find(s => s.name === 'New Snapshot')).toBeDefined();
    });
  });
  
  describe('snapshot retrieval', () => {
    test('gets all snapshots', async () => {
      await snapshotManager.initialize();
      
      const snapshots = await snapshotManager.getSnapshots();
      expect(snapshots).toEqual(mockSnapshots);
    });
    
    test('gets snapshots for specific component', async () => {
      await snapshotManager.initialize();
      
      const componentSnapshots = await snapshotManager.getComponentSnapshots('comp-123');
      expect(componentSnapshots).toEqual(mockSnapshots);
      
      // No snapshots for other component
      const emptySnapshots = await snapshotManager.getComponentSnapshots('comp-456');
      expect(emptySnapshots).toEqual([]);
    });
    
    test('gets snapshot by ID', async () => {
      await snapshotManager.initialize();
      
      const snapshot = await snapshotManager.getSnapshot('snap-2');
      expect(snapshot).toEqual(mockSnapshots[1]);
      
      // Invalid ID returns null
      const nonExistentSnapshot = await snapshotManager.getSnapshot('non-existent');
      expect(nonExistentSnapshot).toBeNull();
    });
  });
  
  describe('snapshot management', () => {
    test('deletes individual snapshot', async () => {
      await snapshotManager.initialize();
      
      await snapshotManager.deleteSnapshot('snap-2');
      
      // Should have removed the snapshot
      const savedSnapshots = (storage.set.mock.calls[0][1] as any[]);
      expect(savedSnapshots.length).toBe(2);
      expect(savedSnapshots.find(s => s.id === 'snap-2')).toBeUndefined();
    });
    
    test('deletes all snapshots for component', async () => {
      await snapshotManager.initialize();
      
      await snapshotManager.deleteComponentSnapshots('comp-123');
      
      // Should have removed all snapshots for the component
      const savedSnapshots = (storage.set.mock.calls[0][1] as any[]);
      expect(savedSnapshots.length).toBe(0);
    });
    
    test('deletes all auto snapshots for component', async () => {
      await snapshotManager.initialize();
      
      await snapshotManager.deleteAutoSnapshots('comp-123');
      
      // Should have removed only auto snapshots
      const savedSnapshots = (storage.set.mock.calls[0][1] as any[]);
      expect(savedSnapshots.length).toBe(2);
      expect(savedSnapshots.find(s => s.isAutoSnapshot)).toBeUndefined();
    });
    
    test('renames existing snapshot', async () => {
      await snapshotManager.initialize();
      
      const newName = 'Renamed Snapshot';
      await snapshotManager.renameSnapshot('snap-1', newName);
      
      // Should have updated the snapshot name
      const savedSnapshots = (storage.set.mock.calls[0][1] as any[]);
      const updatedSnapshot = savedSnapshots.find(s => s.id === 'snap-1');
      expect(updatedSnapshot.name).toBe(newName);
    });
  });
  
  describe('snapshot comparison', () => {
    test('compares snapshot states to find differences', async () => {
      await snapshotManager.initialize();
      
      const diff = await snapshotManager.compareSnapshots('snap-1', 'snap-3');
      
      // Should identify differences
      expect(diff).toEqual({
        count: { before: 0, after: 5 },
        items: { before: ['Item 1'], after: ['Item 1', 'Item 2'] }
      });
    });
    
    test('compares snapshot with current component state', async () => {
      await snapshotManager.initialize();
      
      const currentState = {
        count: 10,
        title: 'Counter Component',
        items: ['Item 1', 'Item 2', 'Item 3']
      };
      
      const diff = await snapshotManager.compareWithCurrentState('snap-1', currentState);
      
      // Should identify differences
      expect(diff).toEqual({
        count: { before: 0, after: 10 },
        items: { before: ['Item 1'], after: ['Item 1', 'Item 2', 'Item 3'] }
      });
    });
    
    test('handles comparison of complex nested objects', async () => {
      await snapshotManager.initialize();
      
      // Create complex states with nested objects
      const state1 = {
        user: {
          profile: {
            name: 'John',
            settings: {
              theme: 'light',
              notifications: true
            }
          },
          permissions: ['read', 'write']
        },
        stats: {
          count: 5,
          lastUpdated: '2023-01-01'
        }
      };
      
      const state2 = {
        user: {
          profile: {
            name: 'John',
            settings: {
              theme: 'dark',
              notifications: true
            }
          },
          permissions: ['read', 'write', 'admin']
        },
        stats: {
          count: 10,
          lastUpdated: '2023-01-02'
        }
      };
      
      // Mock snapshots with these complex states
      const complexSnapshots = [
        {
          id: 'complex-1',
          componentId: 'comp-456',
          name: 'Complex State 1',
          timestamp: Date.now() - 5000,
          state: state1,
          isAutoSnapshot: false
        },
        {
          id: 'complex-2',
          componentId: 'comp-456',
          name: 'Complex State 2',
          timestamp: Date.now() - 1000,
          state: state2,
          isAutoSnapshot: false
        }
      ];
      
      // Mock storage to return complex snapshots
      storage.get.mockImplementation(async (key) => {
        if (key === 'snapshots') {
          return complexSnapshots;
        }
        if (key === 'snapshotSettings') {
          return {
            autoSnapshotEnabled: true,
            autoSnapshotInterval: 5000,
            maxSnapshots: 20
          };
        }
        return null;
      });
      
      await snapshotManager.initialize();
      
      const diff = await snapshotManager.compareSnapshots('complex-1', 'complex-2');
      
      // Should identify nested differences
      expect(diff).toEqual({
        'user.profile.settings.theme': { before: 'light', after: 'dark' },
        'user.permissions': { before: ['read', 'write'], after: ['read', 'write', 'admin'] },
        'stats.count': { before: 5, after: 10 },
        'stats.lastUpdated': { before: '2023-01-01', after: '2023-01-02' }
      });
    });
  });
  
  describe('settings management', () => {
    test('updates snapshot settings', async () => {
      await snapshotManager.initialize();
      
      const newSettings = {
        autoSnapshotEnabled: false,
        autoSnapshotInterval: 10000,
        maxSnapshots: 50
      };
      
      await snapshotManager.updateSettings(newSettings);
      
      // Should save new settings
      expect(storage.set).toHaveBeenCalledWith('snapshotSettings', newSettings);
      
      // Settings should be updated
      const settings = await snapshotManager.getSettings();
      expect(settings).toEqual(newSettings);
    });
    
    test('toggles auto-snapshot feature', async () => {
      await snapshotManager.initialize();
      
      await snapshotManager.toggleAutoSnapshot(false);
      
      // Should update settings
      expect(storage.set).toHaveBeenCalledWith(
        'snapshotSettings',
        expect.objectContaining({
          autoSnapshotEnabled: false
        })
      );
      
      // Toggle back on
      await snapshotManager.toggleAutoSnapshot(true);
      
      // Should update settings
      expect(storage.set).toHaveBeenCalledWith(
        'snapshotSettings',
        expect.objectContaining({
          autoSnapshotEnabled: true
        })
      );
    });
  });
  
  describe('automatic snapshot scheduling', () => {
    test('starts auto-snapshot interval when enabled', async () => {
      // Mock setInterval
      jest.useFakeTimers();
      
      await snapshotManager.initialize();
      
      // Start auto-snapshotting
      const mockCallback = jest.fn();
      await snapshotManager.startAutoSnapshots(mockCallback);
      
      // Should set an interval
      expect(setInterval).toHaveBeenCalledWith(expect.any(Function), 5000);
      
      // Simulate interval firing
      jest.runOnlyPendingTimers();
      
      // Callback should be called
      expect(mockCallback).toHaveBeenCalled();
      
      // Clean up
      jest.useRealTimers();
    });
    
    test('does not start auto-snapshot when disabled', async () => {
      // Mock setInterval
      jest.useFakeTimers();
      
      // Mock settings with auto-snapshot disabled
      storage.get.mockImplementation(async (key) => {
        if (key === 'snapshots') {
          return mockSnapshots;
        }
        if (key === 'snapshotSettings') {
          return {
            autoSnapshotEnabled: false,
            autoSnapshotInterval: 5000,
            maxSnapshots: 20
          };
        }
        return null;
      });
      
      await snapshotManager.initialize();
      
      // Try to start auto-snapshotting
      const mockCallback = jest.fn();
      await snapshotManager.startAutoSnapshots(mockCallback);
      
      // Should not set an interval
      expect(setInterval).not.toHaveBeenCalled();
      
      // Clean up
      jest.useRealTimers();
    });
    
    test('stops auto-snapshot interval', async () => {
      // Mock setInterval and clearInterval
      jest.useFakeTimers();
      
      await snapshotManager.initialize();
      
      // Start auto-snapshotting
      const mockCallback = jest.fn();
      await snapshotManager.startAutoSnapshots(mockCallback);
      
      // Should set an interval
      expect(setInterval).toHaveBeenCalled();
      
      // Stop auto-snapshotting
      await snapshotManager.stopAutoSnapshots();
      
      // Should clear the interval
      expect(clearInterval).toHaveBeenCalled();
      
      // Clean up
      jest.useRealTimers();
    });
  });
  
  describe('snapshot export/import', () => {
    test('exports snapshots to JSON', async () => {
      await snapshotManager.initialize();
      
      const exportData = await snapshotManager.exportSnapshots('comp-123');
      
      // Should include snapshots and metadata
      expect(exportData).toEqual({
        version: expect.any(String),
        component: {
          id: 'comp-123',
          name: 'Counter'
        },
        snapshots: mockSnapshots,
        exportedAt: expect.any(Number)
      });
    });
    
    test('imports snapshots from JSON', async () => {
      await snapshotManager.initialize();
      
      const importData = {
        version: '1.0',
        component: {
          id: 'comp-123',
          name: 'Counter'
        },
        snapshots: [
          {
            id: 'import-1',
            componentId: 'comp-123',
            name: 'Imported Snapshot',
            timestamp: Date.now() - 10000,
            state: { count: 42, title: 'Imported' },
            isAutoSnapshot: false
          }
        ],
        exportedAt: Date.now() - 10000
      };
      
      await snapshotManager.importSnapshots(importData);
      
      // Should have added imported snapshots
      const savedSnapshots = (storage.set.mock.calls[0][1] as any[]);
      const importedSnapshot = savedSnapshots.find(s => s.id === 'import-1');
      expect(importedSnapshot).toBeDefined();
      expect(importedSnapshot.name).toBe('Imported Snapshot');
    });
    
    test('validates import data before importing', async () => {
      await snapshotManager.initialize();
      
      // Invalid import data
      const invalidImportData = {
        version: '1.0',
        // Missing component
        snapshots: []
      };
      
      // Should throw error
      await expect(snapshotManager.importSnapshots(invalidImportData as any)).rejects.toThrow();
      
      // Should not save anything
      expect(storage.set).not.toHaveBeenCalledWith('snapshots', expect.any(Array));
    });
  });
});
