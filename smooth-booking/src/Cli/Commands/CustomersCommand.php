<?php
/**
 * WP-CLI commands for customer management.
 *
 * @package SmoothBooking\Cli\Commands
 */

namespace SmoothBooking\Cli\Commands;

use SmoothBooking\Domain\Customers\Customer;
use SmoothBooking\Domain\Customers\CustomerService;
use WP_CLI_Command;

use function WP_CLI\error;
use function WP_CLI\line;
use function WP_CLI\log;
use function WP_CLI\success;
use function is_wp_error;
use function sprintf;

/**
 * Provides customer commands for WP-CLI.
 */
class CustomersCommand extends WP_CLI_Command {
    /**
     * @var CustomerService
     */
    private CustomerService $service;

    /**
     * Constructor.
     */
    public function __construct( CustomerService $service ) {
        $this->service = $service;
    }

    /**
     * List customers.
     *
     * ## EXAMPLES
     *
     *     wp smooth customers list
     */
    public function list(): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeyWord
        $result = $this->service->paginate_customers();

        $customers = $result['customers'];

        if ( empty( $customers ) ) {
            log( 'No customers found.' );
            return;
        }

        foreach ( $customers as $customer ) {
            if ( ! $customer instanceof Customer ) {
                continue;
            }

            line( sprintf( '#%d %s <%s>', $customer->get_id(), $customer->get_name(), $customer->get_email() ?? 'n/a' ) );
        }
    }

    /**
     * Create a new customer.
     *
     * ## OPTIONS
     *
     * --name=<name>
     * : Full name of the customer.
     *
     * [--first-name=<first>]
     * : First name field.
     *
     * [--last-name=<last>]
     * : Last name field.
     *
     * [--email=<email>]
     * : Email address.
     *
     * [--phone=<phone>]
     * : Phone number.
     *
     * [--country=<country>]
     * : Country value.
     *
     * [--city=<city>]
     * : City value.
     *
     * ## EXAMPLES
     *
     *     wp smooth customers create --name="Acme Corp" --email=client@example.com
     */
    public function create( array $args, array $assoc_args ): void {
        if ( empty( $assoc_args['name'] ) ) {
            error( 'The --name option is required.' );
        }

        $data = [
            'name'           => $assoc_args['name'],
            'first_name'     => $assoc_args['first-name'] ?? '',
            'last_name'      => $assoc_args['last-name'] ?? '',
            'email'          => $assoc_args['email'] ?? '',
            'phone'          => $assoc_args['phone'] ?? '',
            'country'        => $assoc_args['country'] ?? '',
            'city'           => $assoc_args['city'] ?? '',
            'user_action'    => 'none',
            'tag_ids'        => [],
            'new_tags'       => $assoc_args['tags'] ?? '',
        ];

        $result = $this->service->create_customer( $data );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Customer #%d created.', $result->get_id() ) );
    }

    /**
     * Update an existing customer.
     *
     * ## OPTIONS
     *
     * <customer-id>
     * : The customer identifier.
     *
     * [--name=<name>]
     * : Updated name.
     *
     * [--email=<email>]
     * : Updated email.
     *
     * [--phone=<phone>]
     * : Updated phone.
     *
     * ## EXAMPLES
     *
     *     wp smooth customers update 12 --name="Contoso"
     */
    public function update( array $args, array $assoc_args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Customer ID is required.' );
        }

        $customer_id = (int) $args[0];
        $customer    = $this->service->get_customer( $customer_id );

        if ( is_wp_error( $customer ) ) {
            error( $customer->get_error_message() );
        }

        $data = [
            'name'       => $assoc_args['name'] ?? $customer->get_name(),
            'email'      => $assoc_args['email'] ?? ( $customer->get_email() ?? '' ),
            'phone'      => $assoc_args['phone'] ?? ( $customer->get_phone() ?? '' ),
            'first_name' => $assoc_args['first-name'] ?? ( $customer->get_first_name() ?? '' ),
            'last_name'  => $assoc_args['last-name'] ?? ( $customer->get_last_name() ?? '' ),
            'country'    => $assoc_args['country'] ?? ( $customer->get_country() ?? '' ),
            'city'       => $assoc_args['city'] ?? ( $customer->get_city() ?? '' ),
            'user_action'=> 'none',
            'tag_ids'    => [],
            'new_tags'   => $assoc_args['tags'] ?? '',
        ];

        $result = $this->service->update_customer( $customer_id, $data );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Customer #%d updated.', $result->get_id() ) );
    }

    /**
     * Soft delete a customer.
     *
     * ## OPTIONS
     *
     * <customer-id>
     * : The customer identifier.
     */
    public function delete( array $args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Customer ID is required.' );
        }

        $customer_id = (int) $args[0];

        $result = $this->service->delete_customer( $customer_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Customer #%d deleted.', $customer_id ) );
    }

    /**
     * Restore a customer.
     *
     * ## OPTIONS
     *
     * <customer-id>
     * : The customer identifier.
     */
    public function restore( array $args ): void {
        if ( empty( $args[0] ) ) {
            error( 'Customer ID is required.' );
        }

        $customer_id = (int) $args[0];

        $result = $this->service->restore_customer( $customer_id );

        if ( is_wp_error( $result ) ) {
            error( $result->get_error_message() );
        }

        success( sprintf( 'Customer #%d restored.', $result->get_id() ) );
    }
}
