// import { generateDummyPassword } from './db/utils'; // Removed as ./db/utils.ts is deleted

import type { Config, PARAItem } from './window';

// Guest regex for identifying guest users based on email pattern.
export const guestRegex = /^guest-\\d+$/;

// Environment flags
export const isProductionEnvironment = process.env.NODE_ENV === 'production';
export const isDevelopmentEnvironment = process.env.NODE_ENV === 'development';
export const isTestEnvironment = Boolean(
  process.env.TEST_ENV || process.env.NODE_ENV === 'test',
);

// export const DUMMY_PASSWORD = generateDummyPassword(); // Removed as it's no longer used after auth and DB removal

// Mock Sidebar Data
// Note: Icons (React components) cannot be part of the serializable window.config.
// They are added here for the fallback/dev scenario where app-sidebar consumes these directly.
// In a real scenario, window.config might have icon names, and app-sidebar.tsx would map them to components.

// Placeholder FileIcon for mock data - this will be an issue if constants.ts is used by backend PHP.
// For now, assuming this constants.ts is purely frontend for fallback.
// Ideally, icons are handled entirely within the React components or mapped from strings.
const MockFileIconPlaceholder = 'FileIcon'; // Using a string placeholder for icon

export const mockSidebarProjects: PARAItem[] = [
  { id: 'project-1', name: 'Organize Vacation', icon: MockFileIconPlaceholder },
  { id: 'project-2', name: 'Ship the app', icon: MockFileIconPlaceholder },
];

export const mockSidebarStarred: PARAItem[] = [
  { id: 'area-1', name: 'Wife', icon: MockFileIconPlaceholder },
  { id: 'area-2', name: 'Kid 1', icon: MockFileIconPlaceholder },
  { id: 'area-3', name: 'Dog', icon: MockFileIconPlaceholder },
];

/**
 * Retrieves the runtime configuration injected by PHP.
 * Provides a fallback configuration if window.config is not yet available.
 * It is crucial that this function is called only when window.config is expected to be defined,
 * or the consuming code must gracefully handle the fallback.
 */
export function getConfig(): Config {
  // Default/fallback configuration
  const fallbackConfig: Config = {
    site_title: 'PersonalOS',
	wp_admin_url: 'http://localhost:8901/wp-admin/',
	nonce: '',
    rest_api_url: 'http://localhost:8901/wp-json/', // Must be a valid base for API calls if used before real config loads
	projects: mockSidebarProjects,
	starred: mockSidebarStarred,
  };

  if (typeof window !== 'undefined' && window.config) {
    return window.config;
  }
  console.warn(
    'window.config was not defined when getConfig() was called. Using fallback. Check script loading order.',
  );
  return fallbackConfig;
}
