<?php

namespace Bavragor\FunctionDocumentor;

use Bavragor\FunctionDocumentor\Export\FunctionExportInterface;
use Bavragor\FunctionDocumentor\Log\BufferedLogger;
use Bavragor\FunctionDocumentor\Visitor\FunctionParsingVisitor;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Psr\Log\LoggerAwareTrait;

/**
 * Generates formatted documentation of usages of given functions by provided exporter
 * @author Kevin Mauel <kevin.mauel2+github@gmail.com>
 */
class FunctionDocumentor
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var FunctionExportInterface
     */
    protected $exporter;

    /**
     * @param FunctionExportInterface $exporter
     * @param string $directory
     */
    public function __construct(FunctionExportInterface $exporter, $directory)
    {
        $this->exporter = $exporter;
        $this->directory = $directory;
    }

    public function getLogMessages($preserveLog = false)
    {
        if ($this->logger instanceof BufferedLogger) {
            return $this->logger->getLogMessages($preserveLog);
        }

        return [];
    }

    /**
     * Retrieves occurrences of given function calls, formatted by given formatters and exported
     *
     * @param array $functionCalls
     * @param string[] $excludedDirectories
     * @param bool $sorting
     *
     * @return \SplFileInfo|array|string
     */
    public function retrieve($functionCalls, $excludedDirectories = [], $sorting = false)
    {
        $this->setLogger(new BufferedLogger());

        $parser        = (new ParserFactory)->create(ParserFactory::ONLY_PHP5);
        $traverser     = new NodeTraverser();

        $visitor = new FunctionParsingVisitor($functionCalls, []);
        $visitor->setLogger($this->logger);
        $traverser->addVisitor(new NameResolver()); // We need this to access default values from class constants
        $traverser->addVisitor($visitor);

        try {
            $it = new \RecursiveDirectoryIterator(realpath($this->directory));

            /**
             * @var $file \SplFileInfo
             */
            foreach (new \RecursiveIteratorIterator($it) as $file) {
                if (
                    $file->getExtension() === 'php' &&
                    str_replace($excludedDirectories, '', $file->getRealPath()) === $file->getRealPath()
                ) {
                    $visitor->setFilePath($file->getRealPath());
                    $traverser->traverse($parser->parse(file_get_contents($file->getRealPath())));
                }
            }

            if ($sorting) {
                ksort($visitor->functions);
            }

            return $this->exporter->export($visitor->functions);
        } catch (Error $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
