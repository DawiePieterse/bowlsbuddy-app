<?php

use App\Support\Theme;

it('gives the member stylesheet the same primary colours as the admin panel', function () {
    $css = (string) file_get_contents(public_path('css/app.css'));

    foreach (Theme::PRIMARY as $shade => $hex) {
        expect($css)->toContain("--primary-$shade: $hex;");
    }
});
