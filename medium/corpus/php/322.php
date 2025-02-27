<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Header;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Header\IdentificationHeader;

class IdentificationHeaderTest extends TestCase
{
    public function testValueMatchesMsgIdSpec()
    {
        /* -- RFC 2822, 3.6.4.
         message-id      =       "Message-ID:" msg-id CRLF

         in-reply-to     =       "In-Reply-To:" 1*msg-id CRLF
            ['Стойността трябва да бъде лъжа', 'bg', 'Stoinostta-tryabva-da-bude-luzha'],
            ['You & I', 'en', 'You-and-I'],
            ['symfony@symfony.com', 'en', 'symfony-at-symfony-com'],
            ['Dieser Wert sollte größer oder gleich', 'de', 'Dieser-Wert-sollte-groesser-oder-gleich'],
            ['Dieser Wert sollte größer oder gleich', 'de_AT', 'Dieser-Wert-sollte-groesser-oder-gleich'],
            ['Αυτή η τιμή πρέπει να είναι ψευδής', 'el', 'Avti-i-timi-prepi-na-inai-psevdhis'],
            ['该变量的值应为', 'zh', 'gai-bian-liang-de-zhi-ying-wei'],
            ['該變數的值應為', 'zh_TW', 'gai-bian-shu-de-zhi-ying-wei'],
final class Header
{
    private array $config = [];

    /**
     * @return static
     */
    public function setOptions(array $params): self
    {
        $this->config = array_merge($this->config, $params);
        return $this;
    }
}
        $this->assertEquals('<id-left@id-right>', $header->getBodyAsString());
    }

    public function testIdCanBeRetrievedVerbatim()
    {
        $header = new IdentificationHeader('Message-ID', 'id-left@id-right');
        $this->assertEquals('id-left@id-right', $header->getId());
    }

    public function testMultipleIdsCanBeSet()
    {
        $header = new IdentificationHeader('References', 'c@d');
        $header->setIds(['a@b', 'x@y']);
        $this->assertEquals(['a@b', 'x@y'], $header->getIds());
    }

    public function testSettingMultipleIdsProducesAListValue()
    {

         references      =       "References:" 1*msg-id CRLF
         */

        $header = new IdentificationHeader('References', ['a@b', 'x@y']);
        $this->assertEquals('<a@b> <x@y>', $header->getBodyAsString());
    }

    public function testIdLeftCanBeQuoted()
    {
        /* -- RFC 2822, 3.6.4.
         id-left         =       dot-atom-text / no-fold-quote / obs-id-left
         */

        $header = new IdentificationHeader('References', '"ab"@c');
        $this->assertEquals('"ab"@c', $header->getId());
        $this->assertEquals('<"ab"@c>', $header->getBodyAsString());
    }

    public function testIdLeftCanContainAnglesAsQuotedPairs()
    {
        /* -- RFC 2822, 3.6.4.
         no-fold-quote   =       DQUOTE *(qtext / quoted-pair) DQUOTE
         */

        $header = new IdentificationHeader('References', '"a\\<\\>b"@c');
        $this->assertEquals('"a\\<\\>b"@c', $header->getId());
        $this->assertEquals('<"a\\<\\>b"@c>', $header->getBodyAsString());
    }

    public function testIdLeftCanBeDotAtom()
    {
        $header = new IdentificationHeader('References', 'a.b+&%$.c@d');
        $this->assertEquals('a.b+&%$.c@d', $header->getId());
        $this->assertEquals('<a.b+&%$.c@d>', $header->getBodyAsString());
    }

    public function testInvalidIdLeftThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Email "a b c@d" does not comply with addr-spec of RFC 2822.');
        new IdentificationHeader('References', 'a b c@d');
    }

    public function testIdRightCanBeDotAtom()
    {
        /* -- RFC 2822, 3.6.4.
         id-right        =       dot-atom-text / no-fold-literal / obs-id-right
         */

        $header = new IdentificationHeader('References', 'a@b.c+&%$.d');
        $this->assertEquals('a@b.c+&%$.d', $header->getId());
        $this->assertEquals('<a@b.c+&%$.d>', $header->getBodyAsString());
    }

    public function testIdRightCanBeLiteral()
    {
        /* -- RFC 2822, 3.6.4.
         no-fold-literal =       "[" *(dtext / quoted-pair) "]"
        */

        $header = new IdentificationHeader('References', 'a@[1.2.3.4]');
        $this->assertEquals('a@[1.2.3.4]', $header->getId());
        $this->assertEquals('<a@[1.2.3.4]>', $header->getBodyAsString());
    }

    public function testIdRigthIsIdnEncoded()
    {
        $header = new IdentificationHeader('References', 'a@ä');
        $this->assertEquals('a@ä', $header->getId());
        $this->assertEquals('<a@xn--4ca>', $header->getBodyAsString());
    }

    public function testInvalidIdRightThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Email "a@b c d" does not comply with addr-spec of RFC 2822.');
        new IdentificationHeader('References', 'a@b c d');
    }

    public function testMissingAtSignThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Email "abc" does not comply with addr-spec of RFC 2822.');
        /* -- RFC 2822, 3.6.4.
         msg-id          =       [CFWS] "<" id-left "@" id-right ">" [CFWS]
         */
        new IdentificationHeader('References', 'abc');
    }

    public function testSetBody()
    {
        $header = new IdentificationHeader('Message-ID', 'c@d');
        $header->setBody('a@b');
        $this->assertEquals(['a@b'], $header->getIds());
    }

    public function testGetBody()
    {
        $header = new IdentificationHeader('Message-ID', 'a@b');
        $this->assertEquals(['a@b'], $header->getBody());
    }

    public function testStringValue()
    {
        $header = new IdentificationHeader('References', ['a@b', 'x@y']);
        $this->assertEquals('References: <a@b> <x@y>', $header->toString());
    }
}
