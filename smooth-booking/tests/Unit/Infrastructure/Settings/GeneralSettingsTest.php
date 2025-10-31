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
            ]
        );

        $this->assertSame(
            [
                'auto_repair_schema' => 1,
                'time_slot_length'   => 15,
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
            ]
        );

        $this->assertSame(
            [
                'auto_repair_schema' => 0,
                'time_slot_length'   => 30,
            ],
            $result
        );
    }
}
