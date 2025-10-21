<?php
/**
 * Generates SQL statements for Smooth Booking tables.
 *
 * @package SmoothBooking\Infrastructure\Database
 */

namespace SmoothBooking\Infrastructure\Database;

/**
 * Provides schema definition strings for dbDelta.
 */
class SchemaDefinitionBuilder {
    /**
     * Retrieve table create statements keyed by logical name.
     *
     * @param string $prefix  WordPress table prefix.
     * @param string $collate Collation string.
     *
     * @return array<string, string>
     */
    public function build_tables( string $prefix, string $collate ): array {
        $options = sprintf( 'ENGINE=InnoDB %s', $collate );

        $tables = [];

        $tables['customers'] = sprintf(
            'CREATE TABLE %1$ssmooth_customers (
                customer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(190) NULL,
                phone VARCHAR(50) NULL,
                category VARCHAR(50) NULL,
                contact_json JSON NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (customer_id),
                UNIQUE KEY email (email)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['employees'] = sprintf(
            'CREATE TABLE %1$ssmooth_employees (
                employee_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(190) NULL,
                phone VARCHAR(50) NULL,
                specialization VARCHAR(120) NULL,
                profile_image_id BIGINT UNSIGNED NULL,
                default_color CHAR(7) NULL,
                visibility ENUM(\'public\',\'private\',\'archived\') NOT NULL DEFAULT \'public\',
                available_online TINYINT(1) NOT NULL DEFAULT 0,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (employee_id),
                UNIQUE KEY email (email)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['employee_categories'] = sprintf(
            'CREATE TABLE %1$ssmooth_employee_categories (
                category_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                slug VARCHAR(160) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (category_id),
                UNIQUE KEY slug (slug)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['employee_category_relationships'] = sprintf(
            'CREATE TABLE %1$ssmooth_employee_category_relationships (
                employee_id BIGINT UNSIGNED NOT NULL,
                category_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (employee_id, category_id),
                KEY category_lookup (category_id, employee_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['services'] = sprintf(
            'CREATE TABLE %1$ssmooth_services (
                service_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                profile_image_id BIGINT UNSIGNED NULL,
                default_color CHAR(7) NULL,
                visibility ENUM(\'public\',\'private\') NOT NULL DEFAULT \'public\',
                price DECIMAL(12,2) NULL,
                payment_methods_mode ENUM(\'default\',\'custom\') NOT NULL DEFAULT \'default\',
                info TEXT NULL,
                providers_preference ENUM(\'most_expensive\',\'least_expensive\',\'specified_order\',\'least_occupied_day\',\'most_occupied_day\',\'least_occupied_period\',\'most_occupied_period\') NOT NULL DEFAULT \'specified_order\',
                providers_random_tie TINYINT(1) NOT NULL DEFAULT 0,
                occupancy_period_before INT NOT NULL DEFAULT 0,
                occupancy_period_after INT NOT NULL DEFAULT 0,
                duration_key VARCHAR(40) NOT NULL DEFAULT \'15_minutes\',
                slot_length_key VARCHAR(40) NOT NULL DEFAULT \'default\',
                padding_before_key VARCHAR(40) NOT NULL DEFAULT \'off\',
                padding_after_key VARCHAR(40) NOT NULL DEFAULT \'off\',
                online_meeting_provider ENUM(\'off\',\'zoom\',\'google_meet\') NOT NULL DEFAULT \'off\',
                limit_per_customer ENUM(\'off\',\'upcoming\',\'per_24_hours\',\'per_day\',\'per_7_days\',\'per_week\',\'per_30_days\',\'per_month\',\'per_365_days\',\'per_year\') NOT NULL DEFAULT \'off\',
                final_step_url_enabled TINYINT(1) NOT NULL DEFAULT 0,
                final_step_url TEXT NULL,
                min_time_prior_booking_key VARCHAR(40) NOT NULL DEFAULT \'default\',
                min_time_prior_cancel_key VARCHAR(40) NOT NULL DEFAULT \'default\',
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (service_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['service_categories'] = sprintf(
            'CREATE TABLE %1$ssmooth_service_categories (
                category_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                slug VARCHAR(160) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (category_id),
                UNIQUE KEY slug (slug)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['service_category_relationships'] = sprintf(
            'CREATE TABLE %1$ssmooth_service_category_relationships (
                service_id BIGINT UNSIGNED NOT NULL,
                category_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (service_id, category_id),
                KEY category_lookup (category_id, service_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['service_tags'] = sprintf(
            'CREATE TABLE %1$ssmooth_service_tags (
                tag_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                slug VARCHAR(160) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (tag_id),
                UNIQUE KEY slug (slug)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['service_tag_relationships'] = sprintf(
            'CREATE TABLE %1$ssmooth_service_tag_relationships (
                service_id BIGINT UNSIGNED NOT NULL,
                tag_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (service_id, tag_id),
                KEY tag_lookup (tag_id, service_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['service_providers'] = sprintf(
            'CREATE TABLE %1$ssmooth_service_providers (
                service_id BIGINT UNSIGNED NOT NULL,
                employee_id BIGINT UNSIGNED NOT NULL,
                provider_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (service_id, employee_id),
                KEY employee_lookup (employee_id, service_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['locations'] = sprintf(
            'CREATE TABLE %1$ssmooth_locations (
                location_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                address VARCHAR(255) NULL,
                is_event_location TINYINT(1) NOT NULL DEFAULT 1,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (location_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['employee_locations'] = sprintf(
            'CREATE TABLE %1$ssmooth_employee_locations (
                employee_id BIGINT UNSIGNED NOT NULL,
                location_id BIGINT UNSIGNED NOT NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (employee_id, location_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['opening_hours'] = sprintf(
            'CREATE TABLE %1$ssmooth_opening_hours (
                opening_hour_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                location_id BIGINT UNSIGNED NOT NULL,
                day_of_week TINYINT NOT NULL,
                open_time TIME NULL,
                close_time TIME NULL,
                is_closed TINYINT(1) NOT NULL DEFAULT 0,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (opening_hour_id),
                KEY location_day (location_id, day_of_week)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['employee_working_hours'] = sprintf(
            'CREATE TABLE %1$ssmooth_employee_working_hours (
                working_hour_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                employee_id BIGINT UNSIGNED NOT NULL,
                day_of_week TINYINT NOT NULL,
                start_time TIME NULL,
                end_time TIME NULL,
                is_off_day TINYINT(1) NOT NULL DEFAULT 0,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (working_hour_id),
                KEY employee_day (employee_id, day_of_week)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['special_hours'] = sprintf(
            'CREATE TABLE %1$ssmooth_special_hours (
                special_hour_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type ENUM(\'location\',\'employee\') NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                date DATE NOT NULL,
                open_time TIME NULL,
                close_time TIME NULL,
                is_closed TINYINT(1) NOT NULL DEFAULT 0,
                note VARCHAR(255) NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (special_hour_id),
                KEY entity_lookup (entity_type, entity_id, date)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['offerings'] = sprintf(
            'CREATE TABLE %1$ssmooth_offerings (
                offering_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type ENUM(\'service\',\'bundle\') NOT NULL,
                name VARCHAR(150) NOT NULL,
                description TEXT NULL,
                duration_minutes INT NULL,
                buffer_before_min INT NOT NULL DEFAULT 0,
                buffer_after_min INT NOT NULL DEFAULT 0,
                is_collaborative TINYINT(1) NOT NULL DEFAULT 0,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (offering_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['bundle_items'] = sprintf(
            'CREATE TABLE %1$ssmooth_bundle_items (
                bundle_id BIGINT UNSIGNED NOT NULL,
                item_offering_id BIGINT UNSIGNED NOT NULL,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (bundle_id, item_offering_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['taxes'] = sprintf(
            'CREATE TABLE %1$ssmooth_taxes (
                tax_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                rate_percent DECIMAL(5,2) NOT NULL,
                description VARCHAR(255) NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (tax_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['prices'] = sprintf(
            'CREATE TABLE %1$ssmooth_prices (
                price_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                offering_id BIGINT UNSIGNED NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT \'HUF\',
                base_price DECIMAL(12,2) NOT NULL,
                tax_id BIGINT UNSIGNED NULL,
                discount_price DECIMAL(12,2) NULL,
                discount_start DATETIME NULL,
                discount_end DATETIME NULL,
                valid_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                valid_to DATETIME NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (price_id),
                KEY offering_validity (offering_id, valid_from, valid_to)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['bookings'] = sprintf(
            'CREATE TABLE %1$ssmooth_bookings (
                booking_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_type ENUM(\'appointment\',\'event\',\'rental\',\'room\') NOT NULL,
                offering_id BIGINT UNSIGNED NULL,
                customer_id BIGINT UNSIGNED NULL,
                employee_id BIGINT UNSIGNED NULL,
                location_id BIGINT UNSIGNED NULL,
                scheduled_start DATETIME NOT NULL,
                scheduled_end DATETIME NOT NULL,
                status ENUM(\'pending\',\'confirmed\',\'completed\',\'canceled\') NOT NULL DEFAULT \'pending\',
                notes TEXT NULL,
                total_amount DECIMAL(12,2) NULL,
                currency CHAR(3) NOT NULL DEFAULT \'HUF\',
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (booking_id),
                KEY booking_lookup (booking_type, status, scheduled_start),
                KEY booking_customer (customer_id),
                KEY booking_employee (employee_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['coupons'] = sprintf(
            'CREATE TABLE %1$ssmooth_coupons (
                coupon_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(80) NOT NULL,
                description VARCHAR(255) NULL,
                discount_type ENUM(\'fixed\',\'percent\') NOT NULL DEFAULT \'fixed\',
                discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                max_redemptions INT NULL,
                expires_at DATETIME NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (coupon_id),
                UNIQUE KEY coupon_code (code)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['coupon_targets'] = sprintf(
            'CREATE TABLE %1$ssmooth_coupon_targets (
                coupon_target_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                coupon_id BIGINT UNSIGNED NOT NULL,
                target_scope ENUM(\'all\',\'offering\') NOT NULL,
                offering_id BIGINT UNSIGNED NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (coupon_target_id),
                KEY coupon_scope (coupon_id, target_scope)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['coupon_usages'] = sprintf(
            'CREATE TABLE %1$ssmooth_coupon_usages (
                coupon_usage_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                coupon_id BIGINT UNSIGNED NOT NULL,
                booking_id BIGINT UNSIGNED NULL,
                amount DECIMAL(12,2) NOT NULL,
                used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (coupon_usage_id),
                KEY coupon_usage (coupon_id, used_at)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['gift_card_types'] = sprintf(
            'CREATE TABLE %1$ssmooth_gift_card_types (
                gift_card_type_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                description TEXT NULL,
                default_value DECIMAL(12,2) NOT NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (gift_card_type_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['gift_cards'] = sprintf(
            'CREATE TABLE %1$ssmooth_gift_cards (
                gift_card_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                gift_card_type_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(60) NOT NULL,
                purchase_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                initial_value DECIMAL(12,2) NOT NULL,
                current_value DECIMAL(12,2) NOT NULL,
                status ENUM(\'active\',\'used\',\'expired\',\'blocked\') NOT NULL DEFAULT \'active\',
                purchaser_customer_id BIGINT UNSIGNED NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (gift_card_id),
                UNIQUE KEY gift_card_code (code)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['gift_card_transactions'] = sprintf(
            'CREATE TABLE %1$ssmooth_gift_card_transactions (
                transaction_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                gift_card_id BIGINT UNSIGNED NOT NULL,
                booking_id BIGINT UNSIGNED NULL,
                amount DECIMAL(12,2) NOT NULL,
                balance_after DECIMAL(12,2) NOT NULL,
                occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (transaction_id),
                KEY gift_card_usage (gift_card_id, occurred_at)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['payments'] = sprintf(
            'CREATE TABLE %1$ssmooth_payments (
                payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id BIGINT UNSIGNED NULL,
                payment_method ENUM(\'cash\',\'card\',\'transfer\',\'gift_card\',\'other\') NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT \'HUF\',
                gift_card_id BIGINT UNSIGNED NULL,
                coupon_id BIGINT UNSIGNED NULL,
                payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (payment_id),
                KEY payment_date (payment_date)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['notification_templates'] = sprintf(
            'CREATE TABLE %1$ssmooth_notification_templates (
                template_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_type ENUM(\'BookingCreated\',\'BookingCanceled\',\'BookingReminder\',\'BookingUpdated\') NOT NULL,
                recipient_type ENUM(\'customer\',\'employee\') NOT NULL,
                channel ENUM(\'email\',\'sms\',\'push\') NOT NULL,
                subject VARCHAR(200) NULL,
                body TEXT NOT NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (template_id),
                UNIQUE KEY template_lookup (event_type, recipient_type, channel)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['notification_settings'] = sprintf(
            'CREATE TABLE %1$ssmooth_notification_settings (
                setting_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_type ENUM(\'customer\',\'employee\') NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                enable_email TINYINT(1) NOT NULL DEFAULT 1,
                enable_sms TINYINT(1) NOT NULL DEFAULT 0,
                enable_push TINYINT(1) NOT NULL DEFAULT 0,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (setting_id),
                UNIQUE KEY user_pref (user_type, user_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['notification_queue'] = sprintf(
            'CREATE TABLE %1$ssmooth_notification_queue (
                notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id BIGINT UNSIGNED NULL,
                recipient_type ENUM(\'customer\',\'employee\') NOT NULL,
                recipient_id BIGINT UNSIGNED NOT NULL,
                channel ENUM(\'email\',\'sms\',\'push\') NOT NULL,
                subject VARCHAR(200) NULL,
                body TEXT NOT NULL,
                scheduled_at DATETIME NOT NULL,
                sent_at DATETIME NULL,
                status ENUM(\'pending\',\'sent\',\'failed\',\'canceled\') NOT NULL DEFAULT \'pending\',
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (notification_id),
                KEY queue_status (status, scheduled_at)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['event_log'] = sprintf(
            'CREATE TABLE %1$ssmooth_event_log (
                event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_type ENUM(\'BookingCreated\',\'BookingCanceled\',\'BookingReminder\',\'BookingUpdated\') NOT NULL,
                booking_id BIGINT UNSIGNED NULL,
                occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                payload_json JSON NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (event_id),
                KEY event_lookup (event_type, occurred_at)
            ) %2$s;',
            $prefix,
            $options
        );

        return $tables;
    }

    /**
     * Retrieve view creation statements.
     *
     * @param string $prefix WordPress table prefix.
     *
     * @return array<string, string>
     */
    public function build_views( string $prefix ): array {
        $payments_table      = $prefix . 'smooth_payments';
        $daily_revenue_table = $prefix . 'smooth_daily_revenue';

        return [
            'daily_revenue' => sprintf(
                'CREATE OR REPLACE VIEW %1$s AS
                SELECT DATE(payment_date) AS revenue_date,
                       currency,
                       SUM(amount) AS total_revenue,
                       COUNT(*) AS payment_count
                FROM %2$s
                WHERE is_deleted = 0
                GROUP BY DATE(payment_date), currency',
                $daily_revenue_table,
                $payments_table
            ),
        ];
    }
}
