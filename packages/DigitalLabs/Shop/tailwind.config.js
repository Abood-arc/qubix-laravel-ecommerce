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
                // Brand greens and neutrals. `navyBlue` is the template's legacy name for the
                // primary colour and is used across 36 views, so it points at the brand green.
                brand: "#2f5d48",
                brandDeep: "#234537",
                sage: "#e3ede4",
                sand: "#f4efe6",
                navyBlue: "#2f5d48",
                lightOrange: "#F6F2EB",
                darkGreen: '#40994A',
                darkBlue: '#0044F2',
                darkPink: '#F85156',
            },

            fontFamily: {
                // The store's one face. `poppins` and `dmserif` are the template's legacy names
                // (used across many views), so they point at Karla too; admin-managed HTML that
                // names "Poppins" or "DM Serif Display" directly still gets those fonts.
                karla: ["Karla", "sans-serif"],
                poppins: ["Karla", "sans-serif"],
                dmserif: ["Karla", "sans-serif"],
                arabic: ["Noto Sans Arabic", "Poppins", "sans-serif"],
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
