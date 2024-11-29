<?php declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ripple\Promise;
use Ripple\Utils\Output;
use Ripple\WebSocket\Client;
use Ripple\WebSocket\Options;
use Ripple\WebSocket\Server;
use Ripple\WebSocket\Server\Connection;

use function Co\cancelAll;
use function Co\defer;
use function Co\wait;

/**
 * @Author cclilshy
 * @Date   2024/8/15 14:49
 */
class WsTest extends TestCase
{
    /**
     * @Author cclilshy
     * @Date   2024/8/15 14:49
     * @return void
     * @throws Throwable
     */
    #[Test]
    public function test_wsServer(): void
    {
        defer(function () {
            for ($i = 0; $i < 10; $i++) {
                try {
                    $this->wsTest()->await();
                } catch (Throwable $exception) {
                    Output::error($exception->getMessage());
                }
            }

            \gc_collect_cycles();
            $baseMemory = \memory_get_usage();

            for ($i = 0; $i < 10; $i++) {
                try {
                    $this->wsTest()->await();
                } catch (Throwable $exception) {
                    Output::error($exception->getMessage());
                }
            }

            \gc_collect_cycles();
            if ($baseMemory !== \memory_get_usage()) {
                Output::warning('There may be a memory leak');
            }
            cancelAll();
            $this->assertTrue(true);
        });

        $context = \stream_context_create([
            'socket' => [
                'so_reuseport' => 1,
                'so_reuseaddr' => 1,
            ],
        ]);

        $server = new Server(
            'ws://127.0.0.1:8001/',
            $context,
            new Options(true, true)
        );
        $server->onMessage(static function (string $data, Connection $connection) {
            $connection->send($data);
        });
        $server->listen();
        wait();
    }

    /**
     * @Author cclilshy
     * @Date   2024/8/15 14:49
     * @return Promise
     */
    private function wsTest(): Promise
    {
        return \Co\promise(function ($r) {
            $hash = \md5(\uniqid());
            $client = new Client('ws://127.0.0.1:8001/');
            $client->onOpen(static function () use ($client, $hash) {
                \Co\sleep(0.1);
                $client->send($hash);
            });

            $client->onMessage(function (string $data) use ($hash, $r) {
                $this->assertEquals($hash, $data);
                $r();
            });
        });
    }


    /**
     * @Author BadJacky
     * @Date   2024/11/29 22:39
     * @return void
     * @throws Throwable
     */
    #[Test]
    public function test_wsServerWithQuery(): void
    {
        defer(function () {
            for ($i = 0; $i < 10; $i++) {
                try {
                    $this->wsTestWithQuery()->await();
                } catch (Throwable $exception) {
                    Output::error($exception->getMessage());
                }
            }

            \gc_collect_cycles();
            $baseMemory = \memory_get_usage();

            for ($i = 0; $i < 10; $i++) {
                try {
                    $this->wsTestWithQuery()->await();
                } catch (Throwable $exception) {
                    Output::error($exception->getMessage());
                }
            }

            \gc_collect_cycles();
            if ($baseMemory !== \memory_get_usage()) {
                Output::warning('There may be a memory leak');
            }
            cancelAll();
            $this->assertTrue(true);
        });

        $context = \stream_context_create([
            'socket' => [
                'so_reuseport' => 1,
                'so_reuseaddr' => 1,
            ],
        ]);

        $server = new Server(
            'ws://127.0.0.1:8001/',
            $context,
            new Options(true, true)
        );
        $server->onMessage(static function (string $data, Connection $connection) {
            $connection->send(\json_encode(['hash' => $data, 'query' => $connection->getRequest()->query], \JSON_UNESCAPED_UNICODE));
        });
        $server->listen();
        wait();
    }


    /**
     * @Author BadJacky
     * @Date   2024/11/29 22:39
     * @return Promise
     */
    private function wsTestWithQuery(): Promise
    {
        return \Co\promise(function ($r) {
            $hash = \md5(\uniqid());
            $client = new Client('ws://127.0.0.1:8001/?foo=bar&baz=qux');
            $client->onOpen(static function () use ($client, $hash) {
                \Co\sleep(0.1);
                $client->send($hash);
            });

            $client->onMessage(function (string $data) use ($hash, $r) {
                $this->assertEquals(\json_encode(['hash' => $hash, 'query' => ['foo' => 'bar', 'baz' => 'qux']]), $data);
                $r();
            });
        });
    }

}
