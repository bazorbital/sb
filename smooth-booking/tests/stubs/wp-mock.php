<?php
/**
 * Minimal WordPress stubs for PHPUnit without WP core.
 */

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! isset( $GLOBALS['smooth_booking_test_options'] ) ) {
    $GLOBALS['smooth_booking_test_options'] = [];
}

if ( ! isset( $GLOBALS['smooth_booking_wp_mail_should_fail'] ) ) {
    $GLOBALS['smooth_booking_wp_mail_should_fail'] = false;
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( string $text, string $domain = 'default' ): string {
        return $text;
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( string $text ): string {
        return $text;
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( string $text ): string {
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

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( string $path = '' ): string {
        return '/wp-admin/' . ltrim( $path, '/' );
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

if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( string $show = '', string $filter = 'raw' ): string {
        unset( $filter );

        return match ( $show ) {
            'name'        => 'Smooth Booking',
            'admin_email' => 'admin@example.com',
            default       => 'Smooth Booking',
        };
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

if ( ! function_exists( 'do_action' ) ) {
    function do_action( string $tag, ...$args ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        // No-op during tests.
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        return true;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        return true;
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
        return $GLOBALS['smooth_booking_test_options'][ $option ] ?? $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( string $option, $value ): bool {
        $GLOBALS['smooth_booking_test_options'][ $option ] = $value;

        return true;
    }
}

if ( ! function_exists( 'wp_timezone' ) ) {
    function wp_timezone(): \DateTimeZone {
        return new \DateTimeZone( 'UTC' );
    }
}

if ( ! function_exists( 'plugins_url' ) ) {
    function plugins_url( string $path = '', string $plugin = '' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        return '/wp-content/plugins/smooth-booking/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'get_user_locale' ) ) {
    function get_user_locale(): string {
        return 'en_US';
    }
}

if ( ! function_exists( 'wp_date' ) ) {
    function wp_date( string $format, int $timestamp, ?\DateTimeZone $timezone = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        if ( null !== $timezone ) {
            $date = new \DateTimeImmutable( '@' . $timestamp );
            $date = $date->setTimezone( $timezone );

            return $date->format( $format );
        }

        return gmdate( $format, $timestamp );
    }
}

if ( ! function_exists( 'selected' ) ) {
    function selected( $value, $current, bool $echo = true ) {
        $result = (string) $value === (string) $current ? 'selected="selected"' : '';

        if ( $echo ) {
            echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return $result;
    }
}

if ( ! function_exists( 'wpautop' ) ) {
    function wpautop( string $text ): string {
        return '<p>' . $text . '</p>';
    }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
        $stripped = strip_tags( $text );

        if ( $remove_breaks ) {
            $stripped = preg_replace( '/[\r\n\t]+/', ' ', $stripped ) ?? '';
        }

        return trim( $stripped );
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
        return json_encode( $data, $options | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, $depth );
    }
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        // Intentionally left blank for tests.
    }
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( string $handle, string $src = '', array $deps = [], $ver = false, bool $in_footer = false ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        // Intentionally left blank for tests.
    }
}

if ( ! function_exists( 'wp_mail' ) ) {
    function wp_mail( string $to, string $subject, string $message ) {
        unset( $subject, $message );

        if ( $GLOBALS['smooth_booking_wp_mail_should_fail'] ) {
            return false;
        }

        return is_email( $to );
    }
}

if ( ! function_exists( '_n' ) ) {
    function _n( string $single, string $plural, int $number, string $domain = 'default' ): string { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        return 1 === $number ? $single : $plural;
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( string $type ) {
        unset( $type );

        return gmdate( 'Y-m-d H:i:s' );
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( string $capability ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        return true;
    }
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message = '' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        throw new \RuntimeException( is_string( $message ) ? $message : 'wp_die' );
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

if ( ! class_exists( 'wpdb' ) ) {
    class wpdb {
        public string $prefix = 'wp_';
        public string $last_error = '';
        public int $insert_id = 0;

        public function prepare( $query, $args = null ) {
            if ( null === $args ) {
                $args = array_slice( func_get_args(), 1 );
            }

            return [
                'query' => $query,
                'args'  => is_array( $args ) ? $args : [ $args ],
            ];
        }

        public function get_var( $query ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
            return null;
        }

        public function insert( $table, $data, $format = null ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
            return false;
        }

        public function update( $table, $data, $where, $format = null, $where_format = null ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
            return false;
        }

        public function delete( $table, $where, $where_format = null ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
            return false;
        }

        public function get_results( $query, $output = ARRAY_A ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
            return [];
        }

        public function get_row( $query, $output = ARRAY_A, $y = 0 ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
            $results = $this->get_results( $query, $output );

            return $results[0] ?? null;
        }
    }
}
