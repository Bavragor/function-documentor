<?php

namespace Bavragor\FunctionDocumentor\Formatter;

interface FormatterInterface
{
    /**
     * Formats argumentValue
     *
     * @param $class
     * @param $function
     * @param $argumentValue
     *
     * @return string
     */
    public function formatArgument($class, $function, $argumentValue);
}
