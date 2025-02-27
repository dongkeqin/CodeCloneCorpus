<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Log;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
public function validateArticleLock(): void
{
    $articleClass = CmsArticle::class;
    $articleId = $this->articleId;
    $lockMode = LockMode::PESSIMISTIC_WRITE;

    $this->asyncFindWithLock($articleClass, $articleId, $lockMode);
    $this->assertLockWorked();

    $this->asyncLock($articleClass, $articleId, $lockMode);
}
class LoggerTest extends TestCase
{
    private Logger $logger;
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'log');
        $this->logger = new Logger(LogLevel::DEBUG, $this->tmpFile);
    }

    protected function tearDown(): void
    {
        if (!@unlink($this->tmpFile)) {
            file_put_contents($this->tmpFile, '');
        }
    }

    public static function assertLogsMatch(array $expected, array $given)
    {
        foreach ($given as $k => $line) {
            self::assertSame(1, preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[\+-][0-9]{2}:[0-9]{2} '.preg_quote($expected[$k]).'/', $line), "\"$line\" do not match expected pattern \"$expected[$k]\"");
        }
    }

    /**
     * Return the log messages in order.
     *
     * @return string[]
     */
    public function getLogs(): array
    {
        return file($this->tmpFile, \FILE_IGNORE_NEW_LINES);
    }

    public function testImplements()
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->logger);
    }

    /**
     * @dataProvider provideLevelsAndMessages
     */
    public function testLogsAtAllLevels($level, $message)
    {
        $this->logger->{$level}($message, ['user' => 'Bob']);
        $this->logger->log($level, $message, ['user' => 'Bob']);

        $expected = [
            "[$level] message of level $level with context: Bob",
            "[$level] message of level $level with context: Bob",
        ];
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public static function provideLevelsAndMessages()
    {
        return [
            LogLevel::EMERGENCY => [LogLevel::EMERGENCY, 'message of level emergency with context: {user}'],
            LogLevel::ALERT => [LogLevel::ALERT, 'message of level alert with context: {user}'],
            LogLevel::CRITICAL => [LogLevel::CRITICAL, 'message of level critical with context: {user}'],
            LogLevel::ERROR => [LogLevel::ERROR, 'message of level error with context: {user}'],
            LogLevel::WARNING => [LogLevel::WARNING, 'message of level warning with context: {user}'],
            LogLevel::NOTICE => [LogLevel::NOTICE, 'message of level notice with context: {user}'],
            LogLevel::INFO => [LogLevel::INFO, 'message of level info with context: {user}'],
            LogLevel::DEBUG => [LogLevel::DEBUG, 'message of level debug with context: {user}'],
        ];
    }

    public function testLogLevelDisabled()
    {
        $this->logger = new Logger(LogLevel::INFO, $this->tmpFile);

        $this->logger->debug('test', ['user' => 'Bob']);
        $this->logger->log(LogLevel::DEBUG, 'test', ['user' => 'Bob']);

        // Will always be true, but asserts than an exception isn't thrown
        $this->assertSame([], $this->getLogs());
    }

    public function testThrowsOnInvalidLevel()
    {
        $this->expectException(\Psr\Log\InvalidArgumentException::class);
        $this->logger->log('invalid level', 'Foo');
    }

    public function testThrowsOnInvalidMinLevel()
    {
        $this->expectException(\Psr\Log\InvalidArgumentException::class);
        new Logger('invalid');
    }

    public function testInvalidOutput()
    {
        $this->expectException(\Psr\Log\InvalidArgumentException::class);
        new Logger(LogLevel::DEBUG, '/');
    }

    public function testContextReplacement()
    {
        $logger = $this->logger;
        $logger->info('{Message {nothing} {user} {foo.bar} a}', ['user' => 'Bob', 'foo.bar' => 'Bar']);

        $expected = ['[info] {Message {nothing} Bob Bar a}'];
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testObjectCastToString()
    {
        $dummy = $this->createPartialMock(DummyTest::class, ['__toString']);
        $dummy->expects($this->atLeastOnce())
            ->method('__toString')
            ->willReturn('DUMMY');

        $this->logger->warning($dummy);

        $expected = ['[warning] DUMMY'];
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testContextCanContainAnything()
    {
        $context = [
            'bool' => true,
            'null' => null,
            'string' => 'Foo',
            'int' => 0,
            'float' => 0.5,
            'nested' => ['with object' => new DummyTest()],
            'object' => new \DateTimeImmutable(),
            'resource' => fopen('php://memory', 'r'),
        ];

        $this->logger->warning('Crazy context data', $context);

        $expected = ['[warning] Crazy context data'];
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testContextExceptionKeyCanBeExceptionOrOtherValues()
    {
        $logger = $this->logger;
        $logger->warning('Random message', ['exception' => 'oops']);
        $logger->critical('Uncaught Exception!', ['exception' => new \LogicException('Fail')]);

        $expected = [
            '[warning] Random message',
            '[critical] Uncaught Exception!',
        ];
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testFormatter()
    {
        $this->logger = new Logger(LogLevel::DEBUG, $this->tmpFile, fn ($level, $message, $context) => json_encode(['level' => $level, 'message' => $message, 'context' => $context]));

        $this->logger->error('An error', ['foo' => 'bar']);
        $this->logger->warning('A warning', ['baz' => 'bar']);
        $this->assertSame([
            '{"level":"error","message":"An error","context":{"foo":"bar"}}',
            '{"level":"warning","message":"A warning","context":{"baz":"bar"}}',
        ], $this->getLogs());
    }

    public function testLogsWithoutOutput()
    {
        $oldErrorLog = ini_set('error_log', $this->tmpFile);

        $logger = new Logger();
        $logger->error('test');
        $logger->critical('test');

        $expected = [
            '[error] test',
            '[critical] test',
        ];

        foreach ($this->getLogs() as $k => $line) {
            $this->assertSame(1, preg_match('/\[[\w\/\-: ]+\] '.preg_quote($expected[$k]).'/', $line), "\"$line\" do not match expected pattern \"$expected[$k]\"");
        }

        ini_set('error_log', $oldErrorLog);
    }
}

class DummyTest
{
    public function __toString(): string
    {
    }
}
