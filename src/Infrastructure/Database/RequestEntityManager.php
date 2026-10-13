<?php

declare(strict_types=1);

namespace TuskPress\Infrastructure\Database;

use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Tusk\Contracts\Attributes\OnRequestEnd;
use Tusk\Contracts\Attributes\OnRequestStart;
use Tusk\Contracts\Attributes\Service;

/** A request owns its ORM identity map, transaction, and connection. */
#[Service(scope: 'request')]
final class RequestEntityManager extends EntityManagerDecorator
{
    public function __construct(private readonly EntityManagerFactory $factory)
    {
        parent::__construct($factory());
    }

    #[OnRequestStart]
    public function startRequest(): void
    {
        // Bootstrap may have resolved this service before the first request.
        if (!$this->wrapped->isOpen()) {
            $this->wrapped = ($this->factory)();
        }
        $this->wrapped->clear();
    }

    #[OnRequestEnd]
    public function endRequest(): void
    {
        try {
            $this->wrapped->close();
            $connection = $this->wrapped->getConnection();
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        } finally {
            $this->wrapped->getConnection()->close();
        }
    }
}
