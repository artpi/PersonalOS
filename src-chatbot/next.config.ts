import type { NextConfig } from 'next';

// Define a base path. This should match the URL subdirectory where the app is hosted.
// Example: if your WordPress site is example.com and the plugin serves the app from
// example.com/wp-content/plugins/personalos/build/chatbot/, then basePath is
// /wp-content/plugins/personalos/build/chatbot
// Ensure this matches the structure derived in your PHP file.
const PLUGIN_NAME = 'personalos'; // Change if your plugin directory name is different
const BASE_SUBPATH = `/wp-content/plugins/${PLUGIN_NAME}/build/chatbot`;

const isDevelopment = process.env.NODE_ENV === 'development';

const nextConfig: NextConfig = {
  // Only set output to 'export' and specify distDir for non-development (production build)
  ...(!isDevelopment && {
    output: 'export',
    distDir: '../build/chatbot', // This should be <project_root>/build/chatbot
    basePath: BASE_SUBPATH,
    assetPrefix: BASE_SUBPATH, // Typically same as basePath for static exports under a subpath
  }),
  // experimental: { // PPR disabled as it conflicts with static export
  //   ppr: true,
  // },
  images: {
    remotePatterns: [
      {
        hostname: 'avatar.vercel.sh',
      },
    ],
    unoptimized: true,
  },
};

export default nextConfig;
