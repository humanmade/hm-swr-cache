<?php

namespace HM\SwrCache;

/**
 *
 */
class CacheStorageProvider extends StorageProvider {
	/**
	 * Deletes a cache group and allows for immediate regeneration.
	 *
	 * @param string $cache_group The cache group to be deleted.
	 *
	 * @return bool Whether the cache group was successfully deleted.
	 */
	function delete_group( string $cache_group ) : bool {
		// Requires cache group registration.
		// @see \HM\SwrCache\register_cache_group
		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			return wp_cache_flush_group( $cache_group );
		}

		return false;
	}

	/**
	 * Retrieves a cached form with its expiry time.
	 *
	 * @param string $cache_key The key of the cache to retrieve.
	 * @param string $cache_group Optional. The cache group. Default is empty string.
	 *
	 * @return array An array containing the cached contents (or false) and its expiry timestamp.
	 */
	function get_with_expiry( string $cache_key, string $cache_group = '' ) : array {
		$data = wp_cache_get( $cache_key, $cache_group );
		$expiry_timestamp = (int) wp_cache_get( $cache_key . '_expiry', $cache_group );

		return [ $data, $expiry_timestamp ];
	}

	/**
	 * Set data in cache with expiry and delete the lock transient.
	 *
	 * @param string $lock_key The name of the lock.
	 * @param mixed  $data The data to be cached.
	 * @param int    $cache_duration The expiry time for the cache in seconds.
	 * @param string $cache_key The key for the cache.
	 * @param string $cache_group Optional. The group for the cache. Default value is empty string.
	 */
	public function set_with_expiry( string $lock_key, mixed $data, int $cache_duration, string $cache_key, string $cache_group = '' ) : void {
		wp_cache_set( $cache_key, $data, $cache_group );

		wp_cache_set( $cache_key . '_expiry', time() + $cache_duration, $cache_group, $cache_duration );
		wp_cache_delete( $lock_key, $cache_group );
	}

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
	function lock_add( string $lock_key, string $cache_group = '', int $lock_time = MINUTE_IN_SECONDS ) : string {
		$lock_value = wp_generate_uuid4();
		// Add will fail if it already exists.
		wp_cache_add( $lock_key, $lock_value, $cache_group, $lock_time );

		return $lock_value;
	}
}
