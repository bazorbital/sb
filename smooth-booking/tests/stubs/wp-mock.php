<?php
/**
 * Minimal WordPress stubs for PHPUnit without WP core.
 */

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( string $text, string $domain = 'default' ): string {
        return $text;
    }
}

if ( ! function_exists( '__' ) ) {
    function __( string $text, string $domain = 'default' ): string {
        return $text;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( string $value ): string {
        return trim( $value );
    }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( string $value ): string {
        return trim( str_replace( ["\r", "\n"], ' ', $value ) );
    }
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
    function sanitize_hex_color( string $color ): string {
        $color = trim( $color );

        if ( '' === $color ) {
            return '';
        }

        $color = ltrim( $color, '#' );

        if ( preg_match( '/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/', $color ) ) {
            return '#' . strtolower( $color );
        }

        return '';
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( string $key ): string {
        return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '' );
    }
}

if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( string $title ): string {
        $title = strtolower( trim( $title ) );
        $title = preg_replace( '/[^a-z0-9\- ]/', '', $title ) ?? '';
        $title = preg_replace( '/\s+/', '-', $title ) ?? '';

        return trim( $title, '-' );
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( string $url ): string {
        return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( string $text ): string {
        return $text;
    }
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $maybeint ): int {
        return abs( (int) $maybeint );
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( string $email ): string {
        return filter_var( $email, FILTER_SANITIZE_EMAIL ) ?: '';
    }
}

if ( ! function_exists( 'is_email' ) ) {
    function is_email( string $email ): bool {
        return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        return $value;
    }
}

if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
    function rest_sanitize_boolean( $value ): bool {
        return (bool) $value;
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( string $tag, $value ) {
        return $value;
    }
}

if ( ! function_exists( 'sanitize_user' ) ) {
    function sanitize_user( string $username ): string {
        return preg_replace( '/[^a-z0-9_\-\.]/i', '', strtolower( $username ) ) ?? '';
    }
}

if ( ! function_exists( 'username_exists' ) ) {
    function username_exists( string $username ): bool {
        return false;
    }
}

if ( ! function_exists( 'wp_generate_password' ) ) {
    function wp_generate_password(): string {
        return 'password';
    }
}

if ( ! function_exists( 'wp_insert_user' ) ) {
    function wp_insert_user( array $userdata ) {
        return rand( 1000, 9999 );
    }
}

if ( ! function_exists( 'get_user_by' ) ) {
    function get_user_by( string $field, int $value ) {
        return null;
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( string $option, $default = false ) {
        return $default ?: 'HUF';
    }
}

if ( ! function_exists( 'wp_timezone' ) ) {
    function wp_timezone(): \DateTimeZone {
        return new \DateTimeZone( 'UTC' );
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private string $code;
        private string $message;

        public function __construct( string $code = '', string $message = '' ) {
            $this->code    = $code;
            $this->message = $message;
        }

        public function get_error_message(): string {
            return $this->message;
        }

        public function get_error_code(): string {
            return $this->code;
        }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ): bool {
        return $thing instanceof WP_Error;
    }
}
