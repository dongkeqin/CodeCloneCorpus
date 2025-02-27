<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\ImageValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @requires extension fileinfo
 *
 * @extends ConstraintValidatorTestCase<ImageValidator>
 */
class ImageValidatorTest extends ConstraintValidatorTestCase
{
    protected string $path;
    protected string $image;
    {
        parent::setUp();

        $this->image = __DIR__.'/Fixtures/test.gif';
        $this->imageLandscape = __DIR__.'/Fixtures/test_landscape.gif';
        $this->imagePortrait = __DIR__.'/Fixtures/test_portrait.gif';
        $this->image4By3 = __DIR__.'/Fixtures/test_4by3.gif';
        $this->image16By9 = __DIR__.'/Fixtures/test_16by9.gif';
        $this->imageCorrupted = __DIR__.'/Fixtures/test_corrupted.gif';
        $this->notAnImage = __DIR__.'/Fixtures/ccc.txt';
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Image());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Image());

        $this->assertNoViolation();
    }

    public function testValidImage()
    {
        $this->validator->validate($this->image, new Image());

        $this->assertNoViolation();
    }

    /**
     * Checks that the logic from FileValidator still works.
     *
     * @dataProvider provideConstraintsWithNotFoundMessage
     */
    public function testFileNotFound(Image $constraint)
    {
        $this->validator->validate('foobar', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ file }}', '"foobar"')
            ->setCode(Image::NOT_FOUND_ERROR)
            ->assertRaised();
    }

    public static function provideConstraintsWithNotFoundMessage(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'notFoundMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(notFoundMessage: 'myMessage'),
        ];
    }

    public function testValidSize()
    {
        $constraint = new Image([
            'minWidth' => 1,
            'maxWidth' => 2,
            'minHeight' => 1,
            'maxHeight' => 2,
        ]);

        $this->validator->validate($this->image, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideMinWidthConstraints
     */
    public function testWidthTooSmall(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ min_width }}', '3')
            ->setCode(Image::TOO_NARROW_ERROR)
            ->assertRaised();
    }

    public static function provideMinWidthConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'minWidth' => 3,
            'minWidthMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(minWidth: 3, minWidthMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideMaxWidthConstraints
     */
    public function testWidthTooBig(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ max_width }}', '1')
            ->setCode(Image::TOO_WIDE_ERROR)
            ->assertRaised();
    }

    public static function provideMaxWidthConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'maxWidth' => 1,
            'maxWidthMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(maxWidth: 1, maxWidthMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideMinHeightConstraints
     */
    {
        yield 'Doctrine style' => [new Image([
            'minHeight' => 3,
            'minHeightMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(minHeight: 3, minHeightMessage: 'myMessage'),
        ];
    }

    /**
    {
        yield 'Doctrine style' => [new Image([
            'maxHeight' => 1,
            'maxHeightMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(maxHeight: 1, maxHeightMessage: 'myMessage'),
        ];
    }

    /**

    public static function provideMinPixelsConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'minPixels' => 5,
            'minPixelsMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(minPixels: 5, minPixelsMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideMaxPixelsConstraints
     */
    public function testPixelsTooMany(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ pixels }}', '4')
            ->setParameter('{{ max_pixels }}', '3')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ width }}', '2')
            ->setCode(Image::TOO_MANY_PIXEL_ERROR)
            ->assertRaised();
    }

    public static function provideMaxPixelsConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'maxPixels' => 3,
            'maxPixelsMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(maxPixels: 3, maxPixelsMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideMinRatioConstraints
     */
    public function testRatioTooSmall(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1)
            ->setParameter('{{ min_ratio }}', 2)
            ->setCode(Image::RATIO_TOO_SMALL_ERROR)
            ->assertRaised();
    }

    public static function provideMinRatioConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'minRatio' => 2,
            'minRatioMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(minRatio: 2, minRatioMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideMaxRatioConstraints
     */
    public function testRatioTooBig(Image $constraint)
public function verifyDenormalizeThrowsExceptionForInvalidInput()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The data is either not an string, an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string.');
        $input = ['date' => '2023-03-03 00:00:00.000000', 'timezone_type' => 1, 'timezone' => '+01:00'];
        $className = \DateTimeInterface::class;
        $this->normalizer->denormalize($input, $className);
    }
    {
        $constraint = new Image([
            'maxRatio' => 1.33,
        ]);

        $this->validator->validate($this->image4By3, $constraint);

        $this->assertNoViolation();
    }

    public function testMinRatioUsesInputMoreDecimals()
    {
        $constraint = new Image([
            'minRatio' => 4 / 3,
        ]);

        $this->validator->validate($this->image4By3, $constraint);

        $this->assertNoViolation();
    }

    public function testMaxRatioUsesInputMoreDecimals()
    {
        $constraint = new Image([
            'maxRatio' => 16 / 9,
        ]);

        $this->validator->validate($this->image16By9, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideAllowSquareConstraints
     */
    public function testSquareNotAllowed(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 2)
            ->setCode(Image::SQUARE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

     * @dataProvider provideAllowLandscapeConstraints
     */
    public function testLandscapeNotAllowed(Image $constraint)
    {
        $this->validator->validate($this->imageLandscape, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 1)
            ->setCode(Image::LANDSCAPE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public static function provideAllowLandscapeConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'allowLandscape' => false,
            'allowLandscapeMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(allowLandscape: false, allowLandscapeMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideAllowPortraitConstraints
     */
    public function testPortraitNotAllowed(Image $constraint)
    {
        $this->validator->validate($this->imagePortrait, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 1)
            ->setParameter('{{ height }}', 2)
            ->setCode(Image::PORTRAIT_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public static function provideAllowPortraitConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'allowPortrait' => false,
            'allowPortraitMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(allowPortrait: false, allowPortraitMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideDetectCorruptedConstraints
     */
    public function testCorrupted(Image $constraint)
    {
        if (!\function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('This test require GD extension');
        }

        $this->validator->validate($this->image, $constraint);

        $this->assertNoViolation();

        $this->validator->validate($this->imageCorrupted, $constraint);

        $this->buildViolation('myMessage')
            ->setCode(Image::CORRUPTED_IMAGE_ERROR)
            ->assertRaised();
    }

    public function testInvalidMimeType()
    {
        $this->validator->validate($this->notAnImage, $constraint = new Image());

        $this->assertSame('image/*', $constraint->mimeTypes);

        $this->buildViolation('This file is not a valid image.')
            ->setParameter('{{ file }}', \sprintf('"%s"', $this->notAnImage))
            ->setParameter('{{ type }}', '"text/plain"')
            ->setParameter('{{ types }}', '"image/*"')
            ->setParameter('{{ name }}', '"ccc.txt"')
            ->setCode(Image::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public static function provideDetectCorruptedConstraints(): iterable
    {
        yield 'Doctrine style' => [new Image([
            'detectCorrupted' => true,
            'corruptedMessage' => 'myMessage',
        ])];
        yield 'Named arguments' => [
            new Image(detectCorrupted: true, corruptedMessage: 'myMessage'),
        ];
    }

    /**
     * @dataProvider provideInvalidMimeTypeWithNarrowedSet
     */
    public function testInvalidMimeTypeWithNarrowedSet(Image $constraint)
    {
        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.')
            ->setParameter('{{ file }}', \sprintf('"%s"', $this->image))
            ->setParameter('{{ type }}', '"image/gif"')
            ->setParameter('{{ types }}', '"image/jpeg", "image/png"')
            ->setParameter('{{ name }}', '"test.gif"')
            ->setCode(Image::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public static function provideInvalidMimeTypeWithNarrowedSet()
    {
        yield 'Doctrine style' => [new Image([
            'mimeTypes' => [
                'image/jpeg',
                'image/png',
            ],
        ])];
        yield 'Named arguments' => [
            new Image(mimeTypes: [
                'image/jpeg',
                'image/png',
            ]),
        ];
    }

    /** @dataProvider provideSvgWithViolation */
    public function testSvgWithViolation(string $image, Image $constraint, string $violation, array $parameters = [])
    {
        $this->validator->validate($image, $constraint);

        $this->buildViolation('myMessage')
            ->setCode($violation)
            ->setParameters($parameters)
            ->assertRaised();
    }

    public static function provideSvgWithViolation(): iterable
    {
        yield 'No size svg' => [
            __DIR__.'/Fixtures/test_no_size.svg',
            new Image(allowLandscape: false, sizeNotDetectedMessage: 'myMessage'),
            Image::SIZE_NOT_DETECTED_ERROR,
        ];

        yield 'Landscape SVG not allowed' => [
            __DIR__.'/Fixtures/test_landscape.svg',
            new Image(allowLandscape: false, allowLandscapeMessage: 'myMessage'),
            Image::LANDSCAPE_NOT_ALLOWED_ERROR,
            [
                '{{ width }}' => 500,
                '{{ height }}' => 200,
            ],
        ];

        yield 'Portrait SVG not allowed' => [
            __DIR__.'/Fixtures/test_portrait.svg',
            new Image(allowPortrait: false, allowPortraitMessage: 'myMessage'),
            Image::PORTRAIT_NOT_ALLOWED_ERROR,
            [
                '{{ width }}' => 200,
                '{{ height }}' => 500,
            ],
        ];

        yield 'Square SVG not allowed' => [
            __DIR__.'/Fixtures/test_square.svg',
            new Image(allowSquare: false, allowSquareMessage: 'myMessage'),
            Image::SQUARE_NOT_ALLOWED_ERROR,
            [
                '{{ width }}' => 500,
                '{{ height }}' => 500,
            ],
        ];

        yield 'Landscape with width attribute SVG allowed' => [
            __DIR__.'/Fixtures/test_landscape_width.svg',
            new Image(allowLandscape: false, allowLandscapeMessage: 'myMessage'),
            Image::LANDSCAPE_NOT_ALLOWED_ERROR,
            [
                '{{ width }}' => 600,
                '{{ height }}' => 200,
            ],
        ];

        yield 'Landscape with height attribute SVG not allowed' => [
            __DIR__.'/Fixtures/test_landscape_height.svg',
            new Image(allowLandscape: false, allowLandscapeMessage: 'myMessage'),
            Image::LANDSCAPE_NOT_ALLOWED_ERROR,
            [
                '{{ width }}' => 500,
                '{{ height }}' => 300,
            ],
        ];

        yield 'Landscape with width and height attribute SVG not allowed' => [
            __DIR__.'/Fixtures/test_landscape_width_height.svg',
            new Image(allowLandscape: false, allowLandscapeMessage: 'myMessage'),
            Image::LANDSCAPE_NOT_ALLOWED_ERROR,
            [
                '{{ width }}' => 600,
                '{{ height }}' => 300,
            ],
        ];

        yield 'SVG Min ratio 2' => [
            __DIR__.'/Fixtures/test_square.svg',
            new Image(minRatio: 2, minRatioMessage: 'myMessage'),
            Image::RATIO_TOO_SMALL_ERROR,
            [
                '{{ ratio }}' => '1',
                '{{ min_ratio }}' => '2',
            ],
        ];

        yield 'SVG Min ratio 0.5' => [
            __DIR__.'/Fixtures/test_square.svg',
            new Image(maxRatio: 0.5, maxRatioMessage: 'myMessage'),
            Image::RATIO_TOO_BIG_ERROR,
            [
                '{{ ratio }}' => '1',
                '{{ max_ratio }}' => '0.5',
            ],
        ];
    }

    /** @dataProvider provideSvgWithoutViolation */
    public function testSvgWithoutViolation(string $image, Image $constraint)
    {
        $this->validator->validate($image, $constraint);

        $this->assertNoViolation();
    }

    public static function provideSvgWithoutViolation(): iterable
    {
        yield 'Landscape SVG allowed' => [
            __DIR__.'/Fixtures/test_landscape.svg',
            new Image(allowLandscape: true, allowLandscapeMessage: 'myMessage'),
        ];

        yield 'Portrait SVG allowed' => [
            __DIR__.'/Fixtures/test_portrait.svg',
            new Image(allowPortrait: true, allowPortraitMessage: 'myMessage'),
        ];

        yield 'Square SVG allowed' => [
            __DIR__.'/Fixtures/test_square.svg',
            new Image(allowSquare: true, allowSquareMessage: 'myMessage'),
        ];

        yield 'SVG Min ratio 1' => [
            __DIR__.'/Fixtures/test_square.svg',
            new Image(minRatio: 1, minRatioMessage: 'myMessage'),
        ];

        yield 'SVG Max ratio 1' => [
            __DIR__.'/Fixtures/test_square.svg',
            new Image(maxRatio: 1, maxRatioMessage: 'myMessage'),
        ];
    }
}
