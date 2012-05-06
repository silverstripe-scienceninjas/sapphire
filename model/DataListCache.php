<?php

/**
 * DataListCache
 *
 * @todo Evict cache entries from cache based on access time, then age
 *
 */
class DataListCache {

	/**
	 *
	 * @var array
	 */
	protected static $cache = array();

	/**
	 *
	 * @var array
	 */
	protected static $cache_group = array();

	/**
	 *
	 * @var int
	 */
	protected static $cache_size = 10000000;
	
	/**
	 *
	 * @var array
	 */
	protected static $cache_accessed = array();

	/**
	 *
	 * @param string $cacheKey
	 * @param closure $callback
	 * @return mixed
	 */
	public function call($cacheKey, $callback, $group=null) {

			$result = self::getCache($cacheKey);

			if($result) {
				return $result;
			}

			if(is_callable($callback)) {
				$result = call_user_func($callback);
			} else {
				$result = $callback;
			}

			$this->setCache($cacheKey, $result, $group);
			return $result;
	}

	/**
	 *
	 * @param string $cacheKey
	 * @param mixed $data
	 * @param string $group
	 * @return mixed - return the $data
	 */
	public function setCache($cacheKey, $data, $group = null) {
		self::$cache[$cacheKey] = $data;
		$this->accessed($cacheKey);

		if($group) {
			if(!isset(self::$cache_group[$group])) {
				self::$cache_group[$group] = array();
			}
			self::$cache_group[$group][] = $cacheKey;
		}

		if(self::$cache_size) {
			$this->gc();
		}
		return $data;
	}

	/**
	 *
	 * @param string $cacheKey
	 * @return mixed
	 */
	public function getCache($cacheKey) {
		if(isset(self::$cache[$cacheKey])) {
			$this->accessed($cacheKey);
			return self::$cache[$cacheKey];
		}
		
		return null;
	}

	/**
	 *
	 * @param string $cacheKey
	 */
	public function unsetCache($cacheKey) {
		if(isset(self::$cache[$cacheKey])) {
			unset(self::$cache[$cacheKey]);
		}
		
		if(isset(self::$cache_accessed[$cacheKey])) {
			unset(self::$cache_accessed[$cacheKey]);
		}
		
		if(isset(self::$cache_group[$cacheKey])) {
			unset(self::$cache_group[$cacheKey]);
		}
	}

	/**
	 *
	 * @param string $group
	 */
	public function clearCache($group = null) {
		if($group && isset(self::$cache_group[$group])) {
			foreach(self::$cache_group[$group] as $cacheKey) {
				unset(self::$cache[$cacheKey]);
				unset(self::$cache_accessed[$cacheKey]);
			}
		} else {
			self::$cache = array();
			self::$cache_accessed = array();
			self::$cache_group = array();
		}
	}

	/**
	 *
	 * @param int $size
	 */
	public function setCacheSize($size) {
		self::$cache_size = $size;
	}

	/**
	 *
	 * @return int
	 */
	public function getCacheSize() {
		$start_memory = memory_get_usage();
		$tmp = unserialize(serialize(self::$cache));
		return memory_get_usage() - $start_memory;
	}
	
	/**
	 *
	 * @param string $cachekey 
	 */
	protected function accessed($cacheKey) {
		self::$cache_accessed[$cacheKey] = microtime(true);
	}
	
	/**
	 *
	 * @return void
	 */
	protected function gc() {
		if(!count(self::$cache_accessed)) {
			return;
		}
		while(self::$cache_size < self::getCacheSize()) {
			asort(self::$cache_accessed);
			$evictedCacheKey = key(self::$cache_accessed);
			$this->unsetCache($evictedCacheKey);
		}
	}
}
