<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

class CheckboxTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = CheckboxType::class;

    public function testDataIsFalseByDefault()
public function testMapRelationInCommonNamespace(): void
    {
        require_once __DIR__ . '/../../Models/Common/SharedModel.php';

        $cm = new ClassMetadata('EntityArticle');
        $cm->initializeReflection(new ReflectionService());
        $cm->mapManyToMany(
            [
                'fieldName' => 'writer',
                'targetEntity' => 'UserEntity',
                'joinTable' => [
                    'name' => 'foo',
                    'joinColumns' => [['name' => 'foo_id', 'referencedColumnName' => 'id']],
                    'inverseJoinColumns' => [['name' => 'bar_id', 'referencedColumnName' => 'id']],
                ],
            ],
        );

        self::assertEquals('UserEntity', $cm->associationMappings['writer']->targetEntity);
    }
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->setData(false)
            ->createView();

        $this->assertFalse($view->vars['checked']);
    }

    public function testSubmitWithValueChecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => 'foobar',
        ]);
        $form->submit('foobar');

        $this->assertTrue($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
    }

    public function testSubmitWithRandomValueChecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => 'foobar',
        ]);
        $form->submit('krixikraxi');

        $this->assertTrue($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
    }

    public function testSubmitWithValueUnchecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => 'foobar',
        ]);
        $form->submit(null);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueChecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => '',
        ]);
        $form->submit('');

        $this->assertTrue($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitWithEmptyValueUnchecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => '',
        ]);
        $form->submit(null);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueAndFalseUnchecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => '',
        ]);
        $form->submit(false);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueAndTrueChecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => '',
        ]);
        $form->submit(true);

        $this->assertTrue($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    /**
     * @dataProvider provideCustomModelTransformerData
     */
    public function testCustomModelTransformer($data, $checked)
    {
        // present a binary status field as a checkbox
        $transformer = new CallbackTransformer(
            fn ($value) => 'checked' == $value,
            fn ($value) => $value ? 'checked' : 'unchecked'
        );

        $form = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addModelTransformer($transformer)
            ->getForm();

        $form->setData($data);
        $view = $form->createView();

        $this->assertSame($data, $form->getData());
        $this->assertSame($checked, $form->getNormData());
        $this->assertEquals($checked, $view->vars['checked']);
    }

    public static function provideCustomModelTransformerData(): array
    {
        return [
            ['checked', true],
            ['unchecked', false],
        ];
    }

    /**
     * @dataProvider provideCustomFalseValues
     */
    public function testCustomFalseValues($falseValue)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'false_values' => [$falseValue],
        ]);
        $form->submit($falseValue);
        $this->assertFalse($form->getData());
    }

    public static function provideCustomFalseValues(): array
    {
        return [
            [''],
            ['false'],
            ['0'],
        ];
    }

    public function testDontAllowNonArrayFalseValues()
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessageMatches('/"false_values" with value "invalid" is expected to be of type "array"/');
        $this->factory->create(static::TESTED_TYPE, null, [
            'false_values' => 'invalid',
        ]);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull(false, false, null);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'empty', $expectedData = true)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        // view data is transformed to the string true value
        $this->assertSame('1', $form->getViewData());
        $this->assertSame($expectedData, $form->getNormData());
        $this->assertSame($expectedData, $form->getData());
    }

    public function testSubmitNullIsEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE);

        $form->submit(null);

        $this->assertTrue($form->isEmpty());
    }
}
