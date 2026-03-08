<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_setting(): void
    {
        $setting = SystemSetting::create([
            'group' => 'test',
            'key' => 'test_key',
            'value' => 'test_value',
            'type' => SystemSetting::TYPE_STRING,
            'label' => 'Test Key',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'group' => 'test',
            'key' => 'test_key',
        ]);
    }

    public function test_typed_value_conversion(): void
    {
        $stringSetting = SystemSetting::create([
            'group' => 'test',
            'key' => 'string_val',
            'value' => 'hello',
            'type' => SystemSetting::TYPE_STRING,
        ]);
        $this->assertEquals('hello', $stringSetting->getTypedValue());

        $intSetting = SystemSetting::create([
            'group' => 'test',
            'key' => 'int_val',
            'value' => '42',
            'type' => SystemSetting::TYPE_INTEGER,
        ]);
        $this->assertEquals(42, $intSetting->getTypedValue());

        $floatSetting = SystemSetting::create([
            'group' => 'test',
            'key' => 'float_val',
            'value' => '3.14',
            'type' => SystemSetting::TYPE_FLOAT,
        ]);
        $this->assertEquals(3.14, $floatSetting->getTypedValue());

        $boolSetting = SystemSetting::create([
            'group' => 'test',
            'key' => 'bool_val',
            'value' => '1',
            'type' => SystemSetting::TYPE_BOOLEAN,
        ]);
        $this->assertTrue($boolSetting->getTypedValue());

        $jsonSetting = SystemSetting::create([
            'group' => 'test',
            'key' => 'json_val',
            'value' => '{"foo":"bar"}',
            'type' => SystemSetting::TYPE_JSON,
        ]);
        $this->assertEquals(['foo' => 'bar'], $jsonSetting->getTypedValue());
    }

    public function test_setting_service_get(): void
    {
        SystemSetting::create([
            'group' => 'test',
            'key' => 'my_key',
            'value' => 'my_value',
            'type' => SystemSetting::TYPE_STRING,
        ]);

        $service = app(SettingService::class);

        $this->assertEquals('my_value', $service->get('test.my_key'));
        $this->assertEquals('default', $service->get('test.nonexistent', 'default'));
    }

    public function test_setting_helper_function(): void
    {
        SystemSetting::create([
            'group' => 'test',
            'key' => 'helper_test',
            'value' => 'helper_value',
            'type' => SystemSetting::TYPE_STRING,
        ]);

        app(SettingService::class)->clearCache();

        $this->assertEquals('helper_value', setting('test.helper_test'));
    }

    public function test_unique_constraint(): void
    {
        SystemSetting::create([
            'group' => 'test',
            'key' => 'unique_key',
            'value' => 'value1',
            'type' => SystemSetting::TYPE_STRING,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SystemSetting::create([
            'group' => 'test',
            'key' => 'unique_key',
            'value' => 'value2',
            'type' => SystemSetting::TYPE_STRING,
        ]);
    }
}
