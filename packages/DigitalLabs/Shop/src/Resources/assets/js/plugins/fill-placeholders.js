/**
 * Fills the ":name" style placeholders of a translated line, the way Laravel's trans() does on the
 * server, for copy that Blade hands to a component and the component completes in the browser:
 *
 *     fillPlaceholders("Photo :current of :total", { current: 2, total: 4 }) // "Photo 2 of 4"
 *
 * Longer names go first, so ":count" never eats the start of ":counter". Values are inserted as
 * they are ("$" in a product name is never read as a replacement pattern).
 *
 * Components use it as this.$fillPlaceholders(); plain page scripts read it from
 * app.config.globalProperties.$fillPlaceholders.
 */
export function fillPlaceholders(line, replacements = {}) {
    return Object.entries(replacements)
        .sort(([a], [b]) => b.length - a.length)
        .reduce((text, [name, value]) => text.replaceAll(`:${name}`, () => String(value)), line);
}

export default {
    install(app) {
        app.config.globalProperties.$fillPlaceholders = fillPlaceholders;
    },
};
