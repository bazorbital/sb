<?php

namespace SmoothBooking\Tests\Unit\Infrastructure\Repository;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Infrastructure\Logging\Logger;
use SmoothBooking\Infrastructure\Repository\EmailNotificationRepository;
use WP_Error;

/**
 * @covers \SmoothBooking\Infrastructure\Repository\EmailNotificationRepository
 */
class EmailNotificationRepositoryTest extends TestCase {
    public function test_create_inserts_template_and_rule(): void {
        $wpdb    = new RecordingWpdb();
        $logger  = new Logger( 'test' );
        $repo    = new EmailNotificationRepository( $wpdb, $logger );

        $result = $repo->create( $this->samplePayload() );

        $this->assertNotInstanceOf( WP_Error::class, $result );
        $this->assertCount( 1, $wpdb->channels, 'Email channel should be created.' );
        $this->assertCount( 1, $wpdb->templates );
        $this->assertCount( 1, $wpdb->rules );
        $this->assertSame( 'Reminder', $result->get_name() );
    }

    public function test_list_excludes_deleted_by_default(): void {
        $wpdb   = new RecordingWpdb();
        $logger = new Logger( 'test' );
        $repo   = new EmailNotificationRepository( $wpdb, $logger );

        $repo->create( $this->samplePayload( 'First' ) );
        $second = $repo->create( $this->samplePayload( 'Second' ) );
        $this->assertNotInstanceOf( WP_Error::class, $second );

        $repo->soft_delete( $second->get_id() );

        $active = $repo->list();
        $all    = $repo->list( true );

        $this->assertCount( 1, $active );
        $this->assertCount( 2, $all );
    }

    public function test_force_delete_removes_records(): void {
        $wpdb   = new RecordingWpdb();
        $logger = new Logger( 'test' );
        $repo   = new EmailNotificationRepository( $wpdb, $logger );

        $created = $repo->create( $this->samplePayload() );
        $this->assertNotInstanceOf( WP_Error::class, $created );

        $repo->force_delete( $created->get_id() );

        $this->assertCount( 0, $wpdb->rules );
        $this->assertCount( 0, $wpdb->templates );
    }

    private function samplePayload( string $name = 'Reminder' ): array {
        return [
            'display_name'        => $name,
            'is_enabled'          => 1,
            'trigger_event'       => 'booking.created',
            'schedule_offset_sec' => 0,
            'channel_order'       => 'email',
            'conditions_json'     => wp_json_encode(
                [
                    'appointment_status' => 'any',
                    'service_scope'      => 'any',
                    'service_ids'        => [],
                ]
            ),
            'settings_json'       => wp_json_encode(
                [
                    'recipients'    => [ 'client' ],
                    'custom_emails' => [],
                    'send_format'   => 'html',
                    'attach_ics'    => false,
                ]
            ),
            'priority'            => 100,
            'location_id'         => null,
            'template'            => [
                'subject'   => 'Hello',
                'body_html' => '<p>Hello</p>',
                'body_text' => 'Hello',
                'locale'    => 'en_US',
            ],
        ];
    }
}

class RecordingWpdb extends \wpdb {
    public array $channels = [];
    public array $templates = [];
    public array $rules = [];

    private int $channel_counter = 1;
    private int $rule_counter    = 1;

    public function get_var( $prepared ) {
        $data = $this->parse_prepared( $prepared );

        if ( str_contains( $data['query'], 'smooth_notification_channels' ) ) {
            foreach ( $this->channels as $row ) {
                if ( 'email' === $row['code'] ) {
                    return $row['channel_id'];
                }
            }
        }

        return null;
    }

    public function insert( $table, $data, $format = null ) {
        unset( $format );

        if ( str_contains( $table, 'smooth_notification_channels' ) ) {
            $data['channel_id']      = $this->channel_counter++;
            $this->channels[]        = $data;
            $this->insert_id         = $data['channel_id'];

            return true;
        }

        if ( str_contains( $table, 'smooth_notification_templates' ) ) {
            $this->templates[ $data['code'] ] = $data;
            $this->insert_id                 = 1;

            return true;
        }

        if ( str_contains( $table, 'smooth_notification_rules' ) ) {
            $data['rule_id']           = $this->rule_counter++;
            $data['is_deleted']        = $data['is_deleted'] ?? 0;
            $this->rules[ $data['rule_id'] ] = $data;
            $this->insert_id           = $data['rule_id'];

            return true;
        }

        return false;
    }

    public function update( $table, $data, $where, $format = null, $where_format = null ) {
        unset( $format, $where_format );

        if ( str_contains( $table, 'smooth_notification_rules' ) ) {
            $id = $where['rule_id'] ?? 0;

            if ( isset( $this->rules[ $id ] ) ) {
                $this->rules[ $id ] = array_merge( $this->rules[ $id ], $data );

                return true;
            }
        }

        if ( str_contains( $table, 'smooth_notification_templates' ) ) {
            $code = $where['code'] ?? '';

            if ( isset( $this->templates[ $code ] ) ) {
                $this->templates[ $code ] = array_merge( $this->templates[ $code ], $data );

                return true;
            }
        }

        return false;
    }

    public function delete( $table, $where, $where_format = null ) {
        unset( $where_format );

        if ( str_contains( $table, 'smooth_notification_rules' ) ) {
            $id = $where['rule_id'] ?? 0;
            unset( $this->rules[ $id ] );

            return true;
        }

        if ( str_contains( $table, 'smooth_notification_templates' ) ) {
            $code = $where['code'] ?? '';
            unset( $this->templates[ $code ] );

            return true;
        }

        return false;
    }

    public function get_results( $prepared, $output = ARRAY_A ) {
        unset( $output );
        $data       = $this->parse_prepared( $prepared );
        $channel_id = $data['args'][0] ?? null;
        $filter_deleted = str_contains( $data['query'], 'r.is_deleted = %d' );
        $deleted_value  = $filter_deleted ? (int) end( $data['args'] ) : null;

        $rows = [];

        foreach ( $this->rules as $rule ) {
            $template = $this->templates[ $rule['template_code'] ] ?? null;

            if ( null === $template ) {
                continue;
            }

            if ( null !== $channel_id && (int) $template['channel_id'] !== (int) $channel_id ) {
                continue;
            }

            if ( $filter_deleted && (int) $rule['is_deleted'] !== $deleted_value ) {
                continue;
            }

            $rows[] = array_merge( $rule, $template );
        }

        return $rows;
    }

    public function get_row( $prepared, $output = ARRAY_A, $y = 0 ) {
        $results = $this->get_results( $prepared, $output );

        return $results[ $y ] ?? null;
    }

    private function parse_prepared( $prepared ): array {
        if ( is_array( $prepared ) && isset( $prepared['query'], $prepared['args'] ) ) {
            return $prepared;
        }

        return [
            'query' => (string) $prepared,
            'args'  => [],
        ];
    }
}
