<?php

return [
    'validations' => [
        'captcha' => [
            'captcha' => '何か問題が発生しました！もう一度試してみてください。',
            'required' => 'CAPTCHA を選択してください',
        ],

        'vat-id' => [
            'invalid-format' => '無効な消費税形式',
            'invalid-gstin-format' => 'GSTINの形式が正しくありません。例: 27AAPFU0939F1ZV',
        ],
    ],
];
