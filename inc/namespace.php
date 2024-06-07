<?php
/**
 * Soft-expiry caching helper by HumanMade.
 *
 * A WordPress cache interface with support for soft-expiration (use old content until new content is available),
 * background updating of the cache using background crons.
 *
 * This allows frequent updating of the content without showing visitors uncached slow responses.
 *
 * The transient sets a unique lock value for every cache regeneration so that when the cache is cold it is only
 * regenerated once (race condition on long running regenerations)
 * the cache expiry key allows the key to expire without losing the cache contents
 * As a result then only on the very first request is there a wait for the content to be populated, from then onward
 * the cache is never empty and the page is always fast.
 * this allows us to set the cache expiry to be quite aggressive without affecting the performance of the site, if
 * this is an editor requirement, or very conservative to conserve resources.
 */

namespace HM\ExpiryCache;

use RuntimeException;

const CRON_ACTION = 'hm.expirycache.cron';

/**
 * Bootstrapping.
 *
 * @return void
 */
function bootstrap() : void {
	add_action( CRON_ACTION, __NAMESPACE__ . '\\do_cron', 10, 6 );
}

/**
 * Deletes a cache group and allows for immediate regeneration.
 *
 * @param string $cache_group The cache group to be deleted.
 *
 * @return bool Whether the cache group was successfully deleted.
 */
function cache_delete_group( string $cache_group ) : bool {
	// Requires cache group registration.
	// @see \HM\ExpiryCache\register_cache_group
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
function cache_get_with_expiry( string $cache_key, string $cache_group = '' ) : array {
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
function cache_set_with_expiry( string $lock_key, mixed $data, int $cache_duration, string $cache_key, string $cache_group = '' ) : void {
	wp_cache_set( $cache_key, $data, $cache_group );
	wp_cache_set( $cache_key . '_expiry', time() + $cache_duration, $cache_group, $cache_duration );
	wp_cache_delete( $lock_key, $cache_group );
}

/**
 * Check if the cache is warm.
 *
 * @param mixed $data The cached data.
 * @param int   $expiry_time The expiry time in Unix timestamp format.
 *
 * @return bool True if the cache is warm, false otherwise.
 */
function cache_is_warm( mixed $data, int $expiry_time ) : bool {
	$is_warm = $data !== false && ( time() < $expiry_time );

	return $is_warm;
}

/**
 * Executes a scheduled task with caching and locking.
 *
 * @param string   $lock_value The lock value used to verify the lock key.
 * @param callable $callback The callback function to execute.
 * @param array    $callback_args The arguments to be passed to the callback function.
 * @param int      $expiry_duration The expiry time in seconds for the cache.
 * @param string   $cache_key The cache key to store the data.
 * @param string   $cache_group (optional) The cache group to store the data. Defaults to an empty string.
 *
 * @throws RuntimeException If an error occurs during the execution of the callback function.
 */
function do_cron( string $lock_value, callable $callback, array $callback_args, int $expiry_duration, string $cache_key, string $cache_group = '' ) : void {
	$lock_key = "lock_$cache_key";
	if ( ! lock_verify( $lock_key, $lock_value, $cache_group ) ) {
		// Another invocation already reserved this cron job.
		return;
	}
	$data = $callback( $callback_args );

	if ( is_wp_error( $data ) ) {
		throw new RuntimeException( $data->get_error_message(), $data->get_error_code() );
	}

	cache_set_with_expiry( $lock_key, $data, $expiry_duration, $cache_key, $cache_group );
}

/**
 * Retrieves data from the cache or, if unavailable, schedules a cron job to fetch and cache the data.
 *
 * @param string   $cache_key The cache key.
 * @param string   $cache_group The cache group.
 * @param callable $callback The callback function to fetch the data if not available in cache.
 * @param array    $callback_args The arguments to pass to the callback function.
 * @param int      $cache_duration The duration of fresh cache content in seconds.
 *
 * @return mixed The cached data if available, or false if the data is not available in cache yet.
 */
function get( string $cache_key, string $cache_group, callable $callback, array $callback_args, int $cache_duration ) : mixed {
	[ $data, $expiry_timestamp ] = cache_get_with_expiry( $cache_key, $cache_group );

	if ( cache_is_warm( $data, $expiry_timestamp ) ) {
		// Cache is warm
		return $data;
	}

	wp_schedule_single_event( time(), CRON_ACTION, [
		lock_add( "lock_$cache_key", $cache_group ),
		$callback,
		$callback_args,
		$cache_duration,
		$cache_key,
		$cache_group,
	], true );

	// Cold cache, still return cached content
	return $data;
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
	$found = null;
	$cached_lock = wp_cache_get( $lock_key, $cache_group, false, $found );

	return $found && $cached_lock === $lock_value;
}

/**
 * Registers a cache group for flushing.
 *
 * @param string $cache_group The cache group to be registered.
 *
 * @return bool Whether the cache group was successfully registered.
 */
function register_cache_group( string $cache_group ) {
	global $wp_object_cache;
	if ( function_exists( 'wp_cache_add_redis_hash_groups' ) ) {
		// Enable cache group flushing for this group
		wp_cache_add_redis_hash_groups( $cache_group );
		return $wp_object_cache && isset( $wp_object_cache->redis_hash_groups[ $cache_group ] );
	}
	return false;
}
