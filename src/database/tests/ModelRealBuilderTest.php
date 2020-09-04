<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace HyperfTest\Database;

use Carbon\Carbon;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Database\Model\Events\Saved;
use HyperfTest\Database\Stubs\ContainerStub;
use HyperfTest\Database\Stubs\Model\User;
use HyperfTest\Database\Stubs\Model\UserRole;
use HyperfTest\Database\Stubs\Model\UserRolePivot;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Swoole\Coroutine\Channel;

/**
 * @internal
 * @coversNothing
 */
class ModelRealBuilderTest extends TestCase
{
    /**
     * @var array
     */
    protected $channel;

    protected function setUp()
    {
        $this->channel = new Channel(999);
    }

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testPivot()
    {
        $this->getContainer();

        $user = User::query()->find(1);
        $role = $user->roles->first();
        $this->assertSame(1, $role->id);
        $this->assertSame('author', $role->name);

        $this->assertInstanceOf(UserRolePivot::class, $role->pivot);
        $this->assertSame(1, $role->pivot->user_id);
        $this->assertSame(1, $role->pivot->role_id);

        $role->pivot->updated_at = $now = Carbon::now()->toDateTimeString();
        $role->pivot->save();

        $pivot = UserRole::query()->find(1);
        $this->assertSame($now, $pivot->updated_at->toDateTimeString());

        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof Saved) {
                $this->assertSame($event->getModel(), $role->pivot);
                $hit = true;
            }
        }

        $this->assertTrue($hit);
    }

    public function testForPageBeforeId()
    {
        $this->getContainer();

        User::query()->forPageBeforeId(2)->get();
        User::query()->forPageBeforeId(2, null)->get();
        User::query()->forPageBeforeId(2, 1)->get();

        $sqls = [
            ['select * from `user` where `id` < ? order by `id` desc limit 2', [0]],
            ['select * from `user` order by `id` desc limit 2', []],
            ['select * from `user` where `id` < ? order by `id` desc limit 2', [1]],
        ];
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame([$event->sql, $event->bindings], array_shift($sqls));
            }
        }
    }

    public function testForPageAfterId()
    {
        $this->getContainer();

        User::query()->forPageAfterId(2)->get();
        User::query()->forPageAfterId(2, null)->get();
        User::query()->forPageAfterId(2, 1)->get();

        $sqls = [
            ['select * from `user` where `id` > ? order by `id` asc limit 2', [0]],
            ['select * from `user` order by `id` asc limit 2', []],
            ['select * from `user` where `id` > ? order by `id` asc limit 2', [1]],
        ];
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame([$event->sql, $event->bindings], array_shift($sqls));
            }
        }
    }

    protected function getContainer()
    {
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->with(Mockery::any())->andReturnUsing(function ($event) {
            $this->channel->push($event);
        });
        $container = ContainerStub::getContainer(function ($conn) use ($dispatcher) {
            $conn->setEventDispatcher($dispatcher);
        });
        $container->shouldReceive('get')->with(EventDispatcherInterface::class)->andReturn($dispatcher);

        return $container;
    }
}
