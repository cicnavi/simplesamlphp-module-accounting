<?php

declare(strict_types=1);

namespace SimpleSAML\Module\accounting\Entities\AuthenticationEvent;

use SimpleSAML\Module\accounting\Entities\AuthenticationEvent;
use SimpleSAML\Module\accounting\Entities\Bases\AbstractJob;
use SimpleSAML\Module\accounting\Entities\Bases\AbstractPayload;
use SimpleSAML\Module\accounting\Exceptions\UnexpectedValueException;

class Job extends AbstractJob
{
    public function getPayload(): AuthenticationEvent
    {
        return $this->validatePayload($this->payload);
    }

    public function setPayload(AbstractPayload $payload): void
    {
        $this->payload = $this->validatePayload($payload);
    }

    protected function validatePayload(AbstractPayload $payload): AuthenticationEvent
    {
        if (! ($payload instanceof AuthenticationEvent)) {
            throw new UnexpectedValueException('AuthenticationEvent Job payload must be of type AuthenticationEvent.');
        }

        return $payload;
    }

    public function getType(): string
    {
        return self::class;
    }
}
