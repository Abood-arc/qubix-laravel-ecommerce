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
                // Brand green (the header colour). `navyBlue` is the template's legacy name for the
                // primary colour and is used across 36 views, so it now points at the brand too.
                brand: "#1f5f4f",
                navyBlue: "#1f5f4f",
                lightOrange: "#F6F2EB",
                darkGreen: '#40994A',
                darkBlue: '#0044F2',
                darkPink: '#F85156',
            },

            fontFamily: {
                poppins: ["Poppins", "sans-serif"],
                dmserif: ["DM Serif Display", "serif"],
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
