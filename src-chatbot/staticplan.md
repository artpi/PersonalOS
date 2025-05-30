Okay, to make this Next.js project (which is heavily reliant on server-side features and API routes) run as a standalone HTML/JS bundle for *initial display only* (with API calls failing, as you specified), we'll need to configure Next.js for a static export.

Here's what you need to do:

1.  **Modify `next.config.js` for Static Export:**
    Tell Next.js to output static HTML files.

    Update `next.config.ts`:
    ```typescript
    import type { NextConfig } from 'next';

    const nextConfig: NextConfig = {
      // Add this line
      output: 'export',
      experimental: {
        ppr: true,
      },
      images: {
        remotePatterns: [
          {
            hostname: 'avatar.vercel.sh',
          },
        ],
        // For static export, next/image optimization needs to be disabled
        // or configured for a different loader if you're not serving from Vercel.
        // For simplest standalone, unoptimized is best.
        unoptimized: true,
      },
    };

    export default nextConfig;
    ```

2.  **Modify `package.json` Scripts:**
    Ensure your build script correctly uses `next build` (which will now respect the `output: 'export'` config). You'll also need a way to serve the static files.

    In `package.json`:
    ```json
    {
      "name": "ai-chatbot",
      "version": "3.0.8",
      "private": true,
      "scripts": {
        "dev": "next dev --turbo",
        "build": "tsx lib/db/migrate && next build", // This is fine, next build will do the export
        "start": "next start",
        "lint": "next lint && biome lint --write --unsafe",
        "lint:fix": "next lint --fix && biome lint --write --unsafe",
        "format": "biome format --write",
        "db:generate": "drizzle-kit generate",
        "db:migrate": "npx tsx lib/db/migrate.ts",
        "db:studio": "drizzle-kit studio",
        "db:push": "drizzle-kit push",
        "db:pull": "drizzle-kit pull",
        "db:check": "drizzle-kit check",
        "db:up": "drizzle-kit up",
        "test": "export PLAYWRIGHT=True && pnpm exec playwright test",
        // Add a script to serve the static output
        "serve-static": "pnpm dlx serve out"
      },
      // ... rest of your package.json
    }
    ```
    If you don't have `serve` globally, `pnpm dlx serve out` will download and run it.

3.  **Understand Limitations (Important):**
    When you do a static export (`output: 'export'`):
    *   **No API Routes:** All files in `app/api` or `pages/api` will **not** be functional. Any `fetch` calls to these from the client-side will fail. You said you're okay with this.
    *   **No Server Components Running Dynamically:** Server Components will be rendered to HTML at build time. Any dynamic server-side logic within them (like fetching from a database on each request) won't run post-export.
    *   **No Server Actions:** All server actions (`"use server"`) will **not** work. Forms submitting to them will fail.
    *   **No Middleware:** `middleware.ts` will **not** run.
    *   **Dynamic Routes:**
        *   For dynamic routes like `app/(chat)/chat/[id]/page.tsx`, Next.js needs to know which paths to pre-render. If you don't provide `generateStaticParams`, these routes might not be exported, or only a fallback HTML is generated. Accessing them directly via URL might lead to a 404. Client-side navigation via `<Link>` might still work if the page can render without server-fetched params.
        *   For your goal of "display the initial screen," the root page (`/`) should export correctly.
    *   **Authentication (`next-auth`):**
        *   The `signIn`, `signOut`, `auth` functions that rely on the backend API routes will not work.
        *   The application will likely default to a "guest" state or whatever state it falls into when `auth()` calls in layouts/pages return `null` or a default unauthenticated session during the build.
        *   Middleware redirects for auth will not function.
    *   **Image Optimization (`next/image`):** By setting `unoptimized: true`, images will be served as-is without server-side optimization. This is necessary for a pure static deployment.
    *   **Pyodide:** The Pyodide script is loaded client-side: `<Script src="https://cdn.jsdelivr.net/pyodide/v0.23.4/full/pyodide.js" strategy="beforeInteractive" />`. This will still be included in the HTML and should load. However, any Python code execution that relies on backend APIs will fail.

4.  **Build the Project:**
    Run your build command:
    ```bash
    pnpm build
    ```
    This will create an `out` directory containing the static HTML, CSS, and JS files. The database migration will run first, which is fine (it just won't be used by the static site).

5.  **Serve the Static Files:**
    Use a simple HTTP server to serve the `out` directory.
    ```bash
    pnpm serve-static
    ```
    This will typically start a server (e.g., on `http://localhost:3000` or another port if 3000 is taken). Open this URL in your browser.

6.  **Check the Output:**
    *   You should see the initial UI of your chatbot.
    *   Open the browser's developer console. You will likely see many errors related to failed API calls (`/api/chat`, `/api/auth/guest`, `/api/history`, etc.). This is expected given your requirements.
    *   Features like logging in, registering, sending messages, fetching chat history, or anything relying on the API backend will not work.

This process will give you a set of HTML, CSS, and JS files in the `out` directory that can be hosted on any static web server (like GitHub Pages, Netlify, Vercel Static, or even just opening `out/index.html` locally, though local file access can sometimes have issues with JS module loading or routing depending on the browser).

The key is that the React components will render their initial state as determined at build time. Client-side JavaScript for UI interactions (like dropdowns, theme toggling if it's client-side, etc.) should still function. The core "intelligence" and data-driven parts of the chatbot will be non-functional.

## Addendum: Actual Steps Taken and Deviations for Next.js 15.3.0-canary.31

The process of achieving a static export with Next.js 15.3.0-canary.31 required several deviations from the original plan due to build errors and incompatibilities. The core goal of obtaining a static export for initial display, with server-dependent features failing at runtime, was maintained.

**Key Deviations and Steps:**

1.  **`next.config.ts` Modifications:**
    *   `output: 'export'` and `images.unoptimized: true` were set as planned.
    *   **Deviation:** `experimental.ppr: true` (Partial Prerendering) had to be **disabled** (commented out). The build process explicitly stated: `Error: Invariant: PPR cannot be enabled in export mode`.

2.  **`package.json` Script Changes:**
    *   The `build` script was changed from `tsx lib/db/migrate && next build` to just `next build`. The database migration step (`tsx lib/db/migrate`) caused initial build failures (missing `POSTGRES_URL`) and is not necessary for a display-only static export.
    *   The `serve-static` script (`pnpm dlx serve out`) was added as planned.

3.  **Handling API Routes and Server-Side Modules:**
    *   All API route directories (`app/(auth)/api/` and `app/(chat)/api/`) were moved to a backup directory (`_api_routes_backup/`).
    *   `_api_routes_backup` was added to `tsconfig.json`'s `exclude` array.

4.  **Neutralizing Server Actions and Server-Only Code (Extensive Modifications):**
    This was the most complex part, as the build failed if it merely *detected* server actions or server-only code, even if they were intended to be non-functional in the static output.
    *   **`'use server';` Directives:** Commented out in:
        *   `app/(auth)/actions.ts`
        *   `app/(chat)/actions.ts`
        *   `artifacts/actions.ts`
    *   **`next/form` to HTML `<form>`:**
        *   `components/auth-form.tsx`: Replaced `next/form`'s `<Form>` with HTML `<form>`, removed `action` prop binding.
        *   `components/sign-out-form.tsx`: Same as above, button also disabled.
    *   **`server-only` Package:**
        *   `lib/db/queries.ts`: Commented out `import 'server-only';`.
    *   **Preventing Server-Side Package Bundling (e.g., `postgres`, `next/headers`):**
        *   Numerous functions across `app/(auth)/actions.ts`, `app/(chat)/actions.ts`, `artifacts/actions.ts`, and `app/(auth)/auth.ts` (NextAuth config) had their internal database calls (e.g., `getUser`, `createUser`, `getSuggestionsByDocumentId`) and imports of DB functions commented out to prevent `postgres` and its Node.js built-in dependencies (`net`, `tls`, `fs`, etc.) from being pulled into the client bundle.
        *   `lib/ai/providers.ts`: Removed an import from `models.test.ts` which was pulling in test utilities with server-side dependencies (`async_hooks`).
    *   **`headers()` and `cookies()` Usage (from `next/headers`):**
        *   Calls to `cookies()` (and by extension, `headers()`) were removed or neutralized in:
            *   `app/(chat)/page.tsx` (root page): Replaced `await cookies()` and `await auth()` with mock data/default fallbacks.
            *   `app/(chat)/layout.tsx`: Same as above.
            *   `app/(chat)/chat/[id]/page.tsx`: Same as above (before this page was ultimately disabled).
            *   `app/(chat)/actions.ts`: `saveChatModelAsCookie` function's use of `cookies()` was commented out.
        *   This was necessary because `dynamic = "error"` (in `app/layout.tsx`) disallows `headers()`/`cookies()` usage during static rendering.

5.  **Middleware:**
    *   `middleware.ts` was renamed to `middleware_disabled.ts` as it's not functional in static export and its presence could affect build analysis.

6.  **Dynamic Route `app/(chat)/chat/[id]/page.tsx`:**
    *   This page proved highly problematic. When PPR was disabled, the build consistently (and seemingly incorrectly) failed with an error stating `Page "/chat/[id]" is missing "generateStaticParams()"`, even though the function was present and correctly defined.
    *   **Deviation:** As a final workaround to allow the rest of the site to build, this page was **disabled by renaming it to `page_disabled.tsx`**. This means the `/chat/[id]` path will not be available in the static export, which is a more significant deviation than just having it 404 at runtime but was necessary due to the persistent build error.

**Outcome:**

After these extensive modifications, the `pnpm build` command completed successfully, producing a static export in the `out/` directory. The resulting site should provide the initial UI display, with server-dependent features (API calls, database interactions, dynamic auth, cookie-based logic) being non-functional or using mocked/default data, as per the original goal's limitations. The key difference was the necessity to actively prevent the build process from *detecting* or *attempting to bundle* server-side code, rather than just letting those features fail gracefully at runtime after a successful build. The incompatibility of PPR with static export mode in this Next.js version was also a critical deviation.