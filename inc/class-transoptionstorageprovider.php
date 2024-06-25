<?php

namespace HM\SwrCache;

/**
 * Option groups do not exists so we're using a prefix.
 */
class TransoptionStorageProvider extends StorageProvider {

	private array $registered_groups = [];
	/**
	 * Deletes a cache group and allows for immediate regeneration.
	 *
	 * @param string $cache_group The option group to be deleted.
	 *
	 * @return bool Always false as option groups do not exist.
	 */
	public function delete_group( string $cache_group ) : bool {
		// Option groups do not exist
		// TODO: Do a database search for all options with a prefix?
		global $wpdb;

		// cache groups must be registered first.
		if ( ! isset( $this->registered_groups[ $cache_group ] ) ) {
			return false;
		}

		$cache_group = $this->dashit( $cache_group );
		$affected = 0;
		// Deleting all options and transients with shared group name
		$affected += (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", "$cache_group%" )
		);
		$affected += (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", "_transient_$cache_group%" )
		);
		return $affected > 0;
	}

		/**
		 * Registers a cache group for flushing.
		 *
		 * @param string $group The cache group to be registered.
		 *
		 * @return bool Whether the cache group was successfully registered.
		 */
	public function register_group( string $group ) : bool {
		$this->registered_groups[ $group ] = $group;
		return true;
	}

	/**
	 * Retrieves a cached form with its expiry time.
	 *
	 * @param string $cache_key The key of the cache to retrieve.
	 * @param string $cache_group Optional. The cache group. Default is empty string.
	 *
	 * @return array An array containing the cached contents (or false) and its expiry timestamp.
	 */
	public function get_with_expiry( string $cache_key, string $cache_group = '' ) : array {
		$data = get_option( $this->dashit( $cache_group ) . $cache_key );
		$expiry_timestamp = (int) get_option( $this->dashit( $cache_group ) . $cache_key . '_expiry' );

		return [ $data, $expiry_timestamp ];
	}

	/**
	 * Add a dash to the end of a string if it is not empty.
	 *
	 * @param string $str The string to append the dash to.
	 *
	 * @return string The modified string.
	 */
	protected function dashit( $str ) : string {
		return $str ? $str . '-' : $str;
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
		update_option( $this->dashit( $cache_group ) . $cache_key, $data, false ); // Don't autoload.
		set_transient( $this->dashit( $cache_group ) . $cache_key . '_expiry', time() + $cache_duration, $cache_duration );
		delete_transient( $this->dashit( $cache_group ) . $lock_key );
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
	public function lock_add( string $lock_key, string $cache_group = '', int $lock_time = MINUTE_IN_SECONDS ) : string {
		$lock_value = wp_generate_uuid4();
		// Add will fail if it already exists.
		$this->add_transient( $lock_key, $lock_value, $cache_group, $lock_time );

		return $lock_value;
	}

	/**
	 * Verifies the lock against the cached lock.
	 *
	 * @param string $lock_key The transient key used to lock the group.
	 * @param string $lock_value The lock value to be verified.
	 * @param string $cache_group The cache group in which the lock value is stored. Default is an empty string.
	 *
	 * @return bool Whether the lock value matches the cached lock value in the cache group.
	 */
	function lock_verify( string $lock_key, string $lock_value, string $cache_group = '' ) : bool {
		$cached_lock = get_transient( $this->dashit( $cache_group ) . $lock_key );
		$found = $cached_lock !== false;

		return $found && $cached_lock === $lock_value;
	}

	/**
	 * Adds a transient value to the cache.
	 *
	 * @param string $key The key of the transient value.
	 * @param mixed  $data The data to be stored in the transient value.
	 * @param string $group The cache group where the transient value will be stored. Default is an empty string.
	 * @param int    $expire The duration in seconds for which the transient value will be stored. Default is 0, which means no expiration.
	 *
	 * @return bool True if the transient value was successfully added to the cache, false if the key and group already exists.
	 */
	protected function add_transient( string $key, mixed $data, string $group = '', int $expire = 0 ) : bool {
		$group = $this->dashit( $group );
		if ( get_transient( $group . $key ) ) {
			return false;
		}
		$transient = $group . $key;

		return set_transient( $transient, $data, $expire );
	}
}
