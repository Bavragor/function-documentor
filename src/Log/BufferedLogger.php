<?php

namespace Bavragor\FunctionDocumentor\Log;

use Psr\Log\AbstractLogger;

class BufferedLogger extends AbstractLogger
{
    /**
     * Log messages with line ending
     * @var string[]
     */
    private $logMessages = [];

    public function getLogMessages($preserveLog = false)
    {
        $logMessages = $this->logMessages;

        if (!$preserveLog) {
            unset($this->logMessages);
        }

        return $logMessages;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        $this->logMessages[strtoupper($level)][] = '[' . strtoupper($level) . '] ' . $message . PHP_EOL;
    }
}
