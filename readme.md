## HM Expiry Cache

A soft expiring cache with background updating is a specialized form of caching strategy that allows cached data to remain available even after the expiration of the cache period. With regular caching, the data becomes invisible or 'misses' as soon as the cache expires, leading to a cache miss that's costly to replace, especially with more complex data fetching operations that require significant time to perform.

In a soft expiring cache, even when the cache period expires, the data remains visible while a background update operation takes place. As the data fetching takes place in the background, the user or application continues to see the old cached data until the new updated data is ready to take its place. This approach enables the data to be always available for end consumers with minimized latency from cache rebuild, providing a continuously smooth experience.

## Difference with other plugins

1. [WP-TLC-Transients](https://github.com/markjaquith/WP-TLC-Transients) This plugin is not hitting the database in 
   any circumstance as 
   both the lock, cached data and cache expiry are all stored using `wp_cache` functions. The syntax in this plugin 
   does 
   not use arrow syntax.

## Usage

All that is needed is for you to create a callback function that will be called with `$callback_args` as the first 
parameter by a scheduled task that is triggered when the cache after `$cache_expiry` seconds.

```php
	$data = ExpiryCache\get(
		$cache_key,
		$cache_group,
		__NAMESPACE__ . '\\get_my_external_data',
		$callback_args,
		$cache_expiry
	);

	if ( ! $data ) {
		// Cache is regenerating on first invocation.
		$data = $my_fallback_data;
	}

	return $data;
```

The plugin internally uses a self-expiring lock system so that the callback is only called once. If due to errors 
the code the callback doesn't complete then the lock self-expires in 1 minute.

Engineers can clear the data as well as any locks by calling the `cache_delete_group( $cache_group )` function, 
which might be useful after saving cache related settings for example.

I think that's it!
