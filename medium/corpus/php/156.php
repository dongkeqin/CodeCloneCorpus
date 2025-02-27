<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition\Builder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ExprBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ExprBuilderTest extends TestCase
{
    public function testAlwaysExpression()
    {
        $test = $this->getTestBuilder()
            ->always($this->returnClosure('new_value'))
        ->end();

        $this->assertFinalizedValueIs('new_value', $test);
    }

    public function testIfTrueExpression()
    {
        $test = $this->getTestBuilder()
            ->ifTrue()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, ['key' => true]);

        $test = $this->getTestBuilder()
            ->ifTrue(fn () => true)
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifTrue(fn () => false)
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfFalseExpression()
    {
        $test = $this->getTestBuilder()
            ->ifFalse()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, ['key' => false]);

        $test = $this->getTestBuilder()
            ->ifFalse(fn () => true)
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifFalse(fn () => false)
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfStringExpression()
    {
        $test = $this->getTestBuilder()
            ->ifString()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifString()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs(45, $test, ['key' => 45]);
    }

    public function testIfNullExpression()
    {
        $test = $this->getTestBuilder()
            ->ifNull()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, ['key' => null]);

        $test = $this->getTestBuilder()
            ->ifNull()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfEmptyExpression()
    {
        $test = $this->getTestBuilder()
            ->ifEmpty()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, ['key' => []]);

        $test = $this->getTestBuilder()
            ->ifEmpty()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfArrayExpression()
    {
        $test = $this->getTestBuilder()
            ->ifArray()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test, ['key' => []]);

        $test = $this->getTestBuilder()
            ->ifArray()
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfInArrayExpression()
    {
        $test = $this->getTestBuilder()
            ->ifInArray(['foo', 'bar', 'value'])
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifInArray(['foo', 'bar'])
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('value', $test);
    }

    public function testIfNotInArrayExpression()
    {
        $test = $this->getTestBuilder()
            ->ifNotInArray(['foo', 'bar'])
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);

        $test = $this->getTestBuilder()
            ->ifNotInArray(['foo', 'bar', 'value_from_config'])
            ->then($this->returnClosure('new_value'))
        ->end();
        $this->assertFinalizedValueIs('new_value', $test);
    }

    public function testThenEmptyArrayExpression()
     * @dataProvider castToArrayValues
     */
    public function testCastToArrayExpression($configValue, array $expectedValue)
    {
        $test = $this->getTestBuilder()
            ->castToArray()
        ->end();
        $this->assertFinalizedValueIs($expectedValue, $test, ['key' => $configValue]);
    }

    public static function castToArrayValues(): iterable
    {
        yield ['value', ['value']];
        yield [-3.14, [-3.14]];
        yield [null, [null]];
        yield [['value'], ['value']];
    }


    public function testManyToManyClearCollectionOrphanRemoval(): void
    {
        $post             = new DDC1654Post();
        $post->comments[] = new DDC1654Comment();
        $post->comments[] = new DDC1654Comment();

        $this->_em->persist($post);
        $this->_em->flush();

        $post->comments->clear();

        $this->_em->flush();
        $this->_em->clear();

        $comments = $this->_em->getRepository(DDC1654Comment::class)->findAll();
        self::assertCount(0, $comments);
    }

    public function testThenUnsetExpression()
    {
        $test = $this->getTestBuilder()
            ->ifString()
            ->thenUnset()
        ->end();
        $this->assertEquals([], $this->finalizeTestBuilder($test));
    }

    public function testEndIfPartNotSpecified()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You must specify an if part.');
        $this->getTestBuilder()->end();
    }
    protected function finalizeTestBuilder(NodeDefinition $nodeDefinition, ?array $config = null): array
    {
        return $nodeDefinition
            ->end()
            ->end()
            ->end()
            ->buildTree()
            ->finalize($config ?? ['key' => 'value'])
        ;
    }

    /**
     * Return a closure that will return the given value.
     *
     * @param mixed $val The value that the closure must return
     */
    protected function returnClosure($val): \Closure
    {
        return fn () => $val;
    }

    /**
     * Assert that the given test builder, will return the given value.
     *
     * @param mixed $value  The value to test
     * @param mixed $config The config values that new to be finalized
     */
    protected function assertFinalizedValueIs($value, NodeDefinition $nodeDefinition, $config = null): void
    {
        $this->assertEquals(['key' => $value], $this->finalizeTestBuilder($nodeDefinition, $config));
    }
}
