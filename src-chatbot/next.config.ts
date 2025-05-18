import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  output: 'export',
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
