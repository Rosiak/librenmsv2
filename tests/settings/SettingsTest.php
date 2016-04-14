<?php
/*
 * Copyright (C) 2016 Tony Murray <murraytony@gmail.com>
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * SettingsTest.php
 *
 * @package    LibreNMS
 * @author     Tony Murray <murraytony@gmail.com>
 * @copyright  2016 Tony Murray
 * @license    @license http://opensource.org/licenses/GPL-3.0 GNU Public License v3 or later
 */
class SettingsTest extends TestCase
{
    public function testSetGet()
    {
        Settings::set('test.setget', 'setget');
        $result = Settings::get('test.setget');

        $this->assertEquals('setget', $result);
    }

    public function testNonExistent()
    {
        $this->assertNull(Settings::get('non.existant.key'));
    }

    public function testDefault()
    {
        $result = Settings::get('test.default', 'default');

        $this->assertEquals('default', $result);
    }

    public function testReadOnly() {
        $this->setExpectedException('Exception');
        Config::set('config.test.readonly', 'value');
        Settings::set('test.readonly');
    }

    public function testConfigOnly()
    {
        Config::set('config.test.key', 'value');
        $result = Settings::get('test.key');

        $this->assertEquals('value', $result);
    }

    public function testConfigOverride()
    {
        Settings::set('test.override', 'settings');
        Settings::flush();
        Config::set('config.test.override', 'config');
        $result = Settings::get('test.override');

        $this->assertEquals('config', $result);
    }

    public function testSubtree()
    {
        $expected['key']['data'] = 'value';

        Settings::set('test.subtree.key.data', 'value');
        $result = Settings::get('test.subtree');

        $this->assertEquals($expected, $result);
    }

    public function testRecursiveSetting()
    {
        $data = ['key1' => 'data1', 'key2' => ['key3' => 'data3']];
        Settings::set('test.recursive', $data);
        $result = Settings::get('test.recursive');

        $this->assertEquals($data, $result);
    }

    public function testPathSetting()
    {
        $data = [
            'key1'      => 'data1',
            'key2.key3' => 'data3',
        ];
        $expected = ['key1' => 'data1', 'key2' => ['key3' => 'data3']];

        Settings::set('test.path', $data);
        $result = Settings::get('test.path');

        $this->assertEquals($expected, $result);
    }

    public function testConfigMergeSimple()
    {
        Settings::set('test.merge.simple', 'value');
        Config::set('config.test.merge.simple', 'configvalue');
        $result = Settings::get('test.merge.simple');

        $this->assertEquals('value', $result);
    }

    public function testConfigMergeComplex()
    {
        $expected = [
            'config'   => 'c1',
            'settings' => 's1',
            'other'    => [
                'config_leaf'   => 'c2',
                'settings_leaf' => 's2',
            ]];

        Config::set('config.test.config', 'c1');
        Config::set('config.test.other', 's_unseen');
        Config::set('config.test.other.config_leaf', 'c2');
        Settings::set('test.settings', 's1');
        Settings::set('test.other.settings_leaf', 's2');
        $result = Settings::get('test');

        $this->assertEquals($expected, $result);
    }

    public function testConfigMergeMismatch()
    {
        $data = ['value1', 'value2'];

        Settings::set('test.mismatch', $data);
        Config::set('config.test.mismatch', 'value');
        $result = Settings::get('test.mismatch');

        $this->assertEquals('value', $result);
    }

    public function testMixKeyArray() //TODO: more tests in this area, is this valid or invalid behaviour?
    {
        Settings::set('test.mix', ['with.period' => 'value']);
        $result = Settings::get('test.mix');

        $this->assertEquals(['with' => ['period' => 'value']], $result);
    }

    public function testDeeperKey()
    {
        Settings::set('test.mix', ['with.period' => 'value']);
        $result = Settings::get('test.mix.with.period');

        $this->assertEquals('value', $result);
    }

    public function testCacheFill()
    {
        // set some values
        Settings::set('test.cache.one', 'value1');
        Settings::set('test.cachetwo', 'value2');

        // load the values into cache
        $value1 = Settings::get('test.cache.one');
        $value2 = Settings::get('test.cachetwo');
        $this->assertEquals('value1', $value1);
        $this->assertEquals('value2', $value2);

        // check the cache
        $cache1 = Cache::tags(\App\Settings::$cache_tag)->get('test.cache.one');
        $cache2 = Cache::tags(\App\Settings::$cache_tag)->get('test.cachetwo');
        $this->assertEquals('value1', $cache1);
        $this->assertEquals('value2', $cache2);
    }

    public function testFlushCache()
    {
        Settings::set('test.flush', 'value');
        $cached = Cache::tags(\App\Settings::$cache_tag)->get('test.flush');
        $this->assertEquals('value', $cached);

        Settings::flush();
        $flushed = Cache::tags(\App\Settings::$cache_tag)->get('test.flush');
        $this->assertNull($flushed);

    }

    public function testNoCache() {
        Settings::set('test.nocache', 'value');
        Settings::flush();
        $result = Settings::get('test.nocache');

        $this->assertEquals('value', $result);
    }

    public function testNoCacheArray() {
        $expected = ['one'=>'value1', 'two'=>'value2'];
        Settings::set('test.nocachearray', $expected);
        Settings::flush();
        $result = Settings::get('test.nocachearray');

        $this->assertEquals($expected, $result);
    }

    public function testMultipleSet()
    {
        Settings::set('test.m', 'one');
        Settings::set('test.m', 'two');
        Settings::get('test.m');
        Settings::set('test.m', 'three');
        $result = Settings::get('test.m');

        $this->assertEquals('three', $result);
    }

    public function testParentCache()
    {
        $predata = ['two', 'three', 'one'];
        $data = ['one', 'two', 'three'];

        Settings::set('test.order', $predata);
        Settings::get('test.order'); // fill cache
        Settings::set('test.order', $data);
        $result = Settings::get('test.order');

        $this->assertEquals($data, $result);
    }

    public function testGetAll()
    {
        $data = ['key1' => 'data1', 'key2' => ['key3' => 'data3']];

        Settings::set("", $data);
        $result = Settings::all();

        $this->assertEquals($data, $result);
    }

    public function testArrayOfPaths()
    {
        $data = ['test.path1' => 'value1', 'test.deep.path2' => 'value2'];
        $expected = ['path1' => 'value1', 'deep' => ['path2' => 'value2']];

        Settings::set('', $data);
        $result = Settings::get('test');

        $this->assertEquals($expected, $result);
    }

    public function testArrayWithValue() {
        $data = ['value', 'arr'=>['one'=>'one', 'two'=>'two']];

        Settings::set('test.arrayval', $data);

        $result1 = Settings::get('test.arrayval.arr.two');
        $this->assertEquals('two', $result1);

        $result2 = Settings::get('test.arrayval.0');
        $this->assertEquals('value', $result2);
    }

    public function testSubpathValue() {
        Settings::set('test.subpath', 'value');

        try {
            Settings::set('test.subpath', ['one' => 'one', 'two' => 'two']);
            $this->fail("Unreachable line");
        }catch (\Exception $e) {
            $this->assertEquals("Attempting to set array value to existing non-array value at the key 'test.subpath'", $e->getMessage());
        }

        $result1 = Settings::get('test.subpath');
        $this->assertEquals('value', $result1);

        $result2 = Settings::get('test.subpath.one');
        $this->assertNull($result2);
    }

    public function testHas() {
        Settings::set('has.one', 'value');
        $this->assertTrue(Settings::has('has.one'));

        Config::set('config.has.two', 'value');
        $this->assertTrue(Settings::has('has.two'));

        Cache::tags(\App\Settings::$cache_tag)->put('has.three', 'value', 5);
        $this->assertTrue(Settings::has('has.three'))   ;

        $this->assertTrue(Settings::has('has'));

        $this->assertFalse(Settings::has('nothing'));
    }

    public function testForget() {
        Settings::set('test.forget', 'value');
        Settings::forget('test');
        $this->assertTrue(Settings::has('test.forget'));

        Settings::forget('test.forget');
        $this->assertFalse(Settings::has('test.forget'));

        Config::set('config.test.cant.forget', 'value');
        Settings::forget('test.cant.forget');
        $this->assertTrue(Settings::has('test.cant.forget'));
    }
}
