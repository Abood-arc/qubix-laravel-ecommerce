<?php

use DigitalLabs\Customer\Rules\VatIdRule;
use DigitalLabs\Customer\Rules\VatValidator;

function vatIdRuleMessage(string $value, string $country): ?string
{
    $message = null;

    (new VatIdRule)->setCountry($country)->validate('vat_id', $value, function (string $key) use (&$message) {
        $message = $key;

        return new class
        {
            public function translate(): void {}
        };
    });

    return $message;
}

it('names the GSTIN in the error for an invalid Indian tax number', function () {
    expect(vatIdRuleMessage('V12345678901', 'IN'))->toBe('customer::app.validations.vat-id.invalid-gstin-format');
    expect(vatIdRuleMessage('27AAPFU0939F1ZV', 'IN'))->toBeNull();
});

it('keeps the VAT error for other countries', function () {
    expect(vatIdRuleMessage('12345', 'SA'))->toBe('customer::app.validations.vat-id.invalid-format');
});

it('accepts a real Indian GSTIN for an Indian address', function (string $gstin) {
    expect((new VatValidator)->validate($gstin, 'IN'))->toBeTrue();
})->with([
    '27AAPFU0939F1ZV',
    '29aagcr4375j1zu',
    '07 AAACB2894G 1ZJ',
    'IN27AAPFU0939F1ZV',
]);

it('rejects strings that are not GSTINs for an Indian address', function (string $value) {
    expect((new VatValidator)->validate($value, 'IN'))->toBeFalse();
})->with([
    'V12345678901',
    '27AAPFU0939F1Z',
    '27AAPFU0939F1AV',
    'AAPFU0939F',
]);

it('keeps validating Saudi VAT numbers', function () {
    $validator = new VatValidator;

    expect($validator->validate('300000000000003', 'SA'))->toBeTrue();
    expect($validator->validate('27AAPFU0939F1ZV', 'SA'))->toBeFalse();
});
