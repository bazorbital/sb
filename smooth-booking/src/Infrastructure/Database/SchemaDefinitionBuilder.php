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
                name VARCHAR(191) NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                profile_image_id BIGINT UNSIGNED NULL,
                first_name VARCHAR(191) NULL,
                last_name VARCHAR(191) NULL,
                phone VARCHAR(75) NULL,
                email VARCHAR(191) NULL,
                date_of_birth DATE NULL,
                country VARCHAR(120) NULL,
                state_region VARCHAR(120) NULL,
                postal_code VARCHAR(30) NULL,
                city VARCHAR(120) NULL,
                street_address VARCHAR(191) NULL,
                additional_address VARCHAR(191) NULL,
                street_number VARCHAR(60) NULL,
                notes TEXT NULL,
                last_appointment_at DATETIME NULL,
                total_appointments INT UNSIGNED NOT NULL DEFAULT 0,
                total_payments DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (customer_id),
                KEY email (email),
                KEY phone (phone),
                KEY name (name),
                KEY user_lookup (user_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['customer_tags'] = sprintf(
            'CREATE TABLE %1$ssmooth_customer_tags (
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

        $tables['customer_tag_relationships'] = sprintf(
            'CREATE TABLE %1$ssmooth_customer_tag_relationships (
                customer_id BIGINT UNSIGNED NOT NULL,
                tag_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (customer_id, tag_id),
                KEY tag_lookup (tag_id, customer_id)
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
                price_override DECIMAL(10,2) NULL,
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
                profile_image_id BIGINT UNSIGNED NULL,
                address VARCHAR(255) NULL,
                phone VARCHAR(50) NULL,
                base_email VARCHAR(150) NULL,
                website VARCHAR(255) NULL,
                timezone VARCHAR(64) NOT NULL DEFAULT \'Europe/Budapest\',
                industry_id INT NOT NULL DEFAULT 0,
                is_event_location TINYINT(1) NOT NULL DEFAULT 0,
                company_name VARCHAR(150) NULL,
                company_address VARCHAR(255) NULL,
                company_phone VARCHAR(50) NULL,
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

        $tables['location_holidays'] = sprintf(
            'CREATE TABLE %1$ssmooth_location_holidays (
                holiday_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                location_id BIGINT UNSIGNED NOT NULL,
                holiday_date DATE NOT NULL,
                note VARCHAR(255) NOT NULL,
                is_recurring TINYINT(1) NOT NULL DEFAULT 0,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (holiday_id),
                UNIQUE KEY location_date_unique (location_id, holiday_date, is_recurring)
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

        $tables['employee_breaks'] = sprintf(
            'CREATE TABLE %1$ssmooth_employee_breaks (
                break_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                employee_id BIGINT UNSIGNED NOT NULL,
                day_of_week TINYINT NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (break_id),
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
                service_id BIGINT UNSIGNED NULL,
                customer_id BIGINT UNSIGNED NULL,
                employee_id BIGINT UNSIGNED NULL,
                location_id BIGINT UNSIGNED NULL,
                scheduled_start DATETIME NOT NULL,
                scheduled_end DATETIME NOT NULL,
                status ENUM(\'pending\',\'confirmed\',\'completed\',\'canceled\') NOT NULL DEFAULT \'pending\',
                payment_status ENUM(\'pending\',\'authorized\',\'paid\',\'refunded\',\'failed\',\'canceled\') NULL,
                notes TEXT NULL,
                internal_note TEXT NULL,
                total_amount DECIMAL(12,2) NULL,
                currency CHAR(3) NOT NULL DEFAULT \'HUF\',
                customer_email VARCHAR(191) NULL,
                customer_phone VARCHAR(75) NULL,
                should_notify TINYINT(1) NOT NULL DEFAULT 0,
                is_recurring TINYINT(1) NOT NULL DEFAULT 0,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (booking_id),
                KEY booking_lookup (booking_type, status, scheduled_start),
                KEY booking_customer (customer_id),
                KEY booking_employee (employee_id),
                KEY booking_service (service_id)
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

        $tables['notification_channels'] = sprintf(
            'CREATE TABLE %1$ssmooth_notification_channels (
                channel_id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(32) NOT NULL,
                description VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (channel_id),
                UNIQUE KEY channel_code (code)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['notification_recipients'] = sprintf(
            'CREATE TABLE %1$ssmooth_notification_recipients (
                recipient_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                wp_user_id BIGINT UNSIGNED NULL,
                email VARCHAR(320) NULL,
                phone_e164 VARCHAR(20) NULL,
                push_token VARCHAR(255) NULL,
                locale VARCHAR(16) NULL,
                timezone VARCHAR(64) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (recipient_id),
                UNIQUE KEY uq_wp_user (wp_user_id),
                KEY idx_email (email),
                KEY idx_phone (phone_e164)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['notification_templates'] = sprintf(
            'CREATE TABLE %1$ssmooth_notification_templates (
                template_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(64) NOT NULL,
                channel_id TINYINT UNSIGNED NOT NULL,
                locale VARCHAR(16) NOT NULL,
                template_lookup VARCHAR(191) NOT NULL,
                subject VARCHAR(191) NULL,
                body_text MEDIUMTEXT NULL,
                body_html MEDIUMTEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (template_id),
                UNIQUE KEY template_lookup (template_lookup),
                UNIQUE KEY uq_tpl (code, channel_id, locale),
                KEY tpl_channel (channel_id),
                FOREIGN KEY (channel_id) REFERENCES %1$ssmooth_notification_channels (channel_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['notification_rules'] = sprintf(
            'CREATE TABLE %1$ssmooth_notification_rules (
                rule_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                display_name VARCHAR(191) NOT NULL,
                template_code VARCHAR(64) NOT NULL,
                location_id BIGINT UNSIGNED NULL,
                trigger_event VARCHAR(64) NOT NULL,
                schedule_offset_sec INT NOT NULL DEFAULT 0,
                channel_order VARCHAR(128) NOT NULL DEFAULT \'push>email>sms\',
                conditions_json LONGTEXT NULL,
                settings_json LONGTEXT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                priority SMALLINT NOT NULL DEFAULT 100,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (rule_id),
                KEY idx_rule_trigger (trigger_event, is_enabled, priority),
                KEY idx_rule_template (template_code),
                KEY idx_rule_location (location_id),
                FOREIGN KEY (location_id) REFERENCES %1$ssmooth_locations (location_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['notification_send_jobs'] = sprintf(
            'CREATE TABLE %1$ssmooth_notification_send_jobs (
                send_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                rule_id BIGINT UNSIGNED NULL,
                recipient_id BIGINT UNSIGNED NOT NULL,
                channel_id TINYINT UNSIGNED NOT NULL,
                location_id BIGINT UNSIGNED NULL,
                address VARCHAR(320) NULL,
                subject VARCHAR(191) NULL,
                body_text MEDIUMTEXT NULL,
                body_html MEDIUMTEXT NULL,
                merge_vars_json LONGTEXT NULL,
                scheduled_at DATETIME NOT NULL,
                queued_at DATETIME NULL,
                status ENUM(\'scheduled\',\'queued\',\'sending\',\'sent\',\'failed\',\'canceled\',\'suppressed\') NOT NULL DEFAULT \'scheduled\',
                dedupe_key VARCHAR(64) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (send_id),
                UNIQUE KEY uq_send_dedupe (dedupe_key),
                KEY idx_send_status_time (status, scheduled_at),
                KEY idx_send_recipient (recipient_id, scheduled_at),
                KEY idx_send_rule (rule_id),
                FOREIGN KEY (rule_id) REFERENCES %1$ssmooth_notification_rules (rule_id),
                FOREIGN KEY (recipient_id) REFERENCES %1$ssmooth_notification_recipients (recipient_id),
                FOREIGN KEY (channel_id) REFERENCES %1$ssmooth_notification_channels (channel_id),
                FOREIGN KEY (location_id) REFERENCES %1$ssmooth_locations (location_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['notification_send_attempts'] = sprintf(
            'CREATE TABLE %1$ssmooth_notification_send_attempts (
                attempt_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                send_id BIGINT UNSIGNED NOT NULL,
                attempt_no SMALLINT UNSIGNED NOT NULL,
                provider_code VARCHAR(64) NULL,
                provider_message_id VARCHAR(191) NULL,
                requested_at DATETIME NOT NULL,
                status ENUM(\'pending\',\'accepted\',\'delivered\',\'soft_bounce\',\'hard_bounce\',\'failed\') NOT NULL DEFAULT \'pending\',
                response_code VARCHAR(64) NULL,
                response_body TEXT NULL,
                PRIMARY KEY  (attempt_id),
                UNIQUE KEY uq_attempt_per_send (send_id, attempt_no),
                KEY idx_attempt_provider (provider_message_id),
                FOREIGN KEY (send_id) REFERENCES %1$ssmooth_notification_send_jobs (send_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['notification_suppressions'] = sprintf(
            'CREATE TABLE %1$ssmooth_notification_suppressions (
                suppression_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                channel_id TINYINT UNSIGNED NOT NULL,
                address_hash BINARY(16) NOT NULL,
                reason VARCHAR(64) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (suppression_id),
                UNIQUE KEY uq_suppression (channel_id, address_hash),
                FOREIGN KEY (channel_id) REFERENCES %1$ssmooth_notification_channels (channel_id)
            ) %2$s;',
            $prefix,
            $options
        );

        $tables['notification_events'] = sprintf(
            'CREATE TABLE %1$ssmooth_notification_events (
                event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                send_id BIGINT UNSIGNED NULL,
                channel_id TINYINT UNSIGNED NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                payload LONGTEXT NULL,
                PRIMARY KEY  (event_id),
                KEY idx_event_lookup (channel_id, event_type, occurred_at),
                FOREIGN KEY (send_id) REFERENCES %1$ssmooth_notification_send_jobs (send_id)
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
