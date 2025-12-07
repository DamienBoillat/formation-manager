<?php

add_action('wp_enqueue_scripts', 'customTheme');

function customTheme() {
    wp_enqueue_style(
        'parent-style',
        get_stylesheet_uri()
    );
}