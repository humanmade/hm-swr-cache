<?php

declare(strict_types=1);

namespace HM\SwrCache\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use HM\SwrCache\TransoptionStorageProvider;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class TransoptionStorageProviderTest extends TestCase {
	use MockeryPHPUnitIntegration;

	private Mockery\MockInterface $wpdb;
	private array $deleted = [];

	protected function setUp() : void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb = Mockery::mock( 'wpdb' );
		$this->wpdb->options = 'wp_options';
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing( fn( $sql, $arg ) => str_replace( '%s', "'$arg'", $sql ) );
		$this->wpdb->shouldReceive( 'esc_like' )->andReturnUsing( fn( $s ) => addcslashes( $s, '_%\\' ) );
		$this->wpdb->shouldReceive( 'query' )->andReturnUsing( function ( $sql ) {
			$this->deleted[] = $sql;
			return 1;
		} );
		$GLOBALS['wpdb'] = $this->wpdb;
	}

	protected function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function testGetWithExpiryReadsExpiryFromTransient() : void {
		Functions\when( 'get_option' )->justReturn( 'data' );
		Functions\expect( 'get_transient' )->once()->with( 'grp-key_expiry' )->andReturn( 12345 );

		$provider = new TransoptionStorageProvider();

		$this->assertSame( [ 'data', 12345 ], $provider->get_with_expiry( 'key', 'grp' ) );
	}

	public function testDeleteGroupRefusesEmptyGroup() : void {
		$provider = new TransoptionStorageProvider();
		$provider->register_group( '' );

		$this->assertFalse( $provider->delete_group( '' ) );
		$this->assertSame( [], $this->deleted );
	}

	public function testDeleteGroupEscapesLikeAndRemovesTimeouts() : void {
		$provider = new TransoptionStorageProvider();
		$provider->register_group( 'my_grp' );

		$this->assertTrue( $provider->delete_group( 'my_grp' ) );
		$this->assertSame(
			[
				"DELETE FROM wp_options WHERE option_name LIKE 'my\\_grp-%'",
				"DELETE FROM wp_options WHERE option_name LIKE '\\_transient\\_my\\_grp-%'",
				"DELETE FROM wp_options WHERE option_name LIKE '\\_transient\\_timeout\\_my\\_grp-%'",
			],
			$this->deleted
		);
	}
}
