// Create a DevTools panel
chrome.devtools.panels.create(
  "Tapped",
  "/assets/icons/tapped-logo-32.png",
  "/panel.html",
  (panel) => {
    console.log("Tapped DevTools panel created");
    
    // The panel has been created, connect to background page
    const tabId = chrome.devtools.inspectedWindow.tabId;
    const port = chrome.runtime.connect({
      name: `tapped-devtools-${tabId}`
    });
    
    // Store port reference for use in panel window
    window.tappedDevToolsPort = port;
    window.tappedTabId = tabId;
    
    // Handle panel events
    panel.onShown.addListener((panelWindow) => {
      console.log("Tapped panel shown");
      // Pass the port to the panel window if it's ready
      if (panelWindow.setPort) {
        panelWindow.setPort(port, tabId);
      } else {
        // Store for later use
        panelWindow.tappedDevToolsPort = port;
        panelWindow.tappedTabId = tabId;
      }
    });
  }
);
