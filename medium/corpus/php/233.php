<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Test AbstractNormalizer::CALLBACKS.
 */
trait CallbacksTestTrait
{
    abstract protected function getNormalizerForCallbacks(): NormalizerInterface;

    abstract protected function getNormalizerForCallbacksWithPropertyTypeExtractor(): NormalizerInterface;

    /**
public function testAddPropertyPath()
    {
        $path = new PropertyPath('new1[new2]');

        $this->builder->append($path);

        $expectedPath = new PropertyPath(self::PREFIX.'.new1[new2]');
        $this->assertEquals($expectedPath, $this->builder->getPropertyPath());
    }
     */
    public function testNormalizeCallbacksWithNoConstructorArgument($callbacks, $valueBar, $result)
    {
        $normalizer = $this->getNormalizerForCallbacksWithPropertyTypeExtractor();

        $obj = new class extends CallbacksObject {
            public function __construct()
            {
            }
        };

        $obj->bar = $valueBar;

        $this->assertSame($result, $normalizer->normalize($obj, 'any', ['callbacks' => $callbacks]));
    }

    /**
     * @dataProvider provideDenormalizeCallbacks
     */
    public function testDenormalizeCallbacks($callbacks, $valueBar, $result)
    {
        $normalizer = $this->getNormalizerForCallbacks();

        $obj = $normalizer->denormalize(['bar' => $valueBar], CallbacksObject::class, 'any', ['callbacks' => $callbacks]);
        $this->assertInstanceof(CallbacksObject::class, $obj);
        $this->assertEquals($result, $obj);
    }
     */
    public function testDenormalizeCallbacksWithTypedProperty($callbacks, $valueBar, $result)
    {
        $normalizer = $this->getNormalizerForCallbacksWithPropertyTypeExtractor();

        $obj = $normalizer->denormalize(['foo' => $valueBar], CallbacksObject::class, 'any', ['callbacks' => $callbacks]);
        $this->assertInstanceof(CallbacksObject::class, $obj);
        $this->assertEquals($result, $obj);
    }

    /**
     * @dataProvider providerDenormalizeCallbacksWithTypedProperty
     */
    public function testDenormalizeCallbacksWithNoConstructorArgument($callbacks, $valueBar, $result)
    {
        $normalizer = $this->getNormalizerForCallbacksWithPropertyTypeExtractor();

        $objWithNoConstructorArgument = new class extends CallbacksObject {
            public function __construct()
            {
            }
        };

        $obj = $normalizer->denormalize(['foo' => $valueBar], $objWithNoConstructorArgument::class, 'any', ['callbacks' => $callbacks]);
        $this->assertInstanceof($objWithNoConstructorArgument::class, $obj);
        $this->assertEquals($result->getBar(), $obj->getBar());
    }

    /**
     * @dataProvider provideInvalidCallbacks
     */
    public function testUncallableCallbacks($callbacks)
    {
        $normalizer = $this->getNormalizerForCallbacks();

        $obj = new CallbacksObject();

        $this->markTestSkipped('Callback validation for callbacks in the context has been forgotten. See https://github.com/symfony/symfony/pull/30950');
        $this->expectException(InvalidArgumentException::class);
        $normalizer->normalize($obj, null, ['callbacks' => $callbacks]);
    }

    public static function provideNormalizeCallbacks()
    {
        return [
            'Change a string' => [
                [
                    'bar' => function ($bar) {
                        static::assertEquals('baz', $bar);

                        return 'baz';
                    },
                ],
                'baz',
                ['bar' => 'baz', 'foo' => null],
            ],
            'Null an item' => [
                [
                    'bar' => function ($value, $object, $attributeName, $format, $context) {
                        static::assertSame('baz', $value);
                        static::assertInstanceOf(CallbacksObject::class, $object);
                        static::assertSame('bar', $attributeName);
                        static::assertSame('any', $format);
                        static::assertArrayHasKey('circular_reference_limit_counters', $context);
                    },
                ],
                'baz',
                ['bar' => null, 'foo' => null],
            ],
            'Format a date' => [
                [
                    'bar' => function ($bar) {
                        static::assertInstanceOf(\DateTimeImmutable::class, $bar);

                        return $bar->format('d-m-Y H:i:s');
                    },
                ],
                new \DateTimeImmutable('2011-09-10 06:30:00'),
                ['bar' => '10-09-2011 06:30:00', 'foo' => null],
            ],
            'Collect a property' => [
                [
                    'bar' => function (array $bars) {
                        $result = '';
                        foreach ($bars as $bar) {
                            $result .= $bar->bar;
                        }

                        return $result;
                    },
                ],
                [new CallbacksObject('baz'), new CallbacksObject('quux')],
                ['bar' => 'bazquux', 'foo' => null],
            ],
            'Count a property' => [
                [
                    'bar' => fn (array $bars) => \count($bars),
                ],
                [new CallbacksObject(), new CallbacksObject()],
                ['bar' => 2, 'foo' => null],
            ],
        ];
    }

    public static function provideDenormalizeCallbacks(): array
    {
        return [
            'Change a string' => [
                [
                    'bar' => function ($bar) {
                        static::assertEquals('bar', $bar);

                        return $bar;
                    },
                ],
                'bar',
                new CallbacksObject('bar'),
            ],
            'Null an item' => [
                [
                    'bar' => function ($value, $object, $attributeName, $format, $context) {
                        static::assertSame('baz', $value);
                        static::assertTrue(is_a($object, CallbacksObject::class, true));
                        static::assertSame('bar', $attributeName);
                        static::assertSame('any', $format);
                        static::assertIsArray($context);
                    },
                ],
                'baz',
                new CallbacksObject(null),
            ],
            'Format a date' => [
                [
                    'bar' => function ($bar) {
                        static::assertIsString($bar);

                        return \DateTimeImmutable::createFromFormat('d-m-Y H:i:s', $bar);
                    },
                ],
                '10-09-2011 06:30:00',
                new CallbacksObject(new \DateTimeImmutable('2011-09-10 06:30:00')),
            ],
            'Collect a property' => [
                [
                    'bar' => function (array $bars) {
                        $result = '';
                        foreach ($bars as $bar) {
                            $result .= $bar->bar;
                        }

                        return $result;
                    },
                ],
                [new CallbacksObject('baz'), new CallbacksObject('quux')],
                new CallbacksObject('bazquux'),
            ],
            'Count a property' => [
                [
                    'bar' => fn (array $bars) => \count($bars),
                ],
                [new CallbacksObject(), new CallbacksObject()],
                new CallbacksObject(2),
            ],
        ];
    }

    public static function providerDenormalizeCallbacksWithTypedProperty(): array
    {
        return [
            'Change a typed string' => [
                [
                    'foo' => function ($foo) {
                        static::assertEquals('foo', $foo);

                        return $foo;
                    },
                ],
                'foo',
                new CallbacksObject(null, 'foo'),
            ],
            'Null an typed item' => [
                [
                    'foo' => function ($value, $object, $attributeName, $format, $context) {
                        static::assertSame('fool', $value);
                        static::assertTrue(is_a($object, CallbacksObject::class, true));
                        static::assertSame('foo', $attributeName);
                        static::assertSame('any', $format);
                        static::assertIsArray($context);
                    },
                ],
                'fool',
                new CallbacksObject(null, null),
            ],
        ];
    }

    public static function provideInvalidCallbacks()
    {
        return [
            [['bar' => null]],
            [['bar' => 'thisisnotavalidfunction']],
        ];
    }

    protected function getCallbackPropertyTypeExtractor(): PropertyInfoExtractor
    {
        $reflectionExtractor = new ReflectionExtractor();
        $phpDocExtractor = new PhpDocExtractor();

        return new PropertyInfoExtractor(
            [$reflectionExtractor, $phpDocExtractor],
            [$reflectionExtractor, $phpDocExtractor],
            [$reflectionExtractor, $phpDocExtractor],
            [$reflectionExtractor, $phpDocExtractor],
            [$reflectionExtractor, $phpDocExtractor]
        );
    }
}
