<?php

namespace spec\PhpGuard\Application\Event;

use PhpGuard\Listen\Event\ChangeSetEvent;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class EvaluateEventSpec extends ObjectBehavior
{
    function let(ChangeSetEvent $event)
    {
        $this->beConstructedWith($event);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpGuard\Application\Event\EvaluateEvent');
    }

    function its_delegate_changeset_event(ChangeSetEvent $event)
    {
        $this->getChangeset()->shouldReturn($event);

        $event->getEvents()
            ->shouldBeCalled();
        $this->getEvents();

        $event->getFiles()
            ->shouldBeCalled();
        $this->getFiles();

        $event->getListener()
            ->shouldBeCalled();
        $this->getListener();
    }
}