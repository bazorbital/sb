<?php
/**
 * Tests for SchemaDefinitionBuilder.
 */

namespace SmoothBooking\Tests\Unit\Infrastructure\Database;

use PHPUnit\Framework\TestCase;
use SmoothBooking\Infrastructure\Database\SchemaDefinitionBuilder;

/**
 * @covers \SmoothBooking\Infrastructure\Database\SchemaDefinitionBuilder
 */
class SchemaDefinitionBuilderTest extends TestCase {
    public function test_build_tables_applies_prefix_and_collation(): void {
        $builder = new SchemaDefinitionBuilder();
        $tables  = $builder->build_tables( 'wp_', 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );

        $this->assertArrayHasKey( 'customers', $tables );
        $this->assertStringContainsString( 'CREATE TABLE wp_smooth_customers', $tables['customers'] );
        $this->assertStringContainsString( 'ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $tables['customers'] );
    }
}
