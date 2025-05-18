// import { generateDummyPassword } from './db/utils'; // Removed as ./db/utils.ts is deleted

import type { Config } from './window';

// Guest regex for identifying guest users based on email pattern.
export const guestRegex = /^guest-\\d+$/;

// Environment flags
export const isProductionEnvironment = process.env.NODE_ENV === 'production';
export const isDevelopmentEnvironment = process.env.NODE_ENV === 'development';
export const isTestEnvironment = Boolean(
  process.env.TEST_ENV || process.env.NODE_ENV === 'test',
);

// export const DUMMY_PASSWORD = generateDummyPassword(); // Removed as it's no longer used after auth and DB removal

/**
 * Retrieves the runtime configuration injected by PHP.
 * Provides a fallback configuration if window.config is not yet available.
 * It is crucial that this function is called only when window.config is expected to be defined,
 * or the consuming code must gracefully handle the fallback.
 */
export function getConfig(): Config {
  // Default/fallback configuration
  const fallbackConfig: Config = {
    site_title: 'Chatbot (Loading Config...)',
    api_url: 'http://localhost:8901/wp-admin/', // Intended to be admin_url or similar
    rest_api_url: 'http://localhost:8901/wp-json/', // Must be a valid base for API calls if used before real config loads
  };

  if (typeof window !== 'undefined' && window.config) {
    return window.config;
  }
  console.warn(
    'window.config was not defined when getConfig() was called. Using fallback. Check script loading order.',
  );
  return fallbackConfig;
}
