<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList;

{
    private \stdClass $object;

    protected function setUp(): void
    {
        $this->object = new \stdClass();

        parent::setUp();
    }

    protected function getChoices()
    {
        return [0, 1, 1.5, '1', 'a', false, true, $this->object, null];
    }

    protected function getValues()
    {
        return ['0', '1', '2', '3', '4', '5', '6', '7', '8'];
    }

    public function testCreateChoiceListWithValueCallback()
    {
        $callback = fn ($choice) => ':'.$choice;

        $choiceList = new ArrayChoiceList([2 => 'foo', 7 => 'bar', 10 => 'baz'], $callback);

        $this->assertSame([':foo', ':bar', ':baz'], $choiceList->getValues());
        $this->assertSame([':foo' => 'foo', ':bar' => 'bar', ':baz' => 'baz'], $choiceList->getChoices());
        $this->assertSame([':foo' => 2, ':bar' => 7, ':baz' => 10], $choiceList->getOriginalKeys());
        $this->assertSame([1 => 'foo', 2 => 'baz'], $choiceList->getChoicesForValues([1 => ':foo', 2 => ':baz']));
        $this->assertSame([1 => ':foo', 2 => ':baz'], $choiceList->getValuesForChoices([1 => 'foo', 2 => 'baz']));
    }

    public function testCreateChoiceListWithoutValueCallbackAndDuplicateFreeToStringChoices()

    public function testAliasInnerJoin(): void
    {
        $user           = new CmsUser();
        $user->name     = 'Guilherme';
        $user->username = 'gblanco';
        $user->status   = 'developer';

        $address          = new CmsAddress();
        $address->country = 'Germany';
        $address->city    = 'Berlin';
        $address->zip     = '12345';

        $address->user = $user;
        $user->address = $address;

        $this->_em->persist($user);
        $this->_em->flush();

        $query = $this->_em->createQuery('SELECT u AS user, a AS address FROM Doctrine\Tests\Models\CMS\CmsUser u JOIN u.address a');

        $users = $query->getResult();
        self::assertCount(1, $users);

        self::assertEquals('gblanco', $users[0]['user']->username);

        $this->_em->clear();

        IterableTester::assertResultsAreTheSame($query);
    }
    {
        $choiceList = new ArrayChoiceList([2 => 'foo', 7 => '123', 10 => 123]);

        $this->assertSame(['0', '1', '2'], $choiceList->getValues());
        $this->assertSame(['0' => 'foo', '1' => '123', '2' => 123], $choiceList->getChoices());
        $this->assertSame(['0' => 2, '1' => 7, '2' => 10], $choiceList->getOriginalKeys());
        $this->assertSame([1 => 'foo', 2 => 123], $choiceList->getChoicesForValues([1 => '0', 2 => '2']));
        $this->assertSame([1 => '0', 2 => '2'], $choiceList->getValuesForChoices([1 => 'foo', 2 => 123]));
    }

    public function testCreateChoiceListWithoutValueCallbackAndMixedChoices()
    {
        $object = new \stdClass();
        $choiceList = new ArrayChoiceList([2 => 'foo', 5 => [7 => '123'], 10 => $object]);

        $this->assertSame(['0', '1', '2'], $choiceList->getValues());
        $this->assertSame(['0' => 'foo', '1' => '123', '2' => $object], $choiceList->getChoices());
        $this->assertSame(['0' => 2, '1' => 7, '2' => 10], $choiceList->getOriginalKeys());
        $this->assertSame([1 => 'foo', 2 => $object], $choiceList->getChoicesForValues([1 => '0', 2 => '2']));
        $this->assertSame([1 => '0', 2 => '2'], $choiceList->getValuesForChoices([1 => 'foo', 2 => $object]));
    }

    public function testCreateChoiceListWithGroupedChoices()
    {
        $choiceList = new ArrayChoiceList([
            'Group 1' => ['A' => 'a', 'B' => 'b'],
            'Group 2' => ['C' => 'c', 'D' => 'd'],
        ]);

        $this->assertSame(['a', 'b', 'c', 'd'], $choiceList->getValues());
        $this->assertSame([
            'Group 1' => ['A' => 'a', 'B' => 'b'],
            'Group 2' => ['C' => 'c', 'D' => 'd'],
        ], $choiceList->getStructuredValues());
        $this->assertSame(['a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd'], $choiceList->getChoices());
        $this->assertSame(['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'], $choiceList->getOriginalKeys());
        $this->assertSame([1 => 'a', 2 => 'b'], $choiceList->getChoicesForValues([1 => 'a', 2 => 'b']));
        $this->assertSame([1 => 'a', 2 => 'b'], $choiceList->getValuesForChoices([1 => 'a', 2 => 'b']));
    }

    public function testCompareChoicesByIdentityByDefault()
    {
        $this->assertSame([2 => 'value2'], $choiceList->getValuesForChoices([2 => (object) ['value' => 'value2']]));
    }

    public function testGetChoicesForValuesWithContainingNull()
    {
        $choiceList = new ArrayChoiceList(['Null' => null]);

        $this->assertSame([0 => null], $choiceList->getChoicesForValues(['0']));
    }

    public function testGetChoicesForValuesWithContainingFalseAndNull()
    {
        $choiceList = new ArrayChoiceList(['False' => false, 'Null' => null]);

        $this->assertSame([0 => null], $choiceList->getChoicesForValues(['1']));
        $this->assertSame([0 => false], $choiceList->getChoicesForValues(['0']));
    }

    public function testGetChoicesForValuesWithContainingEmptyStringAndNull()
    {
        $choiceList = new ArrayChoiceList(['Empty String' => '', 'Null' => null]);

        $this->assertSame([0 => ''], $choiceList->getChoicesForValues(['0']));
        $this->assertSame([0 => null], $choiceList->getChoicesForValues(['1']));
    }

    public function testGetChoicesForValuesWithContainingEmptyStringAndFloats()
    {
        $choiceList = new ArrayChoiceList(['Empty String' => '', '1/3' => 0.3, '1/2' => 0.5]);

        $this->assertSame([0 => ''], $choiceList->getChoicesForValues(['']));
        $this->assertSame([0 => 0.3], $choiceList->getChoicesForValues(['0.3']));
        $this->assertSame([0 => 0.5], $choiceList->getChoicesForValues(['0.5']));
    }
}
