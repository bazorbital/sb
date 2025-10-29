<?php

namespace SmoothBooking\Tests\Unit\Domain\Notifications;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Domain\Notifications\EmailNotification;
use SmoothBooking\Domain\Notifications\EmailNotificationRepositoryInterface;
use SmoothBooking\Domain\Notifications\EmailNotificationService;
use SmoothBooking\Domain\Services\Service;
use SmoothBooking\Domain\Services\ServiceService;
use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;

/**
 * @covers \SmoothBooking\Domain\Notifications\EmailNotificationService
 */
class EmailNotificationServiceTest extends TestCase {
    public function test_create_notification_requires_name(): void {
        $service = $this->createService();

        $result = $service->create_notification( [ 'name' => '' ] );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_notification_invalid_name', $result->get_error_code() );
    }

    public function test_create_notification_requires_recipients(): void {
        $service = $this->createService();

        $result = $service->create_notification(
            [
                'name'       => 'Reminder',
                'recipients' => [],
            ]
        );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_notification_missing_recipient', $result->get_error_code() );
    }

    public function test_create_notification_validates_custom_emails(): void {
        $service = $this->createService();

        $result = $service->create_notification(
            [
                'name'        => 'Reminder',
                'recipients'  => [ 'custom' ],
                'custom_emails' => "invalid\nsecond@example.com",
            ]
        );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_notification_invalid_custom_email', $result->get_error_code() );
    }

    public function test_create_notification_requires_body_text_for_text_format(): void {
        $service = $this->createService();

        $result = $service->create_notification(
            [
                'name'          => 'Reminder',
                'recipients'    => [ 'client' ],
                'send_format'   => 'text',
                'body_text'     => '',
                'body_html'     => '',
            ]
        );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'smooth_booking_notification_missing_body_text', $result->get_error_code() );
    }

    public function test_create_notification_successful_payload(): void {
        $repository = new InMemoryEmailNotificationRepository();
        $service    = $this->createService( $repository );

        $result = $service->create_notification(
            [
                'name'               => 'New Booking',
                'recipients'         => [ 'client', 'custom' ],
                'custom_emails'      => "first@example.com\nsecond@example.com",
                'service_scope'      => 'selected',
                'service_ids'        => [ 101, 0, 102 ],
                'appointment_status' => 'approved',
                'send_format'        => 'html',
                'subject'            => 'Booking confirmed',
                'body_html'          => '<p>Hello</p>',
                'body_text'          => 'Hello',
                'attach_ics'         => '1',
            ]
        );

        $this->assertInstanceOf( EmailNotification::class, $result );
        $this->assertNotEmpty( $repository->created_payload, 'Repository should receive sanitized payload.' );

        $payload = $repository->created_payload;
        $this->assertSame( 'New Booking', $payload['display_name'] );
        $this->assertSame( 1, $payload['is_enabled'] );

        $conditions = json_decode( $payload['conditions_json'], true );
        $this->assertSame( 'selected', $conditions['service_scope'] );
        $this->assertSame( [ 101, 102 ], $conditions['service_ids'] );
        $this->assertSame( 'approved', $conditions['appointment_status'] );

        $settings = json_decode( $payload['settings_json'], true );
        $this->assertContains( 'client', $settings['recipients'] );
        $this->assertContains( 'custom', $settings['recipients'] );
        $this->assertSame(
            [ 'first@example.com', 'second@example.com' ],
            $settings['custom_emails']
        );
        $this->assertTrue( $settings['attach_ics'] );
    }

    private function createService( ?InMemoryEmailNotificationRepository $repository = null ): EmailNotificationService {
        $repository = $repository ?? new InMemoryEmailNotificationRepository();

        $service_service = $this->createMock( ServiceService::class );
        $service_service->method( 'list_services' )->willReturn( [
            Service::from_row(
                [
                    'service_id' => 101,
                    'name' => 'Massage',
                    'profile_image_id' => null,
                    'default_color' => '#111111',
                    'visibility' => 'public',
                    'price' => 50,
                    'payment_methods_mode' => 'all',
                    'info' => '',
                    'providers_preference' => 'any',
                    'providers_random_tie' => 0,
                    'occupancy_period_before' => 0,
                    'occupancy_period_after' => 0,
                    'duration_key' => 'PT30M',
                    'slot_length_key' => 'PT30M',
                    'padding_before_key' => 'PT0M',
                    'padding_after_key' => 'PT0M',
                    'online_meeting_provider' => 'none',
                    'limit_per_customer' => 'none',
                    'final_step_url_enabled' => 0,
                    'final_step_url' => null,
                    'min_time_prior_booking_key' => 'PT0M',
                    'min_time_prior_cancel_key' => 'PT0M',
                    'is_deleted' => 0,
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                ]
            ),
            Service::from_row(
                [
                    'service_id' => 102,
                    'name' => 'Consulting',
                    'profile_image_id' => null,
                    'default_color' => '#222222',
                    'visibility' => 'public',
                    'price' => 80,
                    'payment_methods_mode' => 'all',
                    'info' => '',
                    'providers_preference' => 'any',
                    'providers_random_tie' => 0,
                    'occupancy_period_before' => 0,
                    'occupancy_period_after' => 0,
                    'duration_key' => 'PT60M',
                    'slot_length_key' => 'PT30M',
                    'padding_before_key' => 'PT0M',
                    'padding_after_key' => 'PT0M',
                    'online_meeting_provider' => 'none',
                    'limit_per_customer' => 'none',
                    'final_step_url_enabled' => 0,
                    'final_step_url' => null,
                    'min_time_prior_booking_key' => 'PT0M',
                    'min_time_prior_cancel_key' => 'PT0M',
                    'is_deleted' => 0,
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00',
                ]
            ),
        ] );

        return new EmailNotificationService( $repository, $service_service, new Logger( 'test' ) );
    }
}

class InMemoryEmailNotificationRepository implements EmailNotificationRepositoryInterface {
    public array $created_payload = [];

    /** @var array<int, array<string, mixed>> */
    private array $rules = [];

    /** @var array<string, array<string, mixed>> */
    private array $templates = [];

    private int $counter = 1;

    public function list( bool $include_deleted = false ): array {
        unset( $include_deleted );

        return [];
    }

    public function find( int $notification_id, bool $include_deleted = false ): ?EmailNotification {
        unset( $include_deleted );

        if ( ! isset( $this->rules[ $notification_id ] ) ) {
            return null;
        }

        $rule = $this->rules[ $notification_id ];
        $tpl  = $this->templates[ $rule['template_code'] ] ?? [];

        return EmailNotification::from_row( array_merge( $rule, $tpl ) );
    }

    public function create( array $data ) {
        $this->created_payload = $data;

        $id                    = $this->counter++;
        $template_code         = $data['template_code'] ?? 'EMAIL_TEST';
        $this->rules[ $id ]    = array_merge(
            $data,
            [
                'rule_id'      => $id,
                'template_code'=> $template_code,
                'is_deleted'   => 0,
                'created_at'   => '2024-01-01 00:00:00',
                'updated_at'   => '2024-01-01 00:00:00',
            ]
        );
        $this->templates[ $template_code ] = array_merge(
            [
                'subject'   => '',
                'body_text' => '',
                'body_html' => '',
                'locale'    => 'en_US',
            ],
            $data['template'] ?? []
        );

        return $this->find( $id, true );
    }

    public function update( int $notification_id, array $data ) {
        unset( $notification_id, $data );

        return new WP_Error( 'not_implemented', 'Not required for this test.' );
    }

    public function soft_delete( int $notification_id ) {
        unset( $notification_id );

        return true;
    }

    public function force_delete( int $notification_id ) {
        unset( $notification_id );

        return true;
    }
}
