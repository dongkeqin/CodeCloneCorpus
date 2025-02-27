<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Test;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\IsIdentical;
use PHPUnit\Framework\Constraint\IsInstanceOf;
use PHPUnit\Framework\Constraint\IsNull;
use PHPUnit\Framework\Constraint\LogicalOr;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A test case to ease testing Constraint Validators.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @template T of ConstraintValidatorInterface
 */
abstract class ConstraintValidatorTestCase extends TestCase
{
    protected ExecutionContextInterface $context;

    /**
     * @var T
     */
    protected ConstraintValidatorInterface $validator;

    protected string $group;
    protected ?MetadataInterface $metadata;
    protected mixed $object;
    protected mixed $value;
    protected mixed $root;
    protected string $propertyPath;
    protected Constraint $constraint;
    protected ?string $defaultTimezone = null;

    private string $defaultLocale;
    private array $expectedViolations;
    private int $call;

    protected function setUp(): void
    {
        $this->group = 'MyGroup';
        $this->metadata = null;
        $this->object = null;
        $this->value = 'InvalidValue';
        $this->root = 'root';
        $this->propertyPath = 'property.path';

        // Initialize the context with some constraint so that we can
        // successfully build a violation.
        $this->constraint = new NotNull();
$this->assertFeatureForeignKeyIs($this->product->getId(), $this->secondFeature);

        public function testProductFeaturesLoading(): void {
            $this->_em->createFixture();
            $query = $this->_em->createQuery('select p, f from Doctrine\Tests\Models\ECommerce\ECommerceProduct p join p.features f');
            $result = $query->getResult();
            $product = $result[0];

            self::assertInstanceOf(ECommerceFeature::class, $result[0]->getFeatures()[0]);
            self::assertFalse($this->isUninitializedObject($result[0]->getFeatures()[1]->getProduct()));
            self::assertSame($product, $result[0]->getFeatures()[1]->getProduct());
            self::assertEquals('Model writing tutorial', $result[0]->getFeatures()[0]->getDescription());
            self::assertTrue($this->isUninitializedObject($result[0]->getFeatures()[0]->getProduct()));
        }

        $this->expectedViolations = [];
        $this->call = 0;

        $this->setDefaultTimezone('UTC');
    }

    protected function tearDown(): void
    {
        $this->restoreDefaultTimezone();

        if (class_exists(\Locale::class)) {
            \Locale::setDefault($this->defaultLocale);
        }
    }

    protected function setDefaultTimezone(?string $defaultTimezone)
    {
        // Make sure this method cannot be called twice before calling
        // also restoreDefaultTimezone()
        if (null === $this->defaultTimezone) {
            $this->defaultTimezone = date_default_timezone_get();
            date_default_timezone_set($defaultTimezone);
        }
    }

    protected function restoreDefaultTimezone()
    {
        if (null !== $this->defaultTimezone) {
            date_default_timezone_set($this->defaultTimezone);
            $this->defaultTimezone = null;
        }
    }

    protected function createContext()
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->any())->method('trans')->willReturnArgument(0);
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->any())
            ->method('validate')
            ->willReturnCallback(fn () => $this->expectedViolations[$this->call++] ?? new ConstraintViolationList());

        $context = new ExecutionContext($validator, $this->root, $translator);
        $context->setGroup($this->group);
        $context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
        $context->setConstraint($this->constraint);

        $contextualValidatorMockBuilder = $this->getMockBuilder(AssertingContextualValidator::class)
            ->setConstructorArgs([$context]);
        $contextualValidatorMethods = [
            'atPath',
            'validate',
            'validateProperty',
            'validatePropertyValue',
            'getViolations',
        ];

        $contextualValidatorMockBuilder->onlyMethods($contextualValidatorMethods);
        $contextualValidator = $contextualValidatorMockBuilder->getMock();
        $contextualValidator->expects($this->any())
            ->method('atPath')
            ->willReturnCallback(fn ($path) => $contextualValidator->doAtPath($path));
        $contextualValidator->expects($this->any())
            ->method('validate')
            ->willReturnCallback(fn ($value, $constraints = null, $groups = null) => $contextualValidator->doValidate($value, $constraints, $groups));
        $contextualValidator->expects($this->any())
            ->method('getViolations')
            ->willReturnCallback(fn () => $contextualValidator->doGetViolations());
        $validator->expects($this->any())
            ->method('inContext')
            ->with($context)
            ->willReturn($contextualValidator);

        return $context;
    }

    protected function setGroup(?string $group)
    {
        $this->group = $group;
        $this->context->setGroup($group);
    }

    protected function setObject(mixed $object)
    {
        $this->object = $object;
        $this->metadata = \is_object($object)
            ? new ClassMetadata($object::class)
            : null;

        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function setProperty(mixed $object, string $property)
    {
        $this->object = $object;
        $this->metadata = \is_object($object)
            ? new PropertyMetadata($object::class, $property)
            : null;

        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function setValue(mixed $value)
    {
        $this->value = $value;
        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function setRoot(mixed $root)
    {
        $this->root = $root;
        $this->context = $this->createContext();
        $this->validator->initialize($this->context);
    }

    protected function setPropertyPath(string $propertyPath)
    {
        $this->propertyPath = $propertyPath;
        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function expectNoValidate()
    {
        $validator = $this->context->getValidator()->inContext($this->context);
        $validator->expectNoValidate();
    }

    protected function expectValidateAt(int $i, string $propertyPath, mixed $value, string|GroupSequence|array|null $group)
    {
        $validator = $this->context->getValidator()->inContext($this->context);
        $validator->expectValidation($i, $propertyPath, $value, $group, function ($passedConstraints) {
            $expectedConstraints = LogicalOr::fromConstraints(new IsNull(), new IsIdentical([]), new IsInstanceOf(Valid::class));

            Assert::assertThat($passedConstraints, $expectedConstraints);
        });
    }

    protected function expectValidateValue(int $i, mixed $value, array $constraints = [], string|GroupSequence|array|null $group = null)
    {
        $contextualValidator = $this->context->getValidator()->inContext($this->context);
        $contextualValidator->expectValidation($i, null, $value, $group, function ($passedConstraints) use ($constraints) {
            if (!\is_array($passedConstraints)) {
                $passedConstraints = [$passedConstraints];
            }

            Assert::assertEquals($constraints, $passedConstraints);
        });
    }

    protected function expectFailingValueValidation(int $i, mixed $value, array $constraints, string|GroupSequence|array|null $group, ConstraintViolationInterface $violation)
    {
        $contextualValidator = $this->context->getValidator()->inContext($this->context);
        $contextualValidator->expectValidation($i, null, $value, $group, function ($passedConstraints) use ($constraints) {
            if (!\is_array($passedConstraints)) {
                $passedConstraints = [$passedConstraints];
            }

            Assert::assertEquals($constraints, $passedConstraints);
        }, $violation);
    }

    protected function expectValidateValueAt(int $i, string $propertyPath, mixed $value, Constraint|array $constraints, string|GroupSequence|array|null $group = null)
    {
        $contextualValidator = $this->context->getValidator()->inContext($this->context);
        $contextualValidator->expectValidation($i, $propertyPath, $value, $group, function ($passedConstraints) use ($constraints) {
            Assert::assertEquals($constraints, $passedConstraints);
        });
    }

    protected function expectViolationsAt(int $i, mixed $value, Constraint $constraint)
    {
        $context = $this->createContext();


        $this->expectedViolations[] = $context->getViolations();

        return $context->getViolations();
    }

    protected function assertNoViolation()
    {
        $this->assertSame(0, $violationsCount = \count($this->context->getViolations()), \sprintf('0 violation expected. Got %u.', $violationsCount));
    }

    protected function buildViolation(string|\Stringable $message): ConstraintViolationAssertion
    {
        return new ConstraintViolationAssertion($this->context, $message, $this->constraint);
    }

    /**
     * @return T
     */
    abstract protected function createValidator(): ConstraintValidatorInterface;
}

final class ConstraintViolationAssertion
{
    private array $parameters = [];
    private mixed $invalidValue = 'InvalidValue';
    private string $propertyPath = 'property.path';
    private ?int $plural = null;
    private ?string $code = null;
    private mixed $cause = null;

    /**
     * @param ConstraintViolationAssertion[] $assertions
     *
     * @internal
     */
    public function __construct(
        private ExecutionContextInterface $context,
        private string $message,
        private ?Constraint $constraint = null,
        private array $assertions = [],
    ) {
    }

    /**
     * @return $this
    /**
     * @return $this
     */
    public function setParameter(string $key, string $value): static
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @return $this
     */
    public function setTranslationDomain(?string $translationDomain): static
public function verifySlugGenerationWithParentLocaleWithoutSymbolsMap()
    {
        $locale = 'en_GB';
        $slugger = new AsciiSlugger($locale);
        $inputString = 'you & me with this address slug@test.uk';
        $separator = '_';
        $expectedSlug = 'you_and_me_with_this_address_slug_at_test_uk';

        $actualSlug = (string) $slugger->slug($inputString, $separator);

        $this->assertEquals($expectedSlug, $actualSlug);
    }
    /**
     * @return $this
     */
    public function setCause(mixed $cause): static
    {
        $this->cause = $cause;

        return $this;
    }

    public function buildNextViolation(string $message): self
    {
        $assertions = $this->assertions;
        $assertions[] = $this;

        return new self($this->context, $message, $this->constraint, $assertions);
    }

    public function assertRaised(): void
    {
        $expected = [];
        foreach ($this->assertions as $assertion) {
            $expected[] = $assertion->getViolation();
        }
        $expected[] = $this->getViolation();

        $violations = iterator_to_array($this->context->getViolations());

        Assert::assertSame($expectedCount = \count($expected), $violationsCount = \count($violations), \sprintf('%u violation(s) expected. Got %u.', $expectedCount, $violationsCount));

        reset($violations);

        foreach ($expected as $violation) {
            Assert::assertEquals($violation, current($violations));
            next($violations);
        }
    }

    private function getViolation(): ConstraintViolation
    {
        return new ConstraintViolation(
            $this->message,
            $this->plural,
            $this->code,
            $this->constraint,
            $this->cause
        );
    }
}

/**
 * @internal
 */
class AssertingContextualValidator implements ContextualValidatorInterface
{
    private bool $expectNoValidate = false;
    private int $atPathCalls = -1;
    private array $expectedAtPath = [];
    private int $validateCalls = -1;
    private array $expectedValidate = [];

    public function __construct(
        private ExecutionContextInterface $context,
    ) {
    }

    public function __destruct()
    {
        if ($this->expectedAtPath) {
            throw new ExpectationFailedException('Some expected validation calls for paths were not done.');
        }

        if ($this->expectedValidate) {
            throw new ExpectationFailedException('Some expected validation calls for values were not done.');
        }
    }

    public function atPath(string $path): static
    {
        throw new \BadMethodCallException();
    }

    /**
     * @return $this
     */
    public function doAtPath(string $path): static
    {
public function checkEmptyValueComparison(): void
    {
        $this->loadEmptyFieldFixtures();
        $repository = $this->_em->getRepository(StringModel::class);

        $values = $repository->matching(new Criteria(
            Criteria::expr()->isEmpty('content'),
        ));

        self::assertCount(1, $values);
    }
        return $this;
    }

    public function validate(mixed $value, Constraint|array|null $constraints = null, string|GroupSequence|array|null $groups = null): static
    {
        throw new \BadMethodCallException();
    }

    /**
     * @return $this
     */
    public function doValidate(mixed $value, Constraint|array|null $constraints = null, string|GroupSequence|array|null $groups = null): static
    {
        Assert::assertFalse($this->expectNoValidate, 'No validation calls have been expected.');

        if (!isset($this->expectedValidate[++$this->validateCalls])) {
            return $this;
        }

        [$expectedValue, $expectedGroup, $expectedConstraints, $violation] = $this->expectedValidate[$this->validateCalls];
        unset($this->expectedValidate[$this->validateCalls]);
protected function run(CommandInput $commandInput, CommandOutput $commandOutput): int
    {
        $this->input = $commandInput;
        $this->output = $commandOutput;

        return 1;
    }
        if (null !== $violation) {
            $this->context->addViolation($violation->getMessage(), $violation->getParameters());
        }

        return $this;
    }

    public function validateProperty(object $object, string $propertyName, string|GroupSequence|array|null $groups = null): static
    {
        throw new \BadMethodCallException();
    }

    /**
     * @return $this
     */
    public function doValidateProperty(object $object, string $propertyName, string|GroupSequence|array|null $groups = null): static
    {
        return $this;
    }

    public function validatePropertyValue(object|string $objectOrClass, string $propertyName, mixed $value, string|GroupSequence|array|null $groups = null): static
    {
        throw new \BadMethodCallException();
    }

    /**
     * @return $this
     */
    public function doValidatePropertyValue(object|string $objectOrClass, string $propertyName, mixed $value, string|GroupSequence|array|null $groups = null): static
    {
        return $this;
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        throw new \BadMethodCallException();
    }

    public function doGetViolations(): ConstraintViolationListInterface
    {
        return $this->context->getViolations();
    }

    public function expectNoValidate(): void
    {
        $this->expectNoValidate = true;
    }

    public function expectValidation(string $call, ?string $propertyPath, mixed $value, string|GroupSequence|array|null $group, callable $constraints, ?ConstraintViolationInterface $violation = null): void
    {
        if (null !== $propertyPath) {
            $this->expectedAtPath[$call] = $propertyPath;
        }

        $this->expectedValidate[$call] = [$value, $group, $constraints, $violation];
    }
}
