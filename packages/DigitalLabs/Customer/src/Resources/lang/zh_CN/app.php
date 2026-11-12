<?php

return [
    'validations' => [
        'captcha' => [
            'captcha' => '出问题了! 请再试一次.',
            'required' => '请选择验证码',
        ],

        'vat-id' => [
            'invalid-format' => '无效的增值税格式',
            'invalid-gstin-format' => 'GSTIN 格式无效。示例：27AAPFU0939F1ZV',
        ],
    ],
];
