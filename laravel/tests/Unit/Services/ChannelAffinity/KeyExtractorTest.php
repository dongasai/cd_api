<?php

namespace Tests\Unit\Services\ChannelAffinity;

use App\Models\ApiKey;
use App\Services\ChannelAffinity\KeyExtractor;
use Illuminate\Http\Request;
use Tests\TestCase;

class KeyExtractorTest extends TestCase
{
    protected KeyExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new KeyExtractor;
    }

    public function test_extract_from_header(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('x-session-id', 'session-123');

        $result = $this->extractor->extract($request, [
            ['type' => 'header', 'key' => 'x-session-id'],
        ]);

        $this->assertEquals('session-123', $result);
    }

    public function test_extract_from_query(): void
    {
        $request = Request::create('/test?session_id=query-789', 'GET');

        $result = $this->extractor->extract($request, [
            ['type' => 'query', 'key' => 'session_id'],
        ]);

        $this->assertEquals('query-789', $result);
    }

    public function test_extract_api_key(): void
    {
        $apiKey = new ApiKey(['key' => 'sk-test-key']);
        $request = Request::create('/test', 'POST');
        $request->attributes->set('api_key', $apiKey);

        $result = $this->extractor->extract($request, [
            ['type' => 'api_key'],
        ]);

        $this->assertEquals('sk-test-key', $result);
    }

    public function test_extract_client_ip(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.100']);

        $result = $this->extractor->extract($request, [
            ['type' => 'client_ip'],
        ]);

        $this->assertEquals('192.168.1.100', $result);
    }

    public function test_extract_user_agent(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'RooCode/3.51.1');

        $result = $this->extractor->extract($request, [
            ['type' => 'user_agent'],
        ]);

        $this->assertEquals('RooCode/3.51.1', $result);
    }

    public function test_combine_first_strategy(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('x-session-id', 'session-123');

        $result = $this->extractor->extract($request, [
            ['type' => 'header', 'key' => 'x-session-id'],
            ['type' => 'header', 'key' => 'x-other'],
        ], 'first');

        $this->assertEquals('session-123', $result);
    }

    public function test_combine_concat_strategy(): void
    {
        $apiKey = new ApiKey(['key' => 'sk-test']);
        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'RooCode/3.51.1');
        $request->attributes->set('api_key', $apiKey);

        $result = $this->extractor->extract($request, [
            ['type' => 'api_key'],
            ['type' => 'user_agent'],
        ], 'concat');

        $this->assertEquals('sk-test|RooCode/3.51.1', $result);
    }

    public function test_generate_key_hash(): void
    {
        $values = ['sk-test-key', 'RooCode/3.51.1'];

        $hash = $this->extractor->generateKeyHash($values, 'hash');

        $this->assertEquals(16, strlen($hash));
        $this->assertEquals(substr(md5('sk-test-key|RooCode/3.51.1'), 0, 16), $hash);
    }

    public function test_fingerprint_masks_key(): void
    {
        $fingerprint = $this->extractor->fingerprint('sk-1234567890abcdef');

        $this->assertEquals('sk-1***********cdef', $fingerprint);
    }

    public function test_fingerprint_short_key(): void
    {
        $fingerprint = $this->extractor->fingerprint('abc');

        $this->assertEquals('***', $fingerprint);
    }
}
