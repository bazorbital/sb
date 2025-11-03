<?php

declare(strict_types=1);

namespace SmoothBooking\Tests\Unit\Infrastructure\Settings;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Infrastructure\Settings\GeneralSettings;

class GeneralSettingsTest extends TestCase {
    public function test_sanitize_accepts_allowed_slot_length(): void {
        $settings = new GeneralSettings();

        $result = $settings->sanitize(
            [
                'auto_repair_schema' => 'yes',
                'time_slot_length'   => 15,
                'enable_debug_logging' => '1',
            ]
        );

        $this->assertSame(
            [
                'auto_repair_schema' => 1,
                'time_slot_length'   => 15,
                'enable_debug_logging' => 1,
            ],
            $result
        );
    }

    public function test_sanitize_discards_invalid_slot_length(): void {
        $settings = new GeneralSettings();

        $result = $settings->sanitize(
            [
                'auto_repair_schema' => '0',
                'time_slot_length'   => 17,
                'enable_debug_logging' => '',
            ]
        );

        $this->assertSame(
            [
                'auto_repair_schema' => 0,
                'time_slot_length'   => 30,
                'enable_debug_logging' => 0,
            ],
            $result
        );
    }

    public function test_is_debug_logging_enabled_reflects_option(): void {
        $settings = new GeneralSettings();

        $this->assertFalse( $settings->is_debug_logging_enabled() );

        $GLOBALS['smooth_booking_test_options'][ GeneralSettings::OPTION_NAME ] = [
            'enable_debug_logging' => 1,
        ];

        $this->assertTrue( $settings->is_debug_logging_enabled() );

        unset( $GLOBALS['smooth_booking_test_options'][ GeneralSettings::OPTION_NAME ] );
    }
}
