<?php

namespace HM\SwrCache;

abstract class StorageProvider {
	public const CACHE = __NAMESPACE__ .'\\CacheStorageProvider';
	public const OPTION = __NAMESPACE__ . '\\OptionStorageProvider';
	private static $instance;

	public static function getInstance( $provider_type = self::CACHE ) {
		if ( ! self::$instance ) {
			if ( ! class_exists( $provider_type ) ) {
				throw new \InvalidArgumentException( "Class '$provider_type' does not exist" );
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
	abstract function set_with_expiry( string $lock_key, mixed $data, int $cache_duration, string $cache_key, string $cache_group = '' ) : void;

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
	abstract function lock_add( string $lock_key, string $cache_group = '', int $lock_time = MINUTE_IN_SECONDS ) : string;
}
