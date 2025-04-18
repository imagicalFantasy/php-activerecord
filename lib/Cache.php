<?php
namespace ActiveRecord;
use Closure;

/**
 * Cache::get('the-cache-key', function() {
 *	 # this gets executed when cache is stale
 *	 return "your cacheable datas";
 * });
 */
class Cache
{
	static $adapter = null;
	static $options = array();

	/**
	 * Initializes the cache.
	 *
	 * With the $options array it's possible to define:
	 * - expiration of the key, (time in seconds)
	 * - a namespace for the key
	 *
	 * this last one is useful in the case two applications use
	 * a shared key/store (for instance a shared Memcached db)
	 *
	 * Ex:
	 * $cfg_ar = ActiveRecord\Config::instance();
	 * $cfg_ar->set_cache('memcache://localhost:11211',array('namespace' => 'my_cool_app',
	 *																											 'expire'		 => 120
	 *																											 ));
	 *
	 * In the example above all the keys expire after 120 seconds, and the
	 * all get a postfix 'my_cool_app'.
	 *
	 * (Note: expiring needs to be implemented in your cache store.)
	 *
	 * @param string $url URL to your cache server
	 * @param array $options Specify additional options
	 */
	public static function initialize($url, $options=array())
	{
		if ($url)
		{
			$url = parse_url($url);
			$file = ucwords(Inflector::instance()->camelize($url['scheme']));
			$class = "ActiveRecord\\Cache\\$file";
			static::$adapter = new $class($url);
		}
		else
			static::$adapter = null;

		static::$options = array_merge(array('expire' => 30, 'namespace' => ''),$options);
	}

	public static function flush()
	{
		if (static::$adapter)
			static::$adapter->flush();
	}

	/**
	 * Attempt to retrieve a value from cache using a key. If the value is not found, then the closure method
	 * will be invoked, and the result will be stored in cache using that key.
	 * @param $key
	 * @param $closure
	 * @param $expire in seconds
	 * @return mixed
	 */
	public static function get($key, $closure, $expire=null)
	{
		if (!static::$adapter)
			return $closure();

		if (is_null($expire))
		{
			$expire = static::$options['expire'];
		}

		$key = static::get_namespace() . $key;

		if (!($value = static::$adapter->read($key)))
			static::$adapter->write($key, ($value = $closure()), $expire);

		return $value;
	}

	public static function set($key, $var, $expire=null)
	{
		if (!static::$adapter)
			return;

		if (is_null($expire))
		{
			$expire = static::$options['expire'];
		}

		$key = static::get_namespace() . $key;
		return static::$adapter->write($key, $var, $expire);
	}

	public static function delete($key)
	{
		if (!static::$adapter)
			return;

		$key = static::get_namespace() . $key;
		return static::$adapter->delete($key);
	}

	private static function get_namespace()
	{
		return (isset(static::$options['namespace']) && strlen(static::$options['namespace']) > 0) ? (static::$options['namespace'] . "::") : "";
	}
}
