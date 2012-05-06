<?php

/**
 * Description of DataListCacheTest
 *
 */
class DataListCacheTest extends SapphireTest {

	/**
	 *
	 * @var DataListCache
	 */
	protected $cache = null;

	public function setUp() {
		parent::setUp();
		$this->cache = new DataListCache();
		$this->cache->clearCache();
	}

	/**
	 * Test the normal set and get of items 
	 */
	public function testGetSetCache() {
		$this->assertEquals(null, $this->cache->getCache('test'));

		$this->cache->setCache('test', 'data');
		$this->assertEquals('data',$this->cache->getCache('test'));

		$this->cache->setCache('otherKey', 'otherData');
		$this->assertEquals('data', $this->cache->getCache('test'));
	}

	/**
	 * Test unsetting a cached entry
	 */
	public function testUnset() {
		$this->cache->setCache('test', 'data');
		$this->assertEquals('data',$this->cache->getCache('test'));
		$this->cache->unsetCache('test');
		$this->assertNull($this->cache->getCache('test'));

	}

	/**
	 * Test that clearing the cache works
	 */
	public function testClearCache() {
		$this->cache->setCache('test', 'data');
		$this->assertEquals('data',$this->cache->getCache('test'));

		$this->cache->clearCache();
		$this->assertEquals(null, $this->cache->getCache('test'));
	}

	/**
	 * Test that the evection works
	 */
	public function testEviction() {
		$this->cache->setCacheSize(950);

		$this->cache->setCache('first', 'first data');
		$this->assertEquals('first data', $this->cache->getCache('first'), 'First entry should be there');

		$this->cache->setCache('second', 'second data');
		$this->assertEquals('first data',$this->cache->getCache('first'), 'Second entry should not have evicted first entry');
		$this->assertEquals('second data',$this->cache->getCache('second'), 'Second entry should be in there.');

		$this->cache->setCache('third', 'third data');
		$this->assertNull($this->cache->getCache('first'), 'When third entry is cached, first should have gone');
		$this->assertEquals('second data', $this->cache->getCache('second'), 'When third entry is cached, second entry should still be there '.$this->cache->getCacheSize());
		$this->assertEquals('third data',$this->cache->getCache('third'), 'When third entry is cached, third entry should still be there');

		$this->cache->setCache('bigdata', 'This is a much bigger entry and should be the only entry in the cache.');
		$this->assertNull($this->cache->getCache('second'), 'When bigdata entry is cached, second entry should not be there');
		$this->assertNull($this->cache->getCache('third'), 'When bigdata entry is cached, third entry should not be there');
		$this->assertNotNull($this->cache->getCache('bigdata'), 'bigdata should not be null');
	}
	
	public function testEvictsNonAccessedItemFirst() {
		$this->cache->setCacheSize(950);
		
		$this->cache->setCache('first', 'first data');
		$this->assertEquals('first data', $this->cache->getCache('first'), 'First entry should be there');
		
		$this->cache->setCache('second', 'second data');
		$this->assertEquals('first data', $this->cache->getCache('first'), 'First entry should still there');
		$this->cache->setCache('third', 'third data');
		$this->assertEquals('first data', $this->cache->getCache('first'), 'First entry should still there');
		$this->cache->setCache('forth', 'forth data');
		$this->assertEquals('first data', $this->cache->getCache('first'), 'First entry should still there');
		$this->assertNull($this->cache->getCache('second'), 'Second entry should have been evicted');
		$this->assertNull($this->cache->getCache('third' ), 'Third entry should have been evicted');
		$this->assertEquals('forth data', $this->cache->getCache('forth'), 'Fourth entry should still there');
	}

	/**
	 * Test that clearing cache by group works 
	 */
	public function testClearCacheByGroup() {
		$this->cache->setCache('first', 'first data group 1', 'group1');
		$this->cache->setCache('second', 'second data group 2', 'group2');
		$this->cache->clearCache('group1');
		$this->assertNull($this->cache->getCache('first'));
		$this->assertEquals('second data group 2', $this->cache->getCache('second'));
	}

	/**
	 * Simplest test using cache with an anon function works
	 * 
	 */
	public function testAnonymousFunctionCache() {
		$this->cache->call('cacheKey', function() {
			return 'Result from calculation';
		});
		$this->assertEquals('Result from calculation', $this->cache->getCache('cacheKey'));
	}

	/**
	 * Test that the anon function in fact doesn't call external function after the first
	 */
	public function testAnonymousFunctionCallontCallCodeTwice() {
		$value = $this->increaseStaticCount();
		$_this = $this;
		$this->assertEquals(1, $value, 'The initial value from increaseStaticCount() should be 1.');

		$value = $this->cache->call('cacheKey', function() use($_this) {
			return $_this->increaseStaticCount();
		});
		$this->assertEquals(2, $this->cache->getCache('cacheKey'), 'The call value should have been increased to 2.');

		$value = $this->cache->call('cacheKey', function() use($_this) {
			return $_this->increaseStaticCount();
		});
		$this->assertEquals(2, $this->cache->getCache('cacheKey'), 'The cached value should not have been increased.');
	}

	/**
	 * Test the the anon function cache works when setting a group
	 */
	public function testCallGroups() {
		$this->cache->call('first', function() {
			return 'first entry';
		}, 'group1');

		$this->cache->call('second', function() {
			return 'second entry';
		}, 'group2');

		$this->assertEquals('first entry', $this->cache->getCache('first'));
		$this->cache->clearCache('group1');
		$this->assertNull($this->cache->getCache('first'));
		$this->assertEquals('second entry', $this->cache->getCache('second'));
	}

	/**
	 * This is a function that is used for testing cached anon functions. Every time this function 
	 * is called it increases the count
	 *
	 * @staticvar int $count
	 * @return int
	 */
	public function increaseStaticCount() {
		static $count = 0;
		$count++;
		return $count;
	}
}
