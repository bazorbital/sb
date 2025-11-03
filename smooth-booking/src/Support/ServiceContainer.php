<?php
/**
 * Simple service container.
 *
 * @package SmoothBooking\Support
 */

namespace SmoothBooking\Support;

use InvalidArgumentException;

/**
 * Basic service container supporting singleton bindings.
 */
class ServiceContainer {
    /**
     * Registered bindings.
     *
     * @var array<string, callable>
     */
    private array $bindings = [];

    /**
     * Singleton instances.
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Register a singleton binding.
     *
     * The factory callable should accept the container instance as its first
     * argument.
     *
     * @param string   $id      Service identifier or class FQCN.
     * @param callable $factory Factory that returns the concrete instance.
     *
     * @return void
     */
    public function singleton( string $id, callable $factory ): void {
        $this->bindings[ $id ] = $factory;
    }

    /**
     * Resolve a binding.
     *
     * @param string $id Class or service identifier.
     *
     * @throws InvalidArgumentException When service is not registered.
     *
     * @return mixed Resolved instance associated with the identifier.
     */
    public function get( string $id ) {
        if ( isset( $this->instances[ $id ] ) ) {
            return $this->instances[ $id ];
        }

        if ( ! isset( $this->bindings[ $id ] ) ) {
            throw new InvalidArgumentException( sprintf( 'Service %s is not registered.', $id ) );
        }

        $this->instances[ $id ] = $this->bindings[ $id ]( $this );

        return $this->instances[ $id ];
    }
}
