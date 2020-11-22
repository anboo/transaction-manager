<?php

namespace Anboo\TransactionManager;

use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

class TransactionManager
{
    /** @var TransactionInterface[] */
    private array $transactions = [];

    private ?LoggerInterface $logger = null;

    /** @var string[] */
    private array $ignoreExceptions = [];

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return TransactionInterface[]
     */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function createEmpty(): self
    {
        $manager = clone $this;

        $manager->transactions = [];
        $manager->ignoreExceptions = [];

        return $manager;
    }

    public function merge(TransactionManager $transactionManager): self
    {
        foreach ($transactionManager->getTransactions() as $transaction) {
            $this->addTransaction($transaction);
        }

        foreach ($transactionManager->ignoreExceptions as $ignoreException) {
            $this->ignoreExceptions[] = $ignoreException;
        }

        return $this;
    }

    public function addTransaction($transaction): self
    {
        if ($transaction instanceof TransactionInterface) {
            $this->transactions[spl_object_hash($transaction)] = $transaction;
        } elseif (is_callable($transaction)) {
            $this->transactions[] = new CallbackTransactionInterface($transaction);
        } else {
            throw new \RuntimeException('Expected type Transaction or callable, got '.gettype($transaction));
        }

        return $this;
    }

    public function addIgnoreException($exception): self
    {
        $this->ignoreExceptions[] = $exception;

        return $this;
    }

    public function run()
    {
        /** @var TransactionInterface[] $completedTransactions */
        $completedTransactions = [];
        /** @var StopwatchEvent[] $stopwatchEvents */
        $stopwatchEvents = [];

        $stopwatch = new Stopwatch();

        foreach ($this->transactions as $i => $transaction) {
            $stopwatchEventCode = basename(str_replace('\\', '/', get_class($transaction))).'#'.$i;
            $stopwatch->start($stopwatchEventCode);

            try {
                $transaction->up();
                $completedTransactions[] = $transaction;
            } catch (\Exception $exception) {
                $isIgnoreException = false;

                foreach ($this->ignoreExceptions as $ignoreException) {
                    if (get_class($exception) == $ignoreException) {
                        $isIgnoreException = true;
                    }
                }

                if (!$isIgnoreException) {
                    foreach ($completedTransactions as $completedTransaction) {
                        try {
                            if ($this->logger) {
                                $this->logger->info('Start rollback transaction '.get_class($completedTransaction));
                            }
                            $completedTransaction->down();
                        } catch (\Exception $exceptionDown) {
                            if ($this->logger) {
                                $this->logger->error(sprintf(
                                    'Rollback transaction %s error %s',
                                    get_class($completedTransaction),
                                    $exceptionDown->getMessage()
                                ), ['exception' => $exceptionDown]);
                            }
                        }
                    }

                    $this->transactions = [];

                    throw $exception;
                } else {
                    if ($this->logger) {
                        $this->logger->debug('Ignore exception '.get_class($exception));
                    }
                }
            }

            $stopwatchEvents[$stopwatchEventCode] = $stopwatch->stop($stopwatchEventCode);
        }

        $duration = array_sum(array_map(fn(StopwatchEvent $e) => $e->getDuration(), $stopwatchEvents)) / 1000;
        if ($duration > 2) {
            $debug = implode(
                PHP_EOL,
                array_map(
                    fn(string $eventName, StopwatchEvent $e) => $eventName.':'.$e,
                    array_keys($stopwatchEvents),
                    $stopwatchEvents
                )
            );

            if ($this->logger) {
                $this->logger->warning('Duration transactions '.$duration.' '.$debug);
            }
        }

        $this->transactions = [];
    }
}
