<?php

namespace SmoothBooking\Tests\Unit\Domain\Notifications;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Notifications\EmailSettingsService;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

/**
 * @covers \SmoothBooking\Domain\Notifications\EmailSettingsService
 */
class EmailSettingsServiceTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['smooth_booking_test_options'] = [];
        $GLOBALS['smooth_booking_wp_mail_should_fail'] = false;
    }

    public function test_sanitize_settings_preserves_existing_password_and_validates(): void {
        $service = new EmailSettingsService( new Logger( 'test' ) );

        update_option(
            'smooth_booking_email_settings',
            [
                'sender_name' => 'Existing',
                'sender_email' => 'sender@example.com',
                'mail_gateway' => 'smtp',
                'smtp' => [
                    'host' => 'smtp.example.com',
                    'password' => 'stored-secret',
                    'username' => 'smtp-user',
                    'secure' => 'ssl',
                    'port' => '465',
                ],
            ]
        );

        $sanitized = $service->sanitize_settings(
            [
                'sender_name' => '  New Name  ',
                'sender_email' => 'invalid-email',
                'mail_gateway' => 'smtp',
                'retry_period_hours' => 5,
                'reply_to_customer' => '1',
                'smtp' => [
                    'host' => 'smtp.newhost.test',
                    'password' => '',
                    'username' => 'new-user',
                    'secure' => 'tls',
                ],
            ]
        );

        $this->assertSame( 'New Name', $sanitized['sender_name'] );
        $this->assertSame( 'admin@example.com', $sanitized['sender_email'], 'Invalid email should fallback to admin email.' );
        $this->assertSame( 5, $sanitized['retry_period_hours'] );
        $this->assertSame( 'smtp', $sanitized['mail_gateway'] );
        $this->assertSame( 'smtp.newhost.test', $sanitized['smtp']['host'] );
        $this->assertSame( 'stored-secret', $sanitized['smtp']['password'], 'Existing password should persist when left blank.' );
        $this->assertSame( 'new-user', $sanitized['smtp']['username'] );
        $this->assertSame( 'tls', $sanitized['smtp']['secure'] );
        $this->assertSame( 1, $sanitized['reply_to_customer'] );
    }

    public function test_send_test_email_requires_valid_recipient(): void {
        $service = new EmailSettingsService( new Logger( 'test' ) );

        $result = $service->send_test_email( 'not-an-email' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_invalid_test_email', $result->get_error_code() );
    }

    public function test_send_test_email_reports_failure(): void {
        $service = new EmailSettingsService( new Logger( 'test' ) );
        $GLOBALS['smooth_booking_wp_mail_should_fail'] = true;

        $result = $service->send_test_email( 'recipient@example.com' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_test_email_failed', $result->get_error_code() );
    }

    public function test_send_test_email_success(): void {
        $service = new EmailSettingsService( new Logger( 'test' ) );

        $result = $service->send_test_email( 'recipient@example.com' );

        $this->assertTrue( $result );
    }
}
