<?php

declare(strict_types=1);

/*
 * DomPDF config for TAQAT.
 *
 * Arabic renders correctly only when the PDF font supports the full Arabic
 * glyph range. DomPDF's bundled DejaVu Sans covers Arabic reasonably well
 * and is the safest default — it ships with the package, so no external
 * font download is required for the container image.
 *
 * Phase 3 polish: bundle a proper Amiri or Cairo TTF under
 * storage/fonts/ and set `default_font` to it (plus load it via
 * `@font-face` in the Blade so the shape is closer to native Arabic
 * typography).
 */

return [

    'show_warnings' => false,

    'public_path' => null,

    /*
     * Convert HTML5 tags. dompdf/dompdf 2.x+ handles them natively; leave
     * enabled so `<header>` / `<footer>` / `<section>` in the Blade view
     * are treated as block-level containers.
     */
    'convert_entities' => true,

    'options' => [

        /*
         * Where DomPDF writes autoloaded fonts. Must be writable by the
         * php-fpm user; storage_path is already writable in TAQAT's
         * container image.
         */
        'font_dir' => storage_path('fonts/'),

        /*
         * Font cache — dompdf writes .ufm/.afm metrics here on first use.
         * Keep separate from font_dir so we can prune the cache without
         * losing custom fonts.
         */
        'font_cache' => storage_path('fonts/'),

        /*
         * Temp dir for streamed PDF writes.
         */
        'temp_dir' => sys_get_temp_dir(),

        /*
         * Chroot restricts DomPDF to reading files from this path (used by
         * <img src="..."> and CSS url()). Keep it inside the Laravel base
         * dir so a malicious template can't read arbitrary host files.
         */
        'chroot' => realpath(base_path()),

        /*
         * Allowed protocols for asset URLs. Leave file:// enabled so local
         * TTFs and logos work, and http/https for CDN fonts (Amiri from
         * Google Fonts is used as a placeholder in the Attendance report
         * Blade until we bundle a local TTF).
         */
        'allowed_protocols' => [
            'data://' => ['rules' => []],
            'file://' => ['rules' => []],
            'http://' => ['rules' => []],
            'https://' => ['rules' => []],
        ],

        'artifactPathValidation' => null,

        'log_output_file' => null,

        /*
         * Font metrics cache — required for Arabic shaping. DejaVu Sans
         * is bundled with dompdf/dompdf and covers Arabic glyphs.
         */
        'default_media_type' => 'screen',
        'default_paper_size' => 'a4',
        'default_paper_orientation' => 'portrait',
        'default_font' => 'DejaVu Sans',

        'dpi' => 96,

        'enable_php' => false,
        'enable_javascript' => false,
        'enable_remote' => true,
        'allowed_remote_hosts' => null,

        'font_height_ratio' => 1.1,

        'enable_html5_parser' => true,
    ],

];
