<?php

declare(strict_types=1);

namespace HM\SwrCache\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use HM\SwrCache\CacheStorageProvider;
use HM\SwrCache\StorageProvider;
use HM\SwrCache\TransoptionStorageProvider;
use PHPUnit\Framework\TestCase;

use function HM\SwrCache\bootstrap;
use function HM\SwrCache\storage;

class BootstrapTest extends TestCase {

	protected function setUp() : void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubs( [ 'add_action' ] );
	}

	protected function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function testBootstrapDefaultsToCacheStorage() : void {
		bootstrap();

		$this->assertInstanceOf( CacheStorageProvider::class, storage() );
		$this->assertArrayNotHasKey( 'storage', $GLOBALS );
	}

	public function testBootstrapStorageIsFilterable() : void {
		Filters\expectApplied( 'hm.swrCache.storage' )->once()->with( StorageProvider::CACHE )->andReturn( StorageProvider::TRANSOPTION );

		bootstrap();

		$this->assertInstanceOf( TransoptionStorageProvider::class, storage() );
	}
}
