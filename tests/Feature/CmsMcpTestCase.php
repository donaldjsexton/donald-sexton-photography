<?php

namespace Tests\Feature;

use App\Models\CmsMcpCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class CmsMcpTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @param  list<string>  $abilities
     */
    protected function token(array $abilities, string $name = 'tester'): string
    {
        return CmsMcpCredential::issue($name, $abilities)['token'];
    }

    /**
     * @return list<string>
     */
    protected function toolNames(string $token): array
    {
        return array_column($this->mcp($token, 'tools/list')->assertOk()->json('result.tools'), 'name');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function mcp(string $token, string $method, array $params = []): TestResponse
    {
        return $this->postJson('/mcp/cms', $this->rpc($method, $params), [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json, text/event-stream',
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function tool(string $token, string $name, array $arguments = []): TestResponse
    {
        return $this->mcp($token, 'tools/call', [
            'name' => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function rpc(string $method, array $params = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params === [] ? (object) [] : $params,
        ];
    }
}
