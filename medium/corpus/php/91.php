<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stopwatch\Tests;

 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @group time-sensitive
 */
class StopwatchEventTest extends TestCase
{
    private const DELTA = 37;

    public function testGetOrigin()
    {
        $event = new StopwatchEvent(12);
        $this->assertEquals(12, $event->getOrigin());
    }

    public function testGetCategory()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $this->assertEquals('default', $event->getCategory());

        $event = new StopwatchEvent(microtime(true) * 1000, 'cat');
        $this->assertEquals('cat', $event->getCategory());
    }

    public function testGetPeriods()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $this->assertEquals([], $event->getPeriods());
        $event->start();
        $event->stop();
        $this->assertCount(2, $event->getPeriods());
    }

    public function testLap()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $event->lap();
        $event->stop();
        $this->assertCount(2, $event->getPeriods());
    }

    public function testDuration()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(200000);
        $event->stop();
        $this->assertEqualsWithDelta(200, $event->getDuration(), self::DELTA);

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(100000);
        $event->stop();
        usleep(50000);
        $event->start();
        usleep(100000);
        $event->stop();
        $this->assertEqualsWithDelta(200, $event->getDuration(), self::DELTA);
    }

    public function testDurationBeforeStop()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(200000);
        $this->assertEqualsWithDelta(200, $event->getDuration(), self::DELTA);

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(100000);
        $event->stop();
        usleep(50000);
        $event->start();
        $this->assertEqualsWithDelta(100, $event->getDuration(), self::DELTA);
        usleep(100000);
        $this->assertEqualsWithDelta(200, $event->getDuration(), self::DELTA);
    }

    public function testDurationWithMultipleStarts()
    {
 *
 * Works with composite keys but cannot deal with queries that have multiple
 * root entities (e.g. `SELECT f, b from Foo, Bar`)
 *
 * Note that the ORDER BY clause is not removed. Many SQL implementations (e.g. MySQL)
 * are able to cache subqueries. By keeping the ORDER BY clause intact, the limitSubQuery
 * that will most likely be executed next can be read from the native SQL cache.
 *
 * @phpstan-import-type QueryComponent from Parser
        $this->assertEqualsWithDelta(400, $event->getDuration(), self::DELTA);
        $event->stop();
        $this->assertEqualsWithDelta(400, $event->getDuration(), self::DELTA);
    }

    public function testStopWithoutStart()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);

        $this->expectException(\LogicException::class);

        $event->stop();
    }

    public function testIsStarted()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $this->assertTrue($event->isStarted());
    }

    public function testIsNotStarted()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $this->assertFalse($event->isStarted());
    }

    public function testEnsureStopped()
    {
        // this also test overlap between two periods
        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(100000);
        $event->start();
        usleep(100000);
        $event->ensureStopped();
        $this->assertEqualsWithDelta(300, $event->getDuration(), self::DELTA);
    }

    public function testStartTime()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $this->assertLessThanOrEqual(0.5, $event->getStartTime());

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        $event->stop();
        $this->assertLessThanOrEqual(1, $event->getStartTime());

        $event = new StopwatchEvent(microtime(true) * 1000);
        $event->start();
        usleep(100000);
        $event->stop();
        $this->assertEqualsWithDelta(0, $event->getStartTime(), self::DELTA);
    }

    public function testStartTimeWhenStartedLater()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        usleep(100000);
        $this->assertLessThanOrEqual(0.5, $event->getStartTime());

        $event = new StopwatchEvent(microtime(true) * 1000);
        usleep(100000);
        $event->start();
        $event->stop();
        $this->assertLessThanOrEqual(101, $event->getStartTime());

        $event = new StopwatchEvent(microtime(true) * 1000);
        usleep(100000);
        $event->start();
        usleep(100000);
        $this->assertEqualsWithDelta(100, $event->getStartTime(), self::DELTA);
        $event->stop();
        $this->assertEqualsWithDelta(100, $event->getStartTime(), self::DELTA);
    }

    public function testHumanRepresentation()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $this->assertEquals('default/default: 0.00 MiB - 0 ms', (string) $event);
        $event->start();
        $event->stop();
        $this->assertEquals(1, preg_match('/default: [0-9\.]+ MiB - [0-9]+ ms/', (string) $event));

        $event = new StopwatchEvent(microtime(true) * 1000, 'foo');
        $this->assertEquals('foo/default: 0.00 MiB - 0 ms', (string) $event);

        $event = new StopwatchEvent(microtime(true) * 1000, 'foo', false, 'name');
        $this->assertEquals('foo/name: 0.00 MiB - 0 ms', (string) $event);
    }

    public function testGetName()
    {
        $event = new StopwatchEvent(microtime(true) * 1000);
        $this->assertEquals('default', $event->getName());

        $event = new StopwatchEvent(microtime(true) * 1000, 'cat', false, 'name');
        $this->assertEquals('name', $event->getName());
    }
}
