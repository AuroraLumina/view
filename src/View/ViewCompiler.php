<?php

namespace AuroraLumina\View;

use AuroraLumina\View\Utils\FileUtils;
use AuroraLumina\View\Parsers\TemplateParser;
use AuroraLumina\View\Processors\TemplateProcessor;

/**
 * Class ViewCompiler
 * 
 * Compiles AuroraLumina templates into PHP code.
 */
class ViewCompiler
{
    /**
     * @var TemplateProcessor
     */
    private TemplateProcessor $processor;

    /**
     * @var TemplateParser
     */
    private TemplateParser $parser;

    /**
     * Constructor.
     * 
     * @param TemplateProcessor $processor The template processor instance.
     */
    public function __construct(TemplateProcessor $processor)
    {
        $this->parser 		= new TemplateParser($processor);
        $this->processor 	= $processor;
    }

    /**
     * Compiles the template.
     *
     * @param string $filename The name of the template file.
     * @param string $outputFilename The name of the output file.
     * @param callable $findPath A callback function to find the path of included files.
     * @param bool $nocache Flag indicating whether to use nocache.
     * @return int Status code indicating success or failure of compilation.
     */
    public function compile(string $filename, string $outputFilename, callable $findPath, bool $nocache): int
    {
        $contents = FileUtils::loadContents($filename);

        if ($contents !== false && $contents !== "") {
            $contents = $this->processTemplate($filename, $contents, $findPath, $nocache);
            if ($this->writeOutputFile($outputFilename, $contents))
			{
            	return 1;
			}
        }

        return 0;
    }

    /**
     * Processes the template contents.
     *
     * @param string $contents The template contents.
     * @param callable $findPath A callback function to find the path of included files.
     * @param bool $nocache Flag indicating whether to use nocache.
     * @return string The processed template contents.
     */
    private function processTemplate(string $filename, string $contents, callable $findPath, bool $nocache): string
    {
        $contents = $this->processor->processIncludes($contents, $findPath);
        $contents = $this->processor->processLoads($contents);
        $contents = $this->parser->replaceNocacheTag($contents, $nocache);
        $contents = $this->parser->stripComments($contents);
        $contents = $this->parser->parseConstants($contents);
        $contents = $this->parser->parseFunctions($contents, $filename);
        $contents = $this->parser->parseExpressions($contents);
        $contents = $this->parser->parseVariables($contents);

        if ($this->processor->globalVariables() > 0) {
            $contents = $this->processor->declareGlobalVariables($contents);
        }

        $contents = $this->parser->cleanupTemplate($contents);

        return $contents;
    }

    /**
     * Writes the output file.
     *
     * @param string $outputFilename The name of the output file.
     * @param string $contents The contents to write to the file.
     */
    private function writeOutputFile(string $outputFilename, string $contents): bool
    {
        FileUtils::createOutputDirectory($outputFilename);
        return FileUtils::writeToFile($outputFilename, $contents);
    }
}