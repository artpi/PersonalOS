import type { Metadata } from 'next';
import { Geist, Geist_Mono } from 'next/font/google';
import ClientOnly from '@/components/client-only';
// import { GeistSans } from 'geist/font/sans'; // Removed due to resolution issues

import './globals.css';
// import { SessionProvider } from 'next-auth/react'; // Removed as next-auth is uninstalled

export const metadata: Metadata = {
  metadataBase: new URL('https://chat.vercel.ai'),
  title: 'PersonalOS',
  description: 'PersonalOS',
  applicationName: 'PersonalOS',
  appleWebApp: {
    capable: true,
    statusBarStyle: 'default',
    title: 'Personal Chat',
    // startupImage: [
    //   '/images/apple-touch-startup-image-768x1004.png',
    //   {
    //     url: '/images/apple-touch-startup-image-1536x2008.png',
    //     media: '(device-width: 768px) and (device-height: 1024px)',
    //   },
    // ],
  },
  formatDetection: {
    telephone: false,
  },
  icons: {
    shortcut: '/wp-content/plugins/personalos/build/chatbot/images/favicon.ico',
    apple: [
      { url: '/wp-content/plugins/personalos/build/chatbot/images/apple-icon.png', sizes: '180x180', type: 'image/png' },
    ],
  },
//   manifest: '/wp-content/plugins/personalos/build/chatbot/manifest.json',
};

export const viewport = {
  maximumScale: 1, // Disable auto-zoom on mobile Safari
};

export const dynamic = 'error';

const geist = Geist({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-geist',
});

const geistMono = Geist_Mono({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-geist-mono',
});

const LIGHT_THEME_COLOR = 'hsl(0 0% 100%)';
const DARK_THEME_COLOR = 'hsl(240deg 10% 3.92%)';
const THEME_COLOR_SCRIPT = `\
(function() {
  var html = document.documentElement;
  var meta = document.querySelector('meta[name="theme-color"]');
  if (!meta) {
    meta = document.createElement('meta');
    meta.setAttribute('name', 'theme-color');
    document.head.appendChild(meta);
  }
  function updateThemeColor() {
    var isDark = html.classList.contains('dark');
    meta.setAttribute('content', isDark ? '${DARK_THEME_COLOR}' : '${LIGHT_THEME_COLOR}');
  }
  var observer = new MutationObserver(updateThemeColor);
  observer.observe(html, { attributes: true, attributeFilter: ['class'] });
  updateThemeColor();
})();`;

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="en"
      // `next-themes` injects an extra classname to the body element to avoid
      // visual flicker before hydration. Hence the `suppressHydrationWarning`
      // prop is necessary to avoid the React hydration mismatch warning.
      // https://github.com/pacocoursey/next-themes?tab=readme-ov-file#with-app
      suppressHydrationWarning
      className={`${geist.variable} ${geistMono.variable}`}
    >
      <head>
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="default" />
        <meta name="apple-mobile-web-app-title" content="Personal Chat" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
        <link rel="apple-touch-icon" href="/wp-content/plugins/personalos/build/chatbot/images/apple-icon.png" />
        <script
          dangerouslySetInnerHTML={{
            __html: THEME_COLOR_SCRIPT,
          }}
        />
      </head>
      <body className="antialiased">
        <ClientOnly>{children}</ClientOnly>
      </body>
    </html>
  );
}
