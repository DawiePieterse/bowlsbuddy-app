<?php

namespace App\Support;

/**
 * The colours both halves of Bowls Buddy share, so the member pages and the admin panel look like one app.
 * The panel takes them from here; public/css/app.css repeats them as CSS variables (no build step), and
 * ThemeTest keeps the two in step.
 */
class Theme
{
    /**
     * A fresh emerald. 500 and 600 are dark enough for white text (WCAG AA), so Filament keeps white
     * text on its buttons and their hover state.
     *
     * @var array<int, string>
     */
    public const PRIMARY = [
        50 => '#ecfdf5',
        100 => '#d1fae5',
        200 => '#a7f3d0',
        300 => '#6ee7b7',
        400 => '#34d399',
        500 => '#05835f',
        600 => '#047857',
        700 => '#036b4e',
        800 => '#065f46',
        900 => '#064e3b',
        950 => '#022c22',
    ];
}
