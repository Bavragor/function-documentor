<?php

namespace Bavragor\FunctionDocumentor\Export;

class ArrayExport implements FunctionExportInterface
{
    /**
     * Export given functions by implementation can either return SplFileInfo, string or an array.
     *
     * @param array $functions
     *
     * @return \SplFileInfo|string|array
     */
    public function export($functions)
    {
        return $functions;
    }
}
