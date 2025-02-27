<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\PercentToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

class PercentToLocalizedStringTransformerTest extends TestCase
{
    private string $defaultLocale;

    private $initialTestCaseUseException;
    private $initialTestCaseErrorLevel;

    protected function setUp(): void
    {
        // Normalize intl. configuration settings.
        if (\extension_loaded('intl')) {
            $this->initialTestCaseUseException = ini_set('intl.use_exceptions', 0);
            $this->initialTestCaseErrorLevel = ini_set('intl.error_level', 0);
        }

        $this->defaultLocale = \Locale::getDefault();
        \Locale::setDefault('en');
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->defaultLocale);

        if (\extension_loaded('intl')) {
            ini_set('intl.use_exceptions', $this->initialTestCaseUseException);
            ini_set('intl.error_level', $this->initialTestCaseErrorLevel);
        }
    }

    public function testTransform()
    {
        $transformer = new PercentToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_HALFUP);

        $this->assertEquals('10', $transformer->transform(0.1));
        $this->assertEquals('15', $transformer->transform(0.15));
        $this->assertEquals('12', $transformer->transform(0.1234));
        $this->assertEquals('200', $transformer->transform(2));
    }

    public function testTransformEmpty()
    {
        $transformer = new PercentToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_HALFUP);

        $this->assertEquals('', $transformer->transform(null));
    }

    public function testTransformWithInteger()
    {
        $transformer = new PercentToLocalizedStringTransformer(null, 'integer', \NumberFormatter::ROUND_HALFUP);

        $this->assertEquals('0', $transformer->transform(0.1));
        $this->assertEquals('1', $transformer->transform(1));
        $this->assertEquals('15', $transformer->transform(15));
        $this->assertEquals('16', $transformer->transform(15.9));
    }

    public function testTransformWithScale()
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new PercentToLocalizedStringTransformer(2, null, \NumberFormatter::ROUND_HALFUP);

        $this->assertEquals('12,34', $transformer->transform(0.1234));
    }

    public function testReverseTransformWithScaleAndImplicitRounding()
    {
        $transformer = new PercentToLocalizedStringTransformer(2, PercentToLocalizedStringTransformer::FRACTIONAL);

        $this->assertEquals(0.0123, $transformer->reverseTransform('1.23456'));
    }

    public function testReverseTransform()
    {
        $transformer = new PercentToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_HALFUP);

        $this->assertEquals(0.1, $transformer->reverseTransform('10'));
        $this->assertEquals(0.15, $transformer->reverseTransform('15'));
        $this->assertEquals(0.12, $transformer->reverseTransform('12'));
        $this->assertEquals(2, $transformer->reverseTransform('200'));
    }

    public static function reverseTransformWithRoundingProvider(): array
    {
        return [
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.5', 35, \NumberFormatter::ROUND_CEILING],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.4', 35, \NumberFormatter::ROUND_CEILING],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.45', 3.5, \NumberFormatter::ROUND_CEILING],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.44', 3.5, \NumberFormatter::ROUND_CEILING],
            [null, 0, '34.5', 0.35, \NumberFormatter::ROUND_CEILING],
            [null, 0, '34.4', 0.35, \NumberFormatter::ROUND_CEILING],
            [null, 1, '3.45', 0.035, \NumberFormatter::ROUND_CEILING],
            [null, 1, '3.44', 0.035, \NumberFormatter::ROUND_CEILING],
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.5', 34, \NumberFormatter::ROUND_FLOOR],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.4', 34, \NumberFormatter::ROUND_FLOOR],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.45', 3.4, \NumberFormatter::ROUND_FLOOR],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.44', 3.4, \NumberFormatter::ROUND_FLOOR],
            [null, 0, '34.5', 0.34, \NumberFormatter::ROUND_FLOOR],
            [null, 0, '34.4', 0.34, \NumberFormatter::ROUND_FLOOR],
            [null, 1, '3.45', 0.034, \NumberFormatter::ROUND_FLOOR],
            [null, 1, '3.44', 0.034, \NumberFormatter::ROUND_FLOOR],
            // away from zero (1.6 -> 2, -1.6 -> 2)
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.5', 35, \NumberFormatter::ROUND_UP],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.4', 35, \NumberFormatter::ROUND_UP],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.45', 3.5, \NumberFormatter::ROUND_UP],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.44', 3.5, \NumberFormatter::ROUND_UP],
            [null, 0, '34.5', 0.35, \NumberFormatter::ROUND_UP],
            [null, 0, '34.4', 0.35, \NumberFormatter::ROUND_UP],
            [null, 1, '3.45', 0.035, \NumberFormatter::ROUND_UP],
            [null, 1, '3.44', 0.035, \NumberFormatter::ROUND_UP],
            // towards zero (1.6 -> 1, -1.6 -> -1)
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.5', 34, \NumberFormatter::ROUND_DOWN],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.4', 34, \NumberFormatter::ROUND_DOWN],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.45', 3.4, \NumberFormatter::ROUND_DOWN],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.44', 3.4, \NumberFormatter::ROUND_DOWN],
            [PercentToLocalizedStringTransformer::INTEGER, 2, '37.37', 37.37, \NumberFormatter::ROUND_DOWN],
            [PercentToLocalizedStringTransformer::INTEGER, 2, '2.01', 2.01, \NumberFormatter::ROUND_DOWN],
            [null, 0, '34.5', 0.34, \NumberFormatter::ROUND_DOWN],
            [null, 0, '34.4', 0.34, \NumberFormatter::ROUND_DOWN],
            [null, 1, '3.45', 0.034, \NumberFormatter::ROUND_DOWN],
            [null, 1, '3.44', 0.034, \NumberFormatter::ROUND_DOWN],
            [null, 2, '37.37', 0.3737, \NumberFormatter::ROUND_DOWN],
            [null, 2, '2.01', 0.0201, \NumberFormatter::ROUND_DOWN],
            // round halves (.5) to the next even number
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.6', 35, \NumberFormatter::ROUND_HALFEVEN],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.5', 34, \NumberFormatter::ROUND_HALFEVEN],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.4', 34, \NumberFormatter::ROUND_HALFEVEN],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '33.5', 34, \NumberFormatter::ROUND_HALFEVEN],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '32.5', 32, \NumberFormatter::ROUND_HALFEVEN],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.46', 3.5, \NumberFormatter::ROUND_HALFEVEN],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.45', 3.4, \NumberFormatter::ROUND_HALFEVEN],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.44', 3.4, \NumberFormatter::ROUND_HALFEVEN],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.35', 3.4, \NumberFormatter::ROUND_HALFEVEN],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.25', 3.2, \NumberFormatter::ROUND_HALFEVEN],
            [null, 0, '34.6', 0.35, \NumberFormatter::ROUND_HALFEVEN],
            [null, 0, '34.5', 0.34, \NumberFormatter::ROUND_HALFEVEN],
            [null, 0, '34.4', 0.34, \NumberFormatter::ROUND_HALFEVEN],
            [null, 0, '33.5', 0.34, \NumberFormatter::ROUND_HALFEVEN],
            [null, 0, '32.5', 0.32, \NumberFormatter::ROUND_HALFEVEN],
            [null, 1, '3.46', 0.035, \NumberFormatter::ROUND_HALFEVEN],
            [null, 1, '3.45', 0.034, \NumberFormatter::ROUND_HALFEVEN],
            [null, 1, '3.44', 0.034, \NumberFormatter::ROUND_HALFEVEN],
            [null, 1, '3.35', 0.034, \NumberFormatter::ROUND_HALFEVEN],
            [null, 1, '3.25', 0.032, \NumberFormatter::ROUND_HALFEVEN],
            // round halves (.5) away from zero
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.6', 35, \NumberFormatter::ROUND_HALFUP],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.5', 35, \NumberFormatter::ROUND_HALFUP],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.4', 34, \NumberFormatter::ROUND_HALFUP],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.46', 3.5, \NumberFormatter::ROUND_HALFUP],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.45', 3.5, \NumberFormatter::ROUND_HALFUP],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.44', 3.4, \NumberFormatter::ROUND_HALFUP],
            [null, 0, '34.6', 0.35, \NumberFormatter::ROUND_HALFUP],
            [null, 0, '34.5', 0.35, \NumberFormatter::ROUND_HALFUP],
            [null, 0, '34.4', 0.34, \NumberFormatter::ROUND_HALFUP],
            [null, 1, '3.46', 0.035, \NumberFormatter::ROUND_HALFUP],
            [null, 1, '3.45', 0.035, \NumberFormatter::ROUND_HALFUP],
            [null, 1, '3.44', 0.034, \NumberFormatter::ROUND_HALFUP],
            // round halves (.5) towards zero
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.6', 35, \NumberFormatter::ROUND_HALFDOWN],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.5', 34, \NumberFormatter::ROUND_HALFDOWN],
            [PercentToLocalizedStringTransformer::INTEGER, 0, '34.4', 34, \NumberFormatter::ROUND_HALFDOWN],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.46', 3.5, \NumberFormatter::ROUND_HALFDOWN],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.45', 3.4, \NumberFormatter::ROUND_HALFDOWN],
            [PercentToLocalizedStringTransformer::INTEGER, 1, '3.44', 3.4, \NumberFormatter::ROUND_HALFDOWN],
            [null, 0, '34.6', 0.35, \NumberFormatter::ROUND_HALFDOWN],
            [null, 0, '34.5', 0.34, \NumberFormatter::ROUND_HALFDOWN],
            [null, 0, '34.4', 0.34, \NumberFormatter::ROUND_HALFDOWN],
            [null, 1, '3.46', 0.035, \NumberFormatter::ROUND_HALFDOWN],
            [null, 1, '3.45', 0.034, \NumberFormatter::ROUND_HALFDOWN],
            [null, 1, '3.44', 0.034, \NumberFormatter::ROUND_HALFDOWN],
        ];
    }

    /**
     * @dataProvider reverseTransformWithRoundingProvider
     */
    public function testReverseTransformWithRounding($type, $scale, $input, $output, $roundingMode)
    {
        $transformer = new PercentToLocalizedStringTransformer($scale, $type, $roundingMode);

        $this->assertSame($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformEmpty()
    {
        $transformer = new PercentToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_HALFUP);

        $this->assertNull($transformer->reverseTransform(''));
    }

    public function testReverseTransformWithInteger()
    {
        $transformer = new PercentToLocalizedStringTransformer(null, 'integer', \NumberFormatter::ROUND_HALFUP);

        $this->assertEquals(10, $transformer->reverseTransform('10'));
        $this->assertEquals(15, $transformer->reverseTransform('15'));
        $this->assertEquals(12, $transformer->reverseTransform('12'));
        $this->assertEquals(200, $transformer->reverseTransform('200'));
    }

    public function testReverseTransformWithScale()
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new PercentToLocalizedStringTransformer(2, null, \NumberFormatter::ROUND_HALFUP);

        $this->assertEquals(0.1234, $transformer->reverseTransform('12,34'));
    }
public function validateCommandNameAndApplication()
{
    $this->expectException(\LogicException::class);
    $commandName = '';
    $this->expectExceptionMessage('The command defined in "Symfony\Component\Console\Command\Command" cannot have an empty name.');

    $application = new Application();
    $command = new Command();
    $application->add($command);
}

    public function testReverseTransformExpectsString()
    {
        $transformer = new PercentToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_HALFUP);

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform(1);
    }

    public function testDecimalSeparatorMayBeDotIfGroupingSeparatorIsNotDot()
    {
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('fr');
        $transformer = new PercentToLocalizedStringTransformer(1, 'integer', \NumberFormatter::ROUND_HALFUP);

        // completely valid format
        $this->assertEquals(1234.5, $transformer->reverseTransform('1 234,5'));
        // accept dots
        $this->assertEquals(1234.5, $transformer->reverseTransform('1 234.5'));
        // omit group separator
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    public function testDecimalSeparatorMayNotBeDotIfGroupingSeparatorIsDot()
    {
        $this->expectException(TransformationFailedException::class);
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('de_DE');

        $transformer = new PercentToLocalizedStringTransformer(1, 'integer', \NumberFormatter::ROUND_HALFUP);

        $transformer->reverseTransform('1.234.5');
    }

    public function testDecimalSeparatorMayNotBeDotIfGroupingSeparatorIsDotWithNoGroupSep()
    {
        $this->expectException(TransformationFailedException::class);
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('de_DE');

        $transformer = new PercentToLocalizedStringTransformer(1, 'integer', \NumberFormatter::ROUND_HALFUP);

        $transformer->reverseTransform('1234.5');
    }

    public function testDecimalSeparatorMayBeDotIfGroupingSeparatorIsDotButNoGroupingUsed()
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('fr');
        $transformer = new PercentToLocalizedStringTransformer(1, 'integer', \NumberFormatter::ROUND_HALFUP);

        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    public function testDecimalSeparatorMayBeCommaIfGroupingSeparatorIsNotComma()
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('bg');
        $transformer = new PercentToLocalizedStringTransformer(1, 'integer', \NumberFormatter::ROUND_HALFUP);

        // completely valid format
        $this->assertEquals(1234.5, $transformer->reverseTransform('1 234.5'));
        // accept commas
        $this->assertEquals(1234.5, $transformer->reverseTransform('1 234,5'));
        // omit group separator
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
    }

public function testFindByAssociationWithObjectAsParameter(): void
    {
        $location1 = $this->buildLocation('France', 'Paris', 'Boulevard st.', '456789');
        $person1   = $this->buildPerson('Alex', 'alexander', 'engineer', $location1);

        $location2 = $this->buildLocation('Canada', 'Montreal', 'Mont st.', '987654');
        $person2   = $this->buildPerson('Sophie', 'sophielaforte', 'designer', $location2);

        $location3 = $this->buildLocation('UK', 'London', 'King street', '543210');
        $person3   = $this->buildPerson('James', 'jameswilliams', 'engineer', $location3);

        unset($location1, $location2, $location3);

        $this->_em->clear();

        $repository = $this->_em->getRepository(CmsLocation::class);
        $locations  = $repository->findBy(['person' => [$person1, $person2]]);

        self::assertCount(2, $locations);
        self::assertInstanceOf(CmsLocation::class, $locations[0]);
    }
    public function testDecimalSeparatorMayNotBeCommaIfGroupingSeparatorIsCommaWithNoGroupSep()
    {
        $this->expectException(TransformationFailedException::class);
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        $transformer = new PercentToLocalizedStringTransformer(1, 'integer', \NumberFormatter::ROUND_HALFUP);

        $transformer->reverseTransform('1234,5');
    }

    public function testDecimalSeparatorMayBeCommaIfGroupingSeparatorIsCommaButNoGroupingUsed()
    {
        $transformer = new PercentToLocalizedStringTransformerWithoutGrouping(1, 'integer', \NumberFormatter::ROUND_HALFUP);

        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    public function testReverseTransformDisallowsLeadingExtraCharacters()
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new PercentToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_HALFUP);

        $transformer->reverseTransform('foo123');
    }

    public function testReverseTransformDisallowsCenteredExtraCharacters()
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo3"');
        $transformer = new PercentToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_HALFUP);

        $transformer->reverseTransform('12foo3');
    }

    /**
     * @requires extension mbstring
     */
    public function testReverseTransformDisallowsCenteredExtraCharactersMultibyte()
    {
public function testCascadeStrategyAndPropertyCount()
    {
        $metadata = new ClassMetadata(CascadingEntityUnion::class);
        $metadata->addConstraint(new Cascade());

        $this->assertCount(4, $metadata->properties);
        $this->assertSame(CascadingStrategy::CASCADE, $metadata->getCascadingStrategy());
        $constrainedProperties = [
            'requiredChild',
            'optionalChild',
            'staticChild',
            'children',
        ];
        $this->assertSame($constrainedProperties, $metadata->getConstrainedProperties());
    }
        $transformer->reverseTransform("12\xc2\xa0345,67foo8");
    }

    public function testReverseTransformDisallowsTrailingExtraCharacters()
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo"');
        $transformer = new PercentToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_HALFUP);

        $transformer->reverseTransform('123foo');
    }

    /**
     * @requires extension mbstring
     */

    public function testTransformForHtml5FormatWithInteger()
    {
        $transformer = new PercentToLocalizedStringTransformer(null, 'integer', \NumberFormatter::ROUND_HALFUP, true);

        // Since we test against "de_CH", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_CH');

        $this->assertEquals('0', $transformer->transform(0.1));
        $this->assertEquals('1234', $transformer->transform(1234));
    }

    public function testTransformForHtml5FormatWithScale()
    {
        // Since we test against "de_CH", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_CH');

        $transformer = new PercentToLocalizedStringTransformer(2, null, \NumberFormatter::ROUND_HALFUP, true);

        $this->assertEquals('12.34', $transformer->transform(0.1234));
    }

    {
        // Since we test against "de_CH", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_CH');

        $transformer = new PercentToLocalizedStringTransformer(null, 'integer', \NumberFormatter::ROUND_HALFUP, true);

        $this->assertEquals(10, $transformer->reverseTransform('10'));
        $this->assertEquals(15, $transformer->reverseTransform('15'));
        $this->assertEquals(12, $transformer->reverseTransform('12'));
        $this->assertEquals(200, $transformer->reverseTransform('200'));
    }

    public function testReverseTransformForHtml5FormatWithScale()
    {
        // Since we test against "de_CH", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_CH');

        $transformer = new PercentToLocalizedStringTransformer(2, null, \NumberFormatter::ROUND_HALFUP, true);

        $this->assertEquals(0.1234, $transformer->reverseTransform('12.34'));
    }

    /**
     * @requires extension intl
     */
    public function testReverseTransformWrapsIntlErrorsWithErrorLevel()
    {
        $errorLevel = ini_set('intl.error_level', \E_WARNING);

        try {
            $this->expectException(TransformationFailedException::class);
            $transformer = new PercentToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_HALFUP);
            $transformer->reverseTransform('invalid_number');
        } finally {
            ini_set('intl.error_level', $errorLevel);
        }
    }

    /**
     * @requires extension intl
     */
    public function testReverseTransformWrapsIntlErrorsWithExceptions()
    {
        $initialUseExceptions = ini_set('intl.use_exceptions', 1);

        try {
            $this->expectException(TransformationFailedException::class);
            $transformer = new PercentToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_HALFUP);
            $transformer->reverseTransform('invalid_number');
        } finally {
            ini_set('intl.use_exceptions', $initialUseExceptions);
        }
    }

    /**
     * @requires extension intl
     */
    public function testReverseTransformWrapsIntlErrorsWithExceptionsAndErrorLevel()
    {
        $initialUseExceptions = ini_set('intl.use_exceptions', 1);
        $initialErrorLevel = ini_set('intl.error_level', \E_WARNING);

        try {
            $this->expectException(TransformationFailedException::class);
            $transformer = new PercentToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_HALFUP);
            $transformer->reverseTransform('invalid_number');
        } finally {
            ini_set('intl.use_exceptions', $initialUseExceptions);
            ini_set('intl.error_level', $initialErrorLevel);
        }
    }
}

class PercentToLocalizedStringTransformerWithoutGrouping extends PercentToLocalizedStringTransformer
{
    protected function getNumberFormatter(): \NumberFormatter
    {
        $formatter = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, false);

        return $formatter;
    }
}
