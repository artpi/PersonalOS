'use client';

import { useEffect } from 'react';

export function PWARegister() {
  useEffect(() => {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker
        .register('/wp-content/plugins/personalos/build/chatbot/sw.js')
        .catch((error) => {
          console.log('Service worker registration failed:', error);
        });
    }
  }, []);

  return null;
}

