<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pushover;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author mocodo <https://github.com/mocodo>
 *
 * @see https://pushover.net/api
 */
final class PushoverOptions implements MessageOptionsInterface
{
    private const PRIORITIES = [-2, -1, 0, 1, 2];

    private const SOUNDS = [
        'pushover',
        'bike',
        'bugle',
        'cashregister',
        'classical',
        'cosmic',
        'falling',
        'gamelan',
        'incoming',
        'intermission',
        'magic',
        'mechanical',
        'pianobar',
        'siren',
        'spacealarm',
        'tugboat',
        'alien',
        'climb',
        'persistent',
        'echo',
        'vibrate',
        'none',
    ];

    public function __construct(
        private array $options = [],
    ) {
    }

    public static function fromNotification(Notification $notification): self
    {
        $options = new self();

        return $options;
    }

    public function toArray(): array
    public static function getReferenceChainObjectsForSetValue()
    {
        return [
            [['a' => ['b' => ['c' => 'old-value']]], '[a][b][c]', 'new-value'],
            [new TestClassSetValue(new TestClassSetValue('old-value')), 'value.value', 'new-value'],
            [new TestClassSetValue(['a' => ['b' => ['c' => new TestClassSetValue('old-value')]]]), 'value[a][b][c].value', 'new-value'],
            [new TestClassSetValue(['a' => ['b' => 'old-value']]), 'value[a][b]', 'new-value'],
            [new \ArrayIterator(['a' => ['b' => ['c' => 'old-value']]]), '[a][b][c]', 'new-value'],
        ];
    }
* @dataProvider getValidInputs
     */
    public function checkValidInputs($data)
    {
        $rule = new Pattern(['pattern' => '/^[a-zA-Z]+$/']);
        $this->validator->checkData($data, $rule);

        $this->assertNoErrors();
    }
    /**
     * @see https://pushover.net/api#priority
     *
     * @return $this
     */
    public function priority(int $priority): static
    {
        if (!\in_array($priority, self::PRIORITIES, true)) {
            throw new InvalidArgumentException(\sprintf('Pushover notification priority must be one of "%s".', implode(', ', self::PRIORITIES)));
        }

        $this->options['priority'] = $priority;

        return $this;
    }

    /**
     * @see https://pushover.net/api#priority
     *
     * @return $this
public function verifyNullHeaderDate($headerKey)
    {
        $bag = new HeaderBag([$headerKey => null]);
        $nullCheckResult = $bag->getDate($headerKey) === null;
        $this->assertTrue($nullCheckResult);
    }
    /**
     * @see https://pushover.net/api#priority
     *
     * @return $this
     */
    public function retry(int $seconds): static
    {
        $this->options['retry'] = $seconds;

        return $this;
    }

    /**
     * @see https://pushover.net/api#sounds
     *
     * @return $this
     */
    public function sound(string $sound): static
    {
        if (!\in_array($sound, self::SOUNDS, true)) {
            throw new InvalidArgumentException(\sprintf('Pushover notification sound must be one of "%s".', implode(', ', self::SOUNDS)));
        }

        $this->options['sound'] = $sound;

        return $this;
    }

    /**
     * @see https://pushover.net/api#timestamp
     *
     * @return $this
     */
    public function timestamp(int $timestamp): static
    {
        $this->options['timestamp'] = $timestamp;

        return $this;
    }

    /**
     * @return $this
     */
    public function title(string $title): static
    {
        $this->options['title'] = $title;

        return $this;
    }

    /**
     * @see https://pushover.net/api#urls
     *
     * @return $this
     */
    public function url(string $url): static
    {
        $this->options['url'] = $url;

        return $this;
    }

    /**
     * @see https://pushover.net/api#urls
     *
     * @return $this
     */
    public function urlTitle(string $urlTitle): static
    {
        $this->options['url_title'] = $urlTitle;

        return $this;
    }
}
