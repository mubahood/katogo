import React from 'react';
import { createRoot } from 'react-dom/client';
import AccountLayout from './components/AccountLayout';

// Initialize React app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  // Initialize account layout if container exists
  const accountContainer = document.getElementById('account-layout-root');
  if (accountContainer) {
    const root = createRoot(accountContainer);
    
    // Get initial tab from URL or data attribute
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab') || accountContainer.dataset.initialTab || 'dashboard';
    
    root.render(
      <AccountLayout 
        initialTab={initialTab}
        onClose={() => {
          // Handle close - could navigate back or hide modal
          window.history.back();
        }}
      />
    );
  }

  // Initialize other components as needed
  initializeVideoPlayers();
  initializeGlobalComponents();
});

// Initialize video players on the page
function initializeVideoPlayers() {
  const videoContainers = document.querySelectorAll('[data-video-player]');
  videoContainers.forEach(container => {
    // Initialize video player components here if needed
    console.log('Video player container found:', container);
  });
}

// Initialize other global React components
function initializeGlobalComponents() {
  // Account menu trigger
  const accountMenuTrigger = document.getElementById('account-menu-trigger');
  if (accountMenuTrigger) {
    accountMenuTrigger.addEventListener('click', (e) => {
      e.preventDefault();
      showAccountLayout();
    });
  }

  // Add other global component initializations here
}

// Function to show account layout
function showAccountLayout(initialTab = 'dashboard') {
  // Create or show account layout container
  let container = document.getElementById('account-layout-root');
  
  if (!container) {
    container = document.createElement('div');
    container.id = 'account-layout-root';
    container.dataset.initialTab = initialTab;
    document.body.appendChild(container);
    
    const root = createRoot(container);
    root.render(
      <AccountLayout 
        initialTab={initialTab}
        onClose={() => {
          container?.remove();
        }}
      />
    );
  } else {
    container.style.display = 'block';
  }
}

// Export utility functions for use in other scripts
declare global {
  interface Window {
    showAccountLayout: (tab?: string) => void;
    KatogoApp: {
      showAccountLayout: (tab?: string) => void;
    };
  }
}

window.showAccountLayout = showAccountLayout;
window.KatogoApp = {
  showAccountLayout
};

export { showAccountLayout };