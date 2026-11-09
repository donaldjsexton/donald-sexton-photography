<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class CmsMcpProtocolClientTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_official_streamable_http_client_against_a_loopback_server(): void
    {
        if (! is_executable((string) getenv('NODE_BINARY') ?: '/usr/bin/node') && ! $this->binaryOnPath('node')) {
            $this->markTestSkipped('node is not available for the MCP client.');
        }

        $root = sys_get_temp_dir().'/cms-mcp-protocol-'.bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $database = $root.'/database.sqlite';
        $tokenFile = $root.'/credential';
        $sdk = sys_get_temp_dir().'/mcp-sdk-client';
        $storageBefore = glob(storage_path('app/public/media/*/*/*')) ?: [];
        $port = $this->freePort();
        $server = null;
        $token = null;

        try {
            $this->installSdk($sdk);
            copy(base_path('tests/Protocol/cms-mcp-client.mjs'), $sdk.'/cms-mcp-client.mjs');
            $env = $this->serverEnvironment($database, $port);
            $this->runProcess([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'], $env);
            $this->seedDisposableCredential($env, $tokenFile);
            $token = file_get_contents($tokenFile);
            $this->assertIsString($token);
            $this->assertNotSame('', $token);

            $server = new Process(
                [PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.(string) $port, '--no-reload'],
                base_path(),
                $env,
            );
            $server->setTimeout(null);
            $server->start();
            $this->waitUntilReady($port);

            $client = new Process(
                [$this->nodeBinary(), 'cms-mcp-client.mjs'],
                $sdk,
                array_merge($env, [
                    'MCP_TOKEN' => $token,
                    'MCP_URL' => 'http://127.0.0.1:'.$port.'/mcp/cms',
                    'MCP_SLUG' => 'protocol-draft',
                    'MCP_PNG' => self::PNG,
                ]),
            );
            $client->setTimeout(30);
            $client->run();
            $stdout = $client->getOutput();
            $stderr = str_replace($token, '[redacted]', $client->getErrorOutput());
            $this->assertTrue($client->isSuccessful(), 'MCP client failed: '.$stderr);
            $report = json_decode($stdout, true);
            $this->assertIsArray($report);
            $this->assertContains('lookup_story', $report['toolNames']);
            $this->assertContains('upload_media', $report['toolNames']);
            $this->assertNotContains('publish_story', $report['toolNames']);
            $this->assertTrue($report['sessionIdPresent']);
            $this->assertFalse($report['lookupIsError']);
            $this->assertSame('Protocol client body.', $report['body']);
            $this->assertSame('draft', $report['status']);
            $this->assertTrue($report['publishRejected']);
            $this->assertCount(2, $report['uploadIds']);
            $this->assertNotNull($report['uploadIds'][0]);
            $this->assertSame($report['uploadIds'][0], $report['uploadIds'][1]);

            $pdo = new \PDO('sqlite:'.$database);
            $mediaCount = (int) $pdo->query('select count(*) from media')->fetchColumn();
            $status = (string) $pdo->query("select status from wedding_stories where slug = 'protocol-draft'")->fetchColumn();
            $this->assertSame(1, $mediaCount);
            $this->assertSame('draft', $status);
            $serverOutput = $server->getOutput().$server->getErrorOutput();
            $this->assertStringNotContainsString($token, $stdout);
            $this->assertFalse(str_contains($serverOutput, $token), 'The disposable credential appeared in the server output.');
            $created = array_diff(glob(storage_path('app/public/media/*/*/*')) ?: [], $storageBefore);
            $this->assertCount(1, $created);
        } finally {
            if ($server instanceof Process && $server->isRunning()) {
                $server->stop(3);
            }

            foreach (array_diff(glob(storage_path('app/public/media/*/*/*')) ?: [], $storageBefore) as $createdFile) {
                if (is_file($createdFile)) {
                    unlink($createdFile);
                }
            }

            $this->deleteTree($root);
        }
    }

    /**
     * @param  array<string, string>  $env
     */
    private function seedDisposableCredential(array $env, string $tokenFile): void
    {
        $seed = <<<'PHP'
<?php

use App\Models\CmsMcpCredential;
use App\Models\WeddingStory;
use Illuminate\Contracts\Console\Kernel;

require getenv('CMS_MCP_BASE').'/vendor/autoload.php';
$app = require getenv('CMS_MCP_BASE').'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$issued = CmsMcpCredential::issue('protocol-test', CmsMcpCredential::DEFAULT_ABILITIES);
file_put_contents(getenv('MCP_TOKEN_FILE'), $issued['token'], LOCK_EX);
chmod(getenv('MCP_TOKEN_FILE'), 0600);
unset($issued);

WeddingStory::query()->create([
    'title' => 'Protocol draft',
    'slug' => 'protocol-draft',
    'status' => 'draft',
    'body' => 'Protocol client body.',
]);

fwrite(STDOUT, "seeded\n");
PHP;
        $seedPath = dirname($tokenFile).'/seed.php';
        file_put_contents($seedPath, $seed);
        $this->runProcess([PHP_BINARY, $seedPath], array_merge($env, [
            'CMS_MCP_BASE' => base_path(),
            'MCP_TOKEN_FILE' => $tokenFile,
        ]));
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $env
     */
    private function runProcess(array $command, array $env): void
    {
        $process = new Process($command, base_path(), $env);
        $process->setTimeout(120);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput()."\n".$process->getOutput());
    }

    /**
     * @return array<string, string>
     */
    private function serverEnvironment(string $database, int $port): array
    {
        $env = [];

        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }

        return array_merge($env, [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:rEkrGC9F7c+yI9IplUQb8MVnwFrfJGr7EvlLtEXf+vE=',
            'APP_URL' => 'http://127.0.0.1:'.$port,
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL' => 'stderr',
            'LOG_LEVEL' => 'debug',
        ]);
    }

    private function installSdk(string $sdk): void
    {
        $package = $sdk.'/node_modules/@modelcontextprotocol/sdk/package.json';

        if (is_file($package)) {
            return;
        }

        if (! is_dir($sdk)) {
            mkdir($sdk, 0700, true);
        }

        if (! is_file($sdk.'/package.json')) {
            file_put_contents($sdk.'/package.json', "{\"private\":true}\n");
        }

        $install = new Process(['npm', 'install', '--silent', '@modelcontextprotocol/sdk@1.22.0'], $sdk);
        $install->setTimeout(120);
        $install->run();
        $this->assertTrue($install->isSuccessful(), 'Could not install the MCP SDK client.');
        $this->assertFileExists($package);
    }

    private function waitUntilReady(int $port): void
    {
        $deadline = microtime(true) + 15;

        while (microtime(true) < $deadline) {
            $context = stream_context_create(['http' => ['timeout' => 1]]);
            $handle = @fopen('http://127.0.0.1:'.$port.'/up', 'rb', false, $context);

            if ($handle !== false) {
                fclose($handle);

                return;
            }

            usleep(200000);
        }

        $this->fail('The loopback MCP server did not become ready.');
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        if ($socket === false) {
            $this->fail('Could not reserve a loopback port.');
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);
    }

    private function nodeBinary(): string
    {
        $configured = getenv('NODE_BINARY');

        return is_string($configured) && $configured !== '' ? $configured : 'node';
    }

    private function binaryOnPath(string $binary): bool
    {
        $process = new Process(['sh', '-c', 'command -v '.escapeshellarg($binary)]);
        $process->run();

        return $process->isSuccessful();
    }

    private function deleteTree(string $root): void
    {
        if (! is_dir($root)) {
            return;
        }

        $items = scandir($root) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $root.'/'.$item;

            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                unlink($path);
            }
        }

        rmdir($root);
    }
}
