/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./src/Resources/**/*.blade.php", "./src/Resources/**/*.js"],

    theme: {
        container: {
            center: true,

            screens: {
                "2xl": "1440px",
            },

            padding: {
                DEFAULT: "90px",
            },
        },

        screens: {
            sm: "525px",
            md: "768px",
            lg: "1024px",
            xl: "1240px",
            "2xl": "1440px",
            1180: "1180px",
            1060: "1060px",
            991: "991px",
            868: "868px",
        },

        extend: {
            colors: {
                // Brand colours are CSS variables (RGB channels) set per store look in app.css
                // ([data-look] on <html>), so one admin setting re-skins the store and opacity
                // modifiers like bg-brand/10 still work. `navyBlue` is the template's legacy name
                // for the primary colour (used across 36 views), so it follows the brand colour.
                brand: "rgb(var(--c-brand) / <alpha-value>)",
                brandDeep: "rgb(var(--c-brand-deep) / <alpha-value>)",
                sage: "rgb(var(--c-sage) / <alpha-value>)",
                sand: "rgb(var(--c-sand) / <alpha-value>)",
                navyBlue: "rgb(var(--c-brand) / <alpha-value>)",
                head: "rgb(var(--c-head) / <alpha-value>)",
                headInk: "rgb(var(--c-head-ink) / <alpha-value>)",
                nav: "rgb(var(--c-nav) / <alpha-value>)",
                navInk: "rgb(var(--c-nav-ink) / <alpha-value>)",
                offer: "rgb(var(--c-offer) / <alpha-value>)",
                offerInk: "rgb(var(--c-offer-ink) / <alpha-value>)",
                field: "rgb(var(--c-field) / <alpha-value>)",
                accent: "rgb(var(--c-accent) / <alpha-value>)",
                foot: "rgb(var(--c-foot) / <alpha-value>)",
                footInk: "rgb(var(--c-foot-ink) / <alpha-value>)",
                footMuted: "rgb(var(--c-foot-muted) / <alpha-value>)",
                lightOrange: "#F6F2EB",
                darkGreen: '#40994A',
                darkBlue: '#0044F2',
                darkPink: '#F85156',
            },

            fontFamily: {
                // The store look sets --font-text and --font-display (Karla in Forest Green).
                // `poppins` and `dmserif` are the template's legacy names, used across many views,
                // so they follow the look too; admin-managed HTML that names "Poppins" or
                // "DM Serif Display" directly still gets those fonts.
                karla: ["var(--font-text)", "sans-serif"],
                poppins: ["var(--font-text)", "sans-serif"],
                dmserif: ["var(--font-display)", "sans-serif"],
                arabic: ["Noto Sans Arabic", "Poppins", "sans-serif"],
            },

            borderRadius: {
                // Buttons, search fields, chips: pill in Forest Green, square-ish in other looks.
                ui: "var(--radius-ui)",
            },
        }
    },

    plugins: [],

    safelist: [
        {
            pattern: /icon-/,
        }
    ]
};
