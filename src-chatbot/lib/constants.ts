// import { generateDummyPassword } from './db/utils'; // Removed as ./db/utils.ts is deleted

export const isProductionEnvironment = process.env.NODE_ENV === 'production';
export const isDevelopmentEnvironment = process.env.NODE_ENV === 'development';
export const isTestEnvironment = Boolean(
  process.env.PLAYWRIGHT_TEST_BASE_URL ||
    process.env.PLAYWRIGHT ||
    process.env.CI_PLAYWRIGHT,
);

export const guestRegex = /^guest-\d+$/;

// export const DUMMY_PASSWORD = generateDummyPassword(); // Removed as it's no longer used after auth and DB removal
