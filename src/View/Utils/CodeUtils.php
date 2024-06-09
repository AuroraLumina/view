<?php

namespace AuroraLumina\View\Utils;

/**
 * Class CodeUtils
 * 
 * Utility class for working with PHP code.
 */
class CodeUtils
{
    /**
     * The opening PHP tag.
     *
     * @var string
     */
    public const TAG_OPEN = "<?php";

    /**
     * The closing PHP tag.
     *
     * @var string
     */
    public const TAG_CLOSE = "?>";

	/**
     * Wraps code in PHP tags.
     *
     * @param string $code The code to wrap.
     * @return string The code wrapped in PHP tags.
     */
	public static function code($code): string 
	{
		return self::TAG_OPEN . " " . $code . self::TAG_CLOSE;
	}

	/**
     * Prepares the code by replacing dots.
     *
     * @param string $expression The expression to prepare.
     * @return string The prepared code.
     */
	public static function prepareCode(string $exp): string
	{
		return str_replace(".", "__1", self::TAG_OPEN . " if (" . $exp . ") { " . self::TAG_CLOSE);
	}

	/**
     * Removes PHP tags from the template contents.
     *
     * This method removes PHP code blocks from the template contents to avoid conflicts with the parser.
     *
     * @param string $contents The contents of the template.
     * @return string The contents with PHP tags removed.
     */
	public static function removePhpTags(string $contents): string
	{
		return preg_replace("/\<\?php.+\?\>/sU", "", $contents);
	}
}