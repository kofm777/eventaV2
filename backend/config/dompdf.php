<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Set some default values. It is possible to add all defines that can be set
    | in dompdf_config.inc.php. You can also override the entire config file.
    |
    */
    'show_warnings' => false,   // Throw an exception on warnings from dompdf

    'orientation' => 'portrait',

    'defines' => [

        /**
         * The location of the DOMPDF font directory
         *
         * The location of the directory where DOMPDF will store fonts and font metrics
         * Note: This directory must exist and be writable by the webserver process.
         * *Please note the trailing slash.*
         *
         * Notes regarding fonts:
         * - DOMPDF was designed to function with bundled fonts only.
         * - If additional fonts are needed, you will need to add them to the font directory.
         * - Fonts can be added in multiple ways (see dompdf_font_family in the config section below)
         */
        "DOMPDF_FONT_DIR" => storage_path('fonts/'), // advised by dompdf (https://github.com/dompdf/dompdf/pull/782)

        /**
         * The location of the DOMPDF font cache directory
         *
         * This directory contains the cached font metrics for the fonts used by DOMPDF.
         * This directory can be the same as DOMPDF_FONT_DIR
         */
        "DOMPDF_FONT_CACHE" => storage_path('fonts/'),

        /**
         * dompdf's "chroot": Prevents dompdf from accessing system files or other
         * files on the webserver. All local files opened by dompdf must be in a
         * subdirectory of this directory. Similarly, web files accessed via
         * http[|s]:// will only be loaded if they reside on the same server as
         * the script loading dompdf. The dirname(__FILE__) value or '.' may not
         * be suitable based on your server configuration. Leaving it blank will
         * effectively disable the chroot feature.
         */
        "DOMPDF_CHROOT" => realpath(base_path()),

        /**
         * The access key 'owner_password' 'user_password'
         * These are used to control owner and user access on pdf's
         */
        "DOMPDF_PDF_BACKEND" => "CPDF",
        "DOMPDF_DEFAULT_MEDIA_TYPE" => "screen",
        "DOMPDF_DEFAULT_PAPER_SIZE" => "a4",
        "DOMPDF_DEFAULT_FONT" => "serif",
        "DOMPDF_DPI" => 96,
        "DOMPDF_ENABLE_PHP" => false,
        "DOMPDF_ENABLE_JAVASCRIPT" => true,
        "DOMPDF_ENABLE_REMOTE" => true,
        "DOMPDF_FONT_HEIGHT_RATIO" => 1.1,
        "DOMPDF_ENABLE_CSS_FLOAT" => false,
        "DOMPDF_ENABLE_HTML5PARSER" => false,
    ],
];
