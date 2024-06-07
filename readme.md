# HM Expiry Cache

A soft expiring cache with background updating is a specialized caching strategy that allows cached data to remain available even after the expiration period. With regular caching, data becomes unavailable or leads to a 'cache miss' as soon as it expires. This can be costly, particularly with complex data fetching operations that require significant time.

In a soft expiring cache, when the cache period expires, the cached data remains accessible while a background update operation occurs. During this time, the user or application continues to see the stale cached data until the new updated data is ready. This approach ensures data is always available for end consumers with minimized latency from cache rebuild, providing a continuously smooth experience.

Note: As the cache is updated via a `wp_schedule_single_event()` call, using a scalable job system such as 
[Cavalcade](https://github.com/humanmade/Cavalcade) is recommended as the [default WordPress pseudo-cron](https://developer.wordpress.org/plugins/cron/) slows
the page load running the scheduled event.

## Requirements

* PHP 8.1+ is supported (technically it might run on PHP 7.1+).
* WordPress 6.4.2+ is supported (technically it might run on older versions, your experience is welcome).

## Installation

Just install the plugin like any other plugin. Installation as a MU plugin is supported as well, and via composer

```shell
composer config repositories.hm-expiry-cache vcs git@github.com:humanmade/hm-expiry-cache.git
composer require humanmade/hm-expiry-cache:^0.2 --no-update
composer update humanmade/hm-expiry-cache --prefer-source
```

These commands configure Composer to use a custom Git repository for the `hm-expiry-cache` package, add this package as a requirement without immediately installing it, and finally update and install the package from the source repository.

## Usage

All that is needed is for you to create a callback function that will be called with `$callback_args` as the first 
parameter by a scheduled task that is triggered when the cache expires after `$cache_expiry` seconds.

The following code goes where you need to use the cached data. On the first request the cache will be empty and 
scheduled to be generated, so your implementation needs handle that event. After the first generation, there will either be 
stale or current data to display:

```php
// false is returned if the data isn't in the cache yet
$data = ExpiryCache\get(
	$cache_key,
	$cache_group,
	__NAMESPACE__ . '\\my_callback',
	$callback_args,
	$cache_expiry
);

if ( ! $data) {
	// handle initial empty cache load
}
```

The callback is what you need to implement to generate the cacheable data, an array in the example below. 
You must return a `WP_Error` object in case of issues, this will generate an RuntimeException in the cron event, 
resulting in a cron log entry. 
```php
function my_callback( array $args ) : WP_Error|array {
	// Example validation handling and cache generation.
	if ( ! isset( $args['url'] ) ) {
		return new WP_Error( 'missing_arg', 'url' );
	}
	$data = example_api_request($args);
	if ( failed_validation($data ) ) {
		return new WP_Error( 'invalid_data', 'Data failed validation', $data );
	}
	return $data;
}

```

Internally a self-expiring and self-managed lock system ensures the callback is just processed once, avoiding race 
conditions. If, for whatever reason (for example due to a fatal error) the callback doesn't complete, then the lock self-expires in 1 minute.

## Flushing the cache

Engineers can clear the cache as well as any locks by calling the `cache_delete_group( $cache_group )` function, 
which might be useful after saving a settings page for example. The cachegroup should first be 
registrered for flushing using `register_cache_group( $cache_group )` before it can be flushed.

## Difference with other plugins

1. [WP-TLC-Transients](https://github.com/markjaquith/WP-TLC-Transients) In contrast to _WP-TLC-Transients_, _HM Expiry 
   Cache_  is not hitting  the database in  any circumstance as  both the lock, cached data and cache expiry are all stored using `wp_cache` functions. The syntax is also quite different.


I think that's it!
