/**
 * Shared Vite dev server settings for Shop / Admin / Installer.
 *
 * - `APP_URL` hostname drives HMR unless `VITE_HMR_HOST` is set (SSH tunnel / unusual DNS).
 * - `VITE_HOST` / `VITE_PORT` control bind address and port (defaults suit Laravel Sail on Linux).
 */

export function hostnameFromAppUrl(appUrl) {
    if (!appUrl || typeof appUrl !== "string") {
        return null;
    }
    try {
        const hostname = new URL(appUrl).hostname;
        return hostname || null;
    } catch {
        return null;
    }
}

/**
 * Pre-declare deps so the dev server does not thrash on stale `.vite/deps` hashes
 * (504 "Outdated Optimize Dep") after env changes or slow first requests over SSH.
 */
export const shopOptimizeDeps = {
    include: [
        "vue",
        "vue/dist/vue.esm-bundler",
        "axios",
        "mitt",
        "vee-validate",
        "@vee-validate/rules",
        "@vee-validate/i18n",
        "vue-flatpickr",
        "flatpickr",
    ],
    holdUntilCrawlEnd: false,
};

export function resolveViteDevServer(env) {
    const sail = env.LARAVEL_SAIL === "1";
    const usePolling =
        env.VITE_USE_POLLING === "true" ||
        (sail && env.VITE_USE_POLLING !== "false");

    const host = env.VITE_HOST || (sail ? "0.0.0.0" : "localhost");
    const port = Number(env.VITE_PORT) || 5173;

    const hmrHost =
        (typeof env.VITE_HMR_HOST === "string" && env.VITE_HMR_HOST.trim()) ||
        hostnameFromAppUrl(env.APP_URL) ||
        "localhost";

    return {
        host,
        port,
        strictPort: env.VITE_STRICT_PORT !== "false",
        cors: true,
        ...(usePolling ? { watch: { usePolling: true, interval: 1000 } } : {}),
        hmr: {
            host: hmrHost,
            ...(env.VITE_HMR_CLIENT_PORT
                ? { clientPort: Number(env.VITE_HMR_CLIENT_PORT) }
                : {}),
        },
    };
}
