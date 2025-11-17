<?php
if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
require_once __DIR__ . '/../vendor/autoload.php';
}

spl_autoload_register(
static function ( $class ) {
if ( 0 !== strpos( $class, 'SmoothBooking\\' ) ) {
return;
}

$path = __DIR__ . '/../src/' . str_replace( [ 'SmoothBooking\\', '\\' ], [ '', '/' ], $class ) . '.php';

if ( file_exists( $path ) ) {
require_once $path;
}
}
);

if ( ! class_exists( 'WP_Post' ) ) {
class WP_Post {
public int $ID;
public string $post_title = '';
public string $post_content = '';
}
}

$GLOBALS['sb_test_meta'] = [];

if ( ! function_exists( 'get_post_meta' ) ) {
function get_post_meta( $post_id, $key, $single = true ) {
return $GLOBALS['sb_test_meta'][ $post_id ][ $key ] ?? '';
}
}

if ( ! function_exists( 'apply_filters' ) ) {
function apply_filters( $tag, $value ) {
return $value;
}
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
function get_edit_post_link( $post_id, $context = '' ) {
return '';
}
}

if ( ! function_exists( '__' ) ) {
function __( $text ) {
return $text;
}
}
