<?php
/**
 * Customer business logic layer.
 *
 * @package SmoothBooking\Domain\Customers
 */

namespace SmoothBooking\Domain\Customers;

use SmoothBooking\Infrastructure\Logging\Logger;
use WP_Error;
use WP_User;

use function __;
use function absint;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function apply_filters;
use function explode;
use function get_user_by;
use function is_email;
use function is_wp_error;
use function preg_match;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sanitize_user;
use function sprintf;
use function strstr;
use function username_exists;
use function wp_generate_password;
use function wp_insert_user;
use function wp_parse_args;
use function wp_unslash;

/**
 * Provides validation and orchestration for customer operations.
 */
class CustomerService {
    /**
     * Repository instance.
     */
    private CustomerRepositoryInterface $repository;

    /**
     * Tag repository.
     */
    private CustomerTagRepositoryInterface $tag_repository;

    /**
     * Logger instance.
     */
    private Logger $logger;

    /**
     * Constructor.
     */
    public function __construct( CustomerRepositoryInterface $repository, CustomerTagRepositoryInterface $tag_repository, Logger $logger ) {
        $this->repository     = $repository;
        $this->tag_repository = $tag_repository;
        $this->logger         = $logger;
    }

    /**
     * Paginate customers list with filters.
     *
     * @param array<string, mixed> $args Arguments.
     *
     * @return array<string, mixed>
     */
    public function paginate_customers( array $args = [] ): array {
        $defaults = [
            'paged'          => 1,
            'per_page'       => 20,
            'search'         => '',
            'orderby'        => 'name',
            'order'          => 'ASC',
            'include_deleted'=> false,
            'only_deleted'   => false,
        ];

        $args = wp_parse_args( $args, $defaults );

        $result    = $this->repository->paginate( $args );
        $customers = $this->attach_tags( $result['customers'] );

        /**
         * Filter the customers list result.
         *
         * @hook smooth_booking_customers_paginated
         * @since 0.6.0
         *
         * @param array<string, mixed> $response Response data.
         * @param array<string, mixed> $args     Query args.
         */
        return apply_filters(
            'smooth_booking_customers_paginated',
            [
                'customers' => $customers,
                'total'     => (int) $result['total'],
                'per_page'  => (int) $args['per_page'],
                'paged'     => (int) $args['paged'],
            ],
            $args
        );
    }

    /**
     * Retrieve a customer by identifier.
     *
     * @return Customer|WP_Error
     */
    public function get_customer( int $customer_id ) {
        $customer = $this->repository->find_with_deleted( $customer_id );

        if ( null === $customer ) {
            return new WP_Error(
                'smooth_booking_customer_not_found',
                __( 'The requested customer could not be found.', 'smooth-booking' )
            );
        }

        return $this->enrich_customer( $customer );
    }

    /**
     * Create a new customer record.
     *
     * @param array<string, mixed> $data Submitted data.
     *
     * @return Customer|WP_Error
     */
    public function create_customer( array $data ) {
        $validated = $this->validate_customer_data( $data );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $record   = $validated['record'];
        $tag_data = $validated['tags'];

        $user_id = $this->resolve_user_assignment( $validated );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $record['user_id'] = $user_id;

        $result = $this->repository->create( $record );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed creating customer: %s', $result->get_error_message() ) );
            return $result;
        }

        $tag_ids = $this->resolve_tag_ids( $tag_data );
        $this->repository->sync_tags( $result->get_id(), $tag_ids );

        return $this->enrich_customer( $result );
    }

    /**
     * Update an existing customer record.
     *
     * @param int                   $customer_id Customer identifier.
     * @param array<string, mixed> $data        Submitted data.
     *
     * @return Customer|WP_Error
     */
    public function update_customer( int $customer_id, array $data ) {
        $existing = $this->repository->find_with_deleted( $customer_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_customer_not_found',
                __( 'The requested customer could not be found.', 'smooth-booking' )
            );
        }

        $validated = $this->validate_customer_data( $data, $existing );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $record   = $validated['record'];
        $tag_data = $validated['tags'];

        $user_id = $this->resolve_user_assignment( $validated, $existing );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $record['user_id'] = $user_id;

        $result = $this->repository->update( $customer_id, $record );

        if ( is_wp_error( $result ) ) {
            $this->logger->error( sprintf( 'Failed updating customer #%d: %s', $customer_id, $result->get_error_message() ) );
            return $result;
        }

        $tag_ids = $this->resolve_tag_ids( $tag_data );
        $this->repository->sync_tags( $customer_id, $tag_ids );

        return $this->enrich_customer( $result );
    }

    /**
     * Soft delete a customer.
     *
     * @return true|WP_Error
     */
    public function delete_customer( int $customer_id ) {
        $existing = $this->repository->find( $customer_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_customer_not_found',
                __( 'The requested customer could not be found.', 'smooth-booking' )
            );
        }

        return $this->repository->soft_delete( $customer_id );
    }

    /**
     * Restore a soft deleted customer.
     *
     * @return Customer|WP_Error
     */
    public function restore_customer( int $customer_id ) {
        $existing = $this->repository->find_with_deleted( $customer_id );

        if ( null === $existing ) {
            return new WP_Error(
                'smooth_booking_customer_not_found',
                __( 'The requested customer could not be found.', 'smooth-booking' )
            );
        }

        $restored = $this->repository->restore( $customer_id );

        if ( is_wp_error( $restored ) ) {
            return $restored;
        }

        return $this->enrich_customer( $restored );
    }

    /**
     * Retrieve all tags.
     *
     * @return CustomerTag[]
     */
    public function list_tags(): array {
        return $this->tag_repository->all();
    }

    /**
     * Validate incoming customer data.
     *
     * @param Customer|null $customer Existing customer for comparison.
     *
     * @return array<string, mixed>|WP_Error
     */
    private function validate_customer_data( array $data, ?Customer $customer = null ) {
        $record = [];

        $name = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( (string) $data['name'] ) ) : '';

        if ( '' === $name ) {
            return new WP_Error(
                'smooth_booking_customer_invalid_name',
                __( 'Customer name is required.', 'smooth-booking' )
            );
        }

        $record['name'] = $name;

        $record['first_name']         = isset( $data['first_name'] ) ? sanitize_text_field( wp_unslash( (string) $data['first_name'] ) ) : '';
        $record['last_name']          = isset( $data['last_name'] ) ? sanitize_text_field( wp_unslash( (string) $data['last_name'] ) ) : '';
        $record['phone']              = isset( $data['phone'] ) ? sanitize_text_field( wp_unslash( (string) $data['phone'] ) ) : '';
        $record['email']              = isset( $data['email'] ) ? sanitize_email( wp_unslash( (string) $data['email'] ) ) : '';
        $record['profile_image_id']   = isset( $data['profile_image_id'] ) ? absint( $data['profile_image_id'] ) : 0;
        $record['country']            = isset( $data['country'] ) ? sanitize_text_field( wp_unslash( (string) $data['country'] ) ) : '';
        $record['state_region']       = isset( $data['state_region'] ) ? sanitize_text_field( wp_unslash( (string) $data['state_region'] ) ) : '';
        $record['postal_code']        = isset( $data['postal_code'] ) ? sanitize_text_field( wp_unslash( (string) $data['postal_code'] ) ) : '';
        $record['city']               = isset( $data['city'] ) ? sanitize_text_field( wp_unslash( (string) $data['city'] ) ) : '';
        $record['street_address']     = isset( $data['street_address'] ) ? sanitize_text_field( wp_unslash( (string) $data['street_address'] ) ) : '';
        $record['additional_address'] = isset( $data['additional_address'] ) ? sanitize_text_field( wp_unslash( (string) $data['additional_address'] ) ) : '';
        $record['street_number']      = isset( $data['street_number'] ) ? sanitize_text_field( wp_unslash( (string) $data['street_number'] ) ) : '';
        $record['notes']              = isset( $data['notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $data['notes'] ) ) : '';

        $date_of_birth = isset( $data['date_of_birth'] ) ? sanitize_text_field( wp_unslash( (string) $data['date_of_birth'] ) ) : '';

        if ( '' !== $date_of_birth && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_of_birth ) ) {
            return new WP_Error(
                'smooth_booking_customer_invalid_birthdate',
                __( 'Date of birth must use the YYYY-MM-DD format.', 'smooth-booking' )
            );
        }

        $record['date_of_birth'] = $date_of_birth;

        $user_action = isset( $data['user_action'] ) ? sanitize_key( wp_unslash( (string) $data['user_action'] ) ) : 'none';

        $email = $record['email'];
        if ( 'create' === $user_action && ! is_email( $email ) ) {
            return new WP_Error(
                'smooth_booking_customer_invalid_user_email',
                __( 'A valid email address is required to create a WordPress user.', 'smooth-booking' )
            );
        }

        $tag_ids = isset( $data['tag_ids'] ) ? array_map( 'absint', (array) $data['tag_ids'] ) : [];
        $new_tags = isset( $data['new_tags'] ) ? sanitize_text_field( wp_unslash( (string) $data['new_tags'] ) ) : '';

        return [
            'record'     => $record,
            'tags'       => [
                'existing' => array_filter( array_map( 'absint', $tag_ids ) ),
                'new'      => $new_tags,
            ],
            'user_action' => $user_action,
            'existing_user_id' => isset( $data['existing_user_id'] ) ? absint( $data['existing_user_id'] ) : ( $customer ? ( $customer->get_user_id() ?? 0 ) : 0 ),
            'email' => $email,
            'first_name' => $record['first_name'],
            'last_name'  => $record['last_name'],
        ];
    }

    /**
     * Attach tag relations to the collection.
     *
     * @param Customer[] $customers Customers to enrich.
     *
     * @return Customer[]
     */
    private function attach_tags( array $customers ): array {
        if ( empty( $customers ) ) {
            return $customers;
        }

        $ids   = array_map(
            static function ( Customer $customer ): int {
                return $customer->get_id();
            },
            $customers
        );

        $tags = $this->repository->get_tags_for_customers( $ids );

        return array_map(
            static function ( Customer $customer ) use ( $tags ): Customer {
                $customer_tags = $tags[ $customer->get_id() ] ?? [];
                return $customer->with_tags( $customer_tags );
            },
            $customers
        );
    }

    /**
     * Enrich customer with relations.
     */
    private function enrich_customer( Customer $customer ): Customer {
        $tags = $this->repository->get_tags_for_customers( [ $customer->get_id() ] );
        $customer_tags = $tags[ $customer->get_id() ] ?? [];

        return $customer->with_tags( $customer_tags );
    }

    /**
     * Resolve tag identifiers from submitted data.
     *
     * @param array{existing: int[], new: string} $tag_data Tag submission data.
     *
     * @return int[]
     */
    private function resolve_tag_ids( array $tag_data ): array {
        $existing = array_filter( array_map( 'absint', $tag_data['existing'] ) );
        $new_tags = [];

        if ( ! empty( $tag_data['new'] ) ) {
            $candidates = array_filter( array_map( 'trim', explode( ',', $tag_data['new'] ) ) );

            foreach ( $candidates as $candidate ) {
                $tag = $this->tag_repository->create( $candidate );

                if ( $tag instanceof CustomerTag ) {
                    $new_tags[] = $tag->get_id();
                }
            }
        }

        $all = array_unique( array_filter( array_merge( $existing, $new_tags ) ) );

        return array_values( $all );
    }

    /**
     * Determine user assignment based on submission.
     *
     * @param array<string, mixed> $validated Validated payload.
     * @param Customer|null        $existing  Existing customer.
     *
     * @return int|null|WP_Error
     */
    private function resolve_user_assignment( array $validated, ?Customer $existing = null ) {
        $action = $validated['user_action'] ?? 'none';

        if ( 'create' === $action ) {
            $email = $validated['email'];

            if ( ! is_email( $email ) ) {
                return new WP_Error(
                    'smooth_booking_customer_invalid_user_email',
                    __( 'A valid email address is required to create a WordPress user.', 'smooth-booking' )
                );
            }

            $login_base = sanitize_user( $validated['first_name'] . '.' . $validated['last_name'], true );

            if ( '' === $login_base ) {
                $login_base = sanitize_user( strstr( $email, '@', true ) ?: $email, true );
            }

            $login = $login_base;
            $suffix = 1;

            while ( username_exists( $login ) ) {
                $login = $login_base . $suffix;
                $suffix++;
            }

            $user_id = wp_insert_user(
                [
                    'user_login' => $login,
                    'user_email' => $email,
                    'first_name' => $validated['first_name'],
                    'last_name'  => $validated['last_name'],
                    'user_pass'  => wp_generate_password(),
                    'role'       => 'subscriber',
                ]
            );

            if ( is_wp_error( $user_id ) ) {
                $this->logger->error( sprintf( 'Failed creating customer user: %s', $user_id->get_error_message() ) );
                return new WP_Error(
                    'smooth_booking_customer_user_create_failed',
                    __( 'Unable to create the WordPress user for this customer.', 'smooth-booking' )
                );
            }

            return (int) $user_id;
        }

        if ( 'assign' === $action ) {
            $user_id = (int) ( $validated['existing_user_id'] ?? 0 );

            if ( $user_id <= 0 ) {
                return new WP_Error(
                    'smooth_booking_customer_missing_user',
                    __( 'Please select an existing WordPress user.', 'smooth-booking' )
                );
            }

            $user = get_user_by( 'id', $user_id );

            if ( ! $user instanceof WP_User ) {
                return new WP_Error(
                    'smooth_booking_customer_invalid_user',
                    __( 'The selected WordPress user does not exist.', 'smooth-booking' )
                );
            }

            return $user_id;
        }

        if ( $existing ) {
            return $existing->get_user_id();
        }

        return null;
    }
}
