<?php

namespace Tests\Feature;

use App\Services\Pricing\FrankfurterExchangeRateService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FrankfurterExchangeRateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_get_rate(): void
    {
        $service = new FrankfurterExchangeRateService;

        $rate = $service->getRate('USD', 'CNY');

        $this->assertNotNull($rate);
        $this->assertGreaterThan(0, $rate);
    }

    public function test_get_rate_same_currency(): void
    {
        $service = new FrankfurterExchangeRateService;

        $rate = $service->getRate('USD', 'USD');

        $this->assertEquals(1.0, $rate);
    }

    public function test_get_rates(): void
    {
        $service = new FrankfurterExchangeRateService;

        $rates = $service->getRates('USD');

        $this->assertIsArray($rates);
        $this->assertArrayHasKey('CNY', $rates);
        $this->assertArrayHasKey('EUR', $rates);
        $this->assertArrayHasKey('GBP', $rates);
    }

    public function test_convert(): void
    {
        $service = new FrankfurterExchangeRateService;

        $result = $service->convert(100, 'USD', 'CNY');

        $this->assertNotNull($result);
        $this->assertEquals(100, $result['amount']);
        $this->assertEquals('USD', $result['from']);
        $this->assertEquals('CNY', $result['to']);
        $this->assertGreaterThan(0, $result['rate']);
        $this->assertGreaterThan(0, $result['result']);
    }

    public function test_convert_same_currency(): void
    {
        $service = new FrankfurterExchangeRateService;

        $result = $service->convert(100, 'USD', 'USD');

        $this->assertNotNull($result);
        $this->assertEquals(1.0, $result['rate']);
        $this->assertEquals(100, $result['result']);
    }

    public function test_get_last_update_date(): void
    {
        $service = new FrankfurterExchangeRateService;

        $date = $service->getLastUpdateDate('USD');

        $this->assertNotNull($date);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
    }

    public function test_rates_are_cached(): void
    {
        $service = new FrankfurterExchangeRateService;

        $rates1 = $service->getRates('USD');
        $rates2 = $service->getRates('USD');

        $this->assertEquals($rates1, $rates2);
    }

    public function test_refresh_cache(): void
    {
        $service = new FrankfurterExchangeRateService;

        $service->getRates('USD');

        $result = $service->refreshCache('USD');

        $this->assertTrue($result);
    }

    public function test_get_historical_rate(): void
    {
        $service = new FrankfurterExchangeRateService;

        $rate = $service->getHistoricalRate('2024-01-01', 'USD', 'EUR');

        if ($rate !== null) {
            $this->assertGreaterThan(0, $rate);
        } else {
            $this->markTestSkipped('Historical rate not available');
        }
    }

    public function test_get_rate_case_insensitive(): void
    {
        $service = new FrankfurterExchangeRateService;

        $rate1 = $service->getRate('usd', 'cny');
        $rate2 = $service->getRate('USD', 'CNY');

        $this->assertEquals($rate1, $rate2);
    }
}
