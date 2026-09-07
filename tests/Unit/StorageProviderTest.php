<?php

declare(strict_types=1);

namespace HM\SwrCache\Tests\Unit;

use HM\SwrCache\StorageProvider;
use HM\SwrCache\TransoptionStorageProvider;
use PHPUnit\Framework\TestCase;

class StorageProviderTest extends TestCase {

	public function testGetInstanceReturnsRequestedProviderType() : void {
		StorageProvider::get_instance( StorageProvider::CACHE );

		$this->assertInstanceOf( TransoptionStorageProvider::class, StorageProvider::get_instance( StorageProvider::TRANSOPTION ) );
	}
}
