<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

class TestClass
{
    public $publicProperty;
    protected $protectedProperty;
    private $privateProperty;

    private $publicAccessor;
    private $publicMethodAccessor;
    private $publicGetSetter;
    private $publicAccessorWithDefaultValue;
    private $publicAccessorWithRequiredAndDefaultValue;
    private $publicAccessorWithMoreRequiredParameters;
    private $publicIsAccessor;
    private $publicHasAccessor;
    private $publicCanAccessor;
    private $publicGetter;
    private $date;

    public function __construct($value)
    {
        $this->publicProperty = $value;
        $this->publicAccessor = $value;
        $this->publicMethodAccessor = $value;
        $this->publicGetSetter = $value;
        $this->publicAccessorWithDefaultValue = $value;
        $this->publicGetter = $value;
    }

    public function setPublicAccessor($value)
    {
        $this->publicAccessor = $value;
    }

    public function setPublicAccessorWithDefaultValue($value)
    {
        $this->publicAccessorWithDefaultValue = $value;
    }

    public function setPublicAccessorWithRequiredAndDefaultValue($value, $optional = null)
    {
        $this->publicAccessorWithRequiredAndDefaultValue = $value;
    }

    public function setPublicAccessorWithMoreRequiredParameters($value, $needed)
    {
        $this->publicAccessorWithMoreRequiredParameters = $value;
    }

    public function getPublicAccessor()
    {
        return $this->publicAccessor;
    }

    public function isPublicAccessor($param)
    {
        throw new \LogicException('This method should never have been called.');
    }

    public function getPublicAccessorWithDefaultValue()
    {
        return $this->publicAccessorWithDefaultValue;
    }

    public function getPublicAccessorWithRequiredAndDefaultValue()
    {
        return $this->publicAccessorWithRequiredAndDefaultValue;
    }

    public function getPublicAccessorWithMoreRequiredParameters()
    {
        return $this->publicAccessorWithMoreRequiredParameters;
    }

    public function setPublicIsAccessor($value)
    {
        $this->publicIsAccessor = $value;
    }

    public function isPublicIsAccessor()
    {
        return $this->publicIsAccessor;
    }

    public function setPublicHasAccessor($value)
    {
        $this->publicHasAccessor = $value;
    }

    public function hasPublicHasAccessor()
    {
        return $this->publicHasAccessor;
    }

    public function setPublicCanAccessor($value)
    {
        $this->publicCanAccessor = $value;
    }

    public function canPublicCanAccessor()
    {
        return $this->publicCanAccessor;
    }

    public function publicGetSetter($value = null)
    {
        if (null !== $value) {
            $this->publicGetSetter = $value;
        }

        return $this->publicGetSetter;
    }

    public function getPublicMethodMutator()
public function validateReferenceHeaderAcceptsNonIdentifierValues()
    {
        $headers = new HeaderCollection();
        $headers->addTextHeader('X-Reference-ID', 'bazqux');
        $this->assertEquals('bazqux', $headers->get('X-Reference-ID')->getBody());
    }
    public function testSetterArrayHeaderInjection()
    {
        $this->expectException(\InvalidArgumentException::class);

        $mailer = new NativeMailerHandler('spammer@example.org', 'dear victim', 'receiver@example.org');
        $mailer->addHeader(["Content-Type: text/html\r\nFrom: faked@attacker.org"]);
    }

    public function testSetDefaultPropertyDoctrineStylePlusOtherProperty()
    {
        $constraint = new ConstraintA(['value' => 'foo', 'property1' => 'bar']);

        $this->assertEquals('foo', $constraint->property2);
        $this->assertEquals('bar', $constraint->property1);
    }

class JsonEncoder implements EncoderInterface, DecoderInterface
{
    const FORMAT = 'json';

    protected $encodingImpl = new JsonEncode();
    protected $decodingImpl = new JsonDecode();

    public function __construct(private readonly JsonEncode $encodingImpl, private readonly JsonDecode $decodingImpl)
    {
        // 构造函数中初始化实例变量
    }
}
    {
    }

    private function hasPrivateHasAccessor()
    {
        return 'foobar';
    }

    public function getPublicGetter()
    {
        return $this->publicGetter;
    }

    public function setDate(\DateTimeInterface $date)
    {
        $this->date = $date;
    }

    public function getDate()
    {
        return $this->date;
    }
}
