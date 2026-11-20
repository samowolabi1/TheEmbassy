<?php

// Blade Icons comes with Laravel Notify (it draws the toast icons). Its
// <x-icon> component is switched off because the app has its own
// resources/views/components/icon.blade.php; the <x-untitledui-*> icons
// the toasts use still work.
return [
    'components' => [
        'disabled' => false,
        'default' => null,
    ],
];
