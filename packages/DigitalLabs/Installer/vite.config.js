import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import path from "path";
import { resolveViteDevServer } from "../../../vite.shared.mjs";

export default defineConfig(({ mode }) => {
    const envDir = "../../../";

    Object.assign(process.env, loadEnv(mode, envDir));

    return {
        build: {
            emptyOutDir: true,
        },

        envDir,

        server: resolveViteDevServer(process.env),

        plugins: [
            laravel({
                hotFile: "../../../public/installer-default-vite.hot",
                publicDirectory: "../../../public",
                buildDirectory: "themes/installer/default/build",
                input: [
                    "src/Resources/assets/css/app.css",
                    "src/Resources/assets/js/app.js",
                ],
                refresh: true,
            }),
        ],

        experimental: {
            renderBuiltUrl(filename, { hostId, hostType, type }) {
                if (hostType === "css") {
                    return path.basename(filename);
                }
            },
        },
    };
});