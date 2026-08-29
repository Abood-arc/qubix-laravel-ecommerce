import Flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.css";
import { Arabic } from "flatpickr/dist/l10n/ar.js";

export default {
    install: (app) => {
        window.Flatpickr = Flatpickr;

        const setLocaleFromLang = () => {
            const lang = document.documentElement.lang || "en";

            const localeMap = {
                ar: Arabic,
            };

            const locale = localeMap[lang] || null;

            if (locale) {
                window.Flatpickr.localize(locale);
            }
        };

        setLocaleFromLang();
    },
};
