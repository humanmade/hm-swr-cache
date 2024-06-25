<?php
/**
 * Abstract class to define a Storage Provider which can be implemented by a backend.
 */

namespace HM\SwrCache;

use InvalidArgumentException;

/**
 * Abstract class to define a Storage Provider for caching functionality.
 */
abstract class StorageProvider {
	public const CACHE = CacheStorageProvider::class;
	public const TRANSOPTION = TransoptionStorageProvider::class;
	private const ALLOWED_CACHE_STORAGE = [ StorageProvider::CACHE, StorageProvider::TRANSOPTION ];
	/** @var StorageProvider */
	private static StorageProvider $instance;

	/**
	 * Retrieves an instance of the cache provider based on the provider type.
	 *
	 * @param string $provider_type The type of cache provider to retrieve.
	 *
	 * @return self The instance of the cache provider.
	 * @throws InvalidArgumentException If the given provider type is not a valid class or cache storage type.
	 */
	public static function get_instance( string $provider_type ) : self {
		if ( ! isset( self::$instance ) || self::$instance === null ) {
			if ( ! class_exists( $provider_type ) ) {
				throw new InvalidArgumentException( "Class '$provider_type' does not exist" );
			}
			// TODO: allow for custom storage providers.
			if ( ! in_array( $provider_type, self::ALLOWED_CACHE_STORAGE, true ) ) {
				throw new InvalidArgumentException( sprintf( 'Cache storage type not valid. Expected one of %s', implode( ', ', self::ALLOWED_CACHE_STORAGE ) ) );
			}
			self::$instance = new $provider_type;
		}

		return self::$instance;
	}

	/**
	 * Deletes a cache group and allows for immediate regeneration.
	 *
	 * @param string $cache_group The cache group to be deleted.
	 *
	 * @return bool Whether the cache group was successfully deleted.
	 */
	abstract public function delete_group( string $cache_group ) : bool;

	/**
	 * Retrieves a cached form with its expiry time.
	 *
	 * @param string $cache_key The key of the cache to retrieve.
	 * @param string $cache_group Optional. The cache group. Default is empty string.
	 *
	 * @return array An array containing the cached contents (or false) and its expiry timestamp.
	 */
	abstract public function get_with_expiry( string $cache_key, string $cache_group = '' ) : array;

	/**
	 * Set data in cache with expiry and delete the lock transient.
	 *
	 * @param string $lock_key The name of the lock.
	 * @param mixed  $data The data to be cached.
	 * @param int    $cache_duration The expiry time for the cache in seconds.
	 * @param string $cache_key The key for the cache.
	 * @param string $cache_group Optional. The group for the cache. Default value is empty string.
	 */
	abstract public function set_with_expiry( string $lock_key, mixed $data, int $cache_duration, string $cache_key, string $cache_group = '' ) : void;

	/**
	 * Adds a lock key in the cache to avoid race conditions and enables synchronization between processes.
	 * It's only stored if the lock doesn't exist (or is expired).
	 *
	 * @param string $lock_key The unique key for the lock.
	 * @param string $cache_group The cache group where the lock key will be stored
	 * @param int    $lock_time The duration in seconds for which the lock will be held. Default is MINUTE_IN_SECONDS constant.
	 *
	 * @return string The generated lock value, unique per invocation, regardless whether the lock is added.
	 */
	abstract public function lock_add( string $lock_key, string $cache_group = '', int $lock_time = MINUTE_IN_SECONDS ) : string;

	/**
	 * Verifies the lock against the cached lock.
	 *
	 * @param string $lock_key The transient key used to lock the group.
	 * @param string $lock_value The lock value to be verified.
	 * @param string $cache_group The cache group in which the lock value is stored. Default is an empty string.
	 *
	 * @return bool Whether the lock value matches the cached lock value in the cache group.
	 */
	abstract public function lock_verify( string $lock_key, string $lock_value, string $cache_group = '' ) : bool;

	/**
	 * Registers a cache group for flushing.
	 *
	 * @param string $cache_group The cache group to be registered.
	 *
	 * @return bool Whether the cache group was successfully registered.
	 */
	abstract public function register_cache_group( string $cache_group ) : bool;
}
