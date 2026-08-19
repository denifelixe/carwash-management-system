<?php

test('clickable elements use a pointer cursor', function () {
    $stylesheet = file_get_contents(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain('a[href]')
        ->toContain('button:not(:disabled)')
        ->toContain('select:not(:disabled)')
        ->toContain("[role='button']:not([aria-disabled='true'])")
        ->toContain("[role='menuitem']:not([aria-disabled='true'])")
        ->toContain("[role='option']:not([aria-disabled='true'])")
        ->toContain('cursor: pointer;');
});

test('disabled interactive elements use a not allowed cursor', function () {
    $stylesheet = file_get_contents(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain('button:disabled')
        ->toContain('input:disabled')
        ->toContain('select:disabled')
        ->toContain("[aria-disabled='true']")
        ->toContain('cursor: not-allowed;');
});
