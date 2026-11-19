<?php

/**
 * A settings page checks its fields in the browser before it saves, with the same rule string
 * the server uses. A rule the browser does not know (Laravel's "nullable", say) throws there,
 * and Save silently does nothing for the whole page, every section on it included.
 */
const BROWSER_VALIDATION_RULES = [
    // @vee-validate/rules
    'alpha', 'alpha_dash', 'alpha_num', 'alpha_spaces', 'between', 'confirmed', 'digits',
    'dimensions', 'email', 'ext', 'image', 'integer', 'is', 'is_not', 'length', 'max',
    'max_value', 'mimes', 'min', 'min_value', 'not_one_of', 'numeric', 'one_of', 'regex',
    'required', 'size', 'url',
    // Admin/src/Resources/assets/js/plugins/vee-validate.js
    'phone', 'address', 'relative_url', 'postcode', 'decimal', 'required_if',
];

it('only uses validation rules the admin settings pages can check in the browser', function () {
    $unknown = [];

    foreach (config('core') as $item) {
        foreach ($item['fields'] ?? [] as $field) {
            foreach (explode('|', $field['validation'] ?? '') as $rule) {
                $name = explode(':', $rule)[0];

                if ($name !== '' && ! in_array($name, BROWSER_VALIDATION_RULES, true)) {
                    $unknown[] = "{$item['key']}.{$field['name']}: {$name}";
                }
            }
        }
    }

    expect($unknown)->toBe([]);
});

it('fills in the Shiprocket backup price, so the Shipping Methods page saves with Shiprocket off', function () {
    expect(core()->getConfigData('sales.carriers.shiprocket.backup_price'))->toBe('150');
});
