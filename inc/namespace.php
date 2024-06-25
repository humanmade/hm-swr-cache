<?php
/**
 * Soft-expiry caching helper by HumanMade.
 *
 * A WordPress cache interface with support for soft-expiration (use old content until new content is available),
 * background updating of the cache using background crons.
 *
 * This allows frequent updating of the content without showing visitors uncached slow responses.
 *
 * The plugin sets a unique lock value for every cache regeneration so that when the cache is cold it is only
 * regenerated once (avoiding race conditions on long running regenerations)
 * the cache expiry key allows the key to expire without losing the cache contents
 * As a result then only on the very first request is there a wait for the content to be populated, from then onward
 * the cache is never empty and the page is always fast.
 * this allows us to set the cache expiry to be quite aggressive without affecting the performance of the site, if
 * this is an editor requirement, or very conservative to conserve resources.
 */

namespace HM\SwrCache;

use Closure;
use InvalidArgumentException;
use RuntimeException;

const CRON_ACTION = 'hm.swrCache.cron';
/**
 * Bootstrapping.
 *
 * @return void
 */
function bootstrap() : void {
	global $storage;

	add_action( CRON_ACTION, __NAMESPACE__ . '\\do_cron', 10, 7 );
	$storage = StorageProvider::get_instance( StorageProvider::CACHE );
}

/**
 * Deletes a cache group and allows for immediate regeneration.
 *
 * @param string $cache_group The cache group to be deleted.
 *
 * @return bool Whether the cache group was successfully deleted.
 */
function cache_delete_group( string $cache_group ) : bool {
	global $storage;

	return $storage->delete_group( $cache_group );
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
 * @throws InvalidArgumentException If a closure is provided as a callback.
 * @throws RuntimeException If an error occurs during the execution of the callback function.
 */
function do_cron( string $lock_value, callable $callback, array $callback_args, int $expiry_duration, string $cache_key, string $cache_group = '') : void {
	global $storage;

	if ( $callback instanceof Closure ) {
		throw new InvalidArgumentException( 'Closures are not allowed as callbacks.' );
	}

	$lock_key = "lock_$cache_key";
	if ( ! $storage->lock_verify( $lock_key, $lock_value, $cache_group ) ) {
		// Another invocation already reserved this cron job.
		return;
	}
	$data = $callback( $callback_args );

	if ( is_wp_error( $data ) ) {
		throw new RuntimeException( $data->get_error_message(), $data->get_error_code() );
	}

	$storage->set_with_expiry( $lock_key, $data, $expiry_duration, $cache_key, $cache_group );
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
 *
 * @throws InvalidArgumentException If a closure is provided as a callback.
 */
function get( string $cache_key, string $cache_group, callable $callback, array $callback_args, int $cache_duration ) : mixed {
	global $storage;

	if ( $callback instanceof Closure ) {
		throw new InvalidArgumentException( 'Closures are not allowed as callbacks.' );
	}

	[ $data, $expiry_timestamp ] = $storage->get_with_expiry( $cache_key, $cache_group );

	if ( cache_is_warm( $data, $expiry_timestamp ) ) {
		// Cache is warm
		return $data;
	}

	wp_schedule_single_event( time(), CRON_ACTION, [
		$storage->lock_add( "lock_$cache_key", $cache_group ),
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
 * Registers a cache group for flushing.
 *
 * @param string $cache_group The cache group to be registered.
 *
 * @return bool Whether the cache group was successfully registered.
 */
function register_cache_group( string $cache_group ) : bool {
	global $storage;

	return $storage->register_group( $cache_group );
}
