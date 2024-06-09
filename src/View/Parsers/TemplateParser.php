<?php

namespace AuroraLumina\View\Parsers;

use AuroraLumina\View\Utils\CodeUtils;
use AuroraLumina\View\Processors\TemplateProcessor;

/**
 * Class TemplateParser
 * 
 * Parses templates and extracts literals and variables.
 */
class TemplateParser
{
    /**
     * An array to store literals extracted from the template.
     * 
     * @var array
     */
    private array $literals = [];

    /**
     * The template processor instance.
     * 
     * @var TemplateProcessor
     */
    private TemplateProcessor $processor;

    /**
     * Constructor.
     * 
     * @param TemplateProcessor $processor The template processor instance.
     */
    public function __construct(TemplateProcessor $processor)
    {
        $this->processor = $processor;
    }

	/**
     * Process strip comments in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	public function stripComments(string $contents): string
	{
		return preg_replace("/\{\*.+\*\}/sU", "", $contents);
	}

	/**
     * Replace {nocache} tag with appropriate code.
     *
     * @param string $contents The contents of the template.
     * @param bool $nocache Flag indicating whether to use nocache.
     * @return string The processed contents.
     */
	public function replaceNocacheTag(string $contents, bool $nocache): string
	{
		$nocache = $nocache ? CodeUtils::code("@unlink(__FILE__);") : "";
		return str_replace("{*nocache*}", $nocache, $contents);
	}

	/**
     * Replace constant definitions in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	public function parseConstants(string $contents): string
	{
		return preg_replace_callback(
			"/\{(\_[a-zA-Z0-9\_]+)\}/",
			function ($matches)
			{
				return CodeUtils::code("echo " . $matches[1] . ";");
			},
			$contents
		);
	}

	/**
     * Extract block and inline functions from the template.
     *
     * @param string $contents The contents of the template.
     * @return array An array containing extracted block and inline functions.
     */
	private function extractBlockAndInlineFunctions(string $contents): array
	{
		$inlines = $blocks = [];
		
		if (preg_match_all("/\{(block|inline)\ ([a-zA-Z0-9\_\-]+)\}(.*?)\{\/\\1\}/s", $contents, $matches)) {
			foreach ($matches[0] as $key => $match)
			{
				$content = trim($matches[3][$key]);
				
				$functionData = ["content" => $content, "src" => $match];
				
				if ($matches[1][$key] == "block")
				{
					$blocks[$matches[2][$key]] = $functionData;
				}
				else
				{
					$inlines[$matches[2][$key]] = $functionData;
				}
			}
		}

		return ['blocks' => $blocks, 'inlines' => $inlines];
	}

	/**
     * Replace blocks in the template with PHP functions.
     *
     * @param string $contents The contents of the template.
     * @param array $blocks An array containing block functions.
     * @param string $filename The name of the template file.
     * @return string The processed contents.
     */
	private function replaceBlocks(string $contents, array $blocks, string $filename): string
	{
		foreach ($blocks as $name => $code)
		{
			$lambda = sprintf("%u", crc32($code['content'])) . "_" . sprintf("%u", crc32($filename));
			$blockCode = "if (!function_exists('" . $name . "_" . $lambda . "')) { function " . $name . "_" . $lambda . "(\$_v) {" . CodeUtils::TAG_CLOSE . $code['content'] . CodeUtils::TAG_OPEN . " } }";
			$contents = str_replace($code['src'], CodeUtils::code($blockCode), $contents);
		}
		return $contents;
	}

	/**
     * Remove inline functions from the template.
     *
     * @param string $contents The contents of the template.
     * @param array $inlines An array containing inline functions.
     * @return string The processed contents.
     */
	private function removeInlines(string $contents, array $inlines): string
	{
		foreach ($inlines as $name => $code)
		{
			$contents = str_replace($code['src'], '', $contents);
		}
		return $contents;
	}

	/**
     * Replace inline functions in the template.
     *
     * @param string $contents The contents of the template.
     * @param array $inlines An array containing inline functions.
     * @return string The processed contents.
     */
	private function replaceInlineFunctions(string $contents, array $inlines): string
	{
		$matches = [];
		while (preg_match_all("/\{inline\:([a-zA-Z0-9\_\-]+)\}/s", $contents, $matches))
		{
			foreach ($matches[0] as $key => $match)
			{
				$contents = str_replace($match, $inlines[$matches[1][$key]]['content'], $contents);
			}
		}
		return $contents;
	}

	/**
     * Replace block tags in the template with PHP function calls.
     *
     * @param string $contents The contents of the template.
     * @param array $blocks An array containing block functions.
     * @param string $lambda The lambda value.
     * @return string The processed contents.
     */
	private function replaceBlockTags(string $contents, array $blocks, string $lambda): string
	{
		foreach ($blocks as $name => $code) {
			$contents = str_replace("{block:" . $name . "}", CodeUtils::code($name . "_" . $lambda . "(&\$_v);"), $contents);
		}
		return $contents;
	}

	/**
     * Process script tags in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	public function processScriptTags(string $contents): string
	{
		if (preg_match_all("/\<script\ ([^\>]+)\>(.*?)\<\/script\>/s", $contents, $matches))
		{
			foreach ($matches[0] as $k => $tag)
			{
				$parameters = $matches[1][$k];
				$scriptContent = $matches[2][$k];
				if (strpos($parameters, "text/template") !== false || strpos($parameters, "text/x-jquery") !== false)
				{
					$key = count($this->literals) . "_literal";
					$this->literals[$key] = $scriptContent;
					$contents = str_replace($matches[2][$k], "[[" . $key . "]]", $contents);
				}
			}
		}
		return $contents;
	}

	/**
	 * Parse functions in the template.
	 *
	 * This method extracts block and inline functions from the template,
	 * processes script tags, replaces blocks with PHP functions, removes
	 * inline functions, replaces inline functions, and replaces remaining
	 * block tags in the template.
	 *
	 * @param string $contents The contents of the template.
	 * @param string $filename The name of the template file.
	 * @return string The processed contents.
	 */
	public function parseFunctions(string $contents, string $filename): string
	{
		// Extrair blocos e funções inline
		$extractedFunctions = $this->extractBlockAndInlineFunctions($contents);
		$blocks = $extractedFunctions['blocks'];
		$inlines = $extractedFunctions['inlines'];

		// Processar tags <script>
		$contents = $this->processScriptTags($contents);

		// Substituir blocos por funções PHP
		$contents = $this->replaceBlocks($contents, $blocks, $filename);

		// Remover funções inline
		$contents = $this->removeInlines($contents, $inlines);

		// Substituir funções inline
		$contents = $this->replaceInlineFunctions($contents, $inlines);

		// Substituir tags de bloco restantes
		$lambda = sprintf("%u", crc32($filename));
		$contents = $this->replaceBlockTags($contents, $blocks, $lambda);

		return $contents;
	}

	/**
     * Parse foreach loops in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	private function parseForeachLoops(string $contents): string
	{
		if (preg_match_all("/\{foreach (.+)\}/sU", $contents, $matches))
		{
			foreach ($matches[1] as $key => $exp)
			{
				[$e_left, $e_right] = explode(" as ", trim(trim($exp, "()")));
				$e_right = explode("=>", $e_right);
				
				$leftExp = $this->processor->splitExp($e_left);
				
				$code = ($leftExp !== "array") ? "if (!empty(" . $leftExp . "))" : "";
				
				$code .= "foreach (" . $leftExp . " as " . $this->processor->splitExp($e_right[0]);
				if (count($e_right) == 2)
				{
					$code .= '=>' . $this->processor->splitExp($e_right[1]);
				}
				$code .= ') {';
				$contents = str_replace($matches[0][$key], trim(CodeUtils::code($code)), $contents);
			}
		}
		return $contents;
	}

	/**
     * Parse conditional and loop statements in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	private function parseConditionalAndLoopStatements(string $contents): string
	{
		if (preg_match_all("/\{(if|elseif|for|while) (.+)\}/sU", $contents, $matches))
		{
			foreach ($matches[1] as $key => $value)
			{
				if ($value === "for")
				{
					$matches[2][$key] = trim($matches[2][$key], "()");
				}
				
				$code = $value . "(" . $this->processor->splitExp($matches[2][$key]) . ") {";
					
				if ($value === "elseif")
				{
					$code = "}" . $code;
				}
				
				$contents = str_replace($matches[0][$key], CodeUtils::code($code), $contents);
			}
		}
		return $contents;
	}

	/**
     * Parse eval expressions in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	private function parseEvalExpressions(string $contents): string
	{
		if (preg_match_all("/\{(eval|eval_literal) (.+)\}/sU", $contents, $matches))
		{
			foreach ($matches as $match)
			{
				[$fullMatch, $type, $code] = $match;
				$code = rtrim(trim($code), ';');

				if ($type === "eval") {
					$code = $this->processor->splitExp($code);
				}

				$code .= ";";

				$contents = str_replace($fullMatch, CodeUtils::code($code), $contents);
			}
		}
		return $contents;
	}

	/**
     * Replace else and closing tags in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	private function replaceElseAndClosingTags(string $contents): string
	{
		$contents = str_replace("{else}", CodeUtils::code("}else{"), $contents);
		$contents = str_replace(["{/foreach}", "{/while}", "{/for}", "{/if}"], trim(CodeUtils::code("}")), $contents);
		return $contents;
	}

	/**
     * Parse expressions in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	public function parseExpressions(string $contents): string
	{
		// Parse foreach loops
		$contents = $this->parseForeachLoops($contents);

		// Parse if, elseif, for, and while statements
		$contents = $this->parseConditionalAndLoopStatements($contents);

		// Parse eval and eval_literal expressions
		$contents = $this->parseEvalExpressions($contents);

		// Replace {else} and closing tags
		$contents = $this->replaceElseAndClosingTags($contents);

		return $contents;
	}

	/**
	 * Generate PHP code for a variable.
	 *
	 * This method generates PHP code for rendering a variable, including any filters specified.
	 *
	 * @param string $value The variable expression.
	 * @return string The generated PHP code.
	 */
	private function generateVariableCode(string $value): string
	{
		if ($value[0] != '$' && !in_array($value[0], ['\'', '"']))
		{
			$value = '$' . $value;
		}

		if (strstr($value, "|") !== false)
		{
			list($left, $right) = explode("|", $value);
			$left = $this->processor->splitExp($left);

			switch ($right)
			{
				case "toupper":
					$right = "strtoupper";
					break;
				case "tolower":
					$right = "strtolower";
					break;
				case "escape":
					return "echo htmlspecialchars(" . $left . ", ENT_QUOTES);";
			}
			return "echo " . $right . "(" . $left . ");";
		}
		else
		{
			return "echo " . $this->processor->splitExp($value) . ";";
		}
	}

	/**
	 * Parse variables in the template.
	 *
	 * This method parses variables enclosed within curly braces in the template contents.
	 * It processes each variable and generates appropriate PHP code for rendering.
	 *
	 * @param string $contents The contents of the template.
	 * @return string The processed contents.
	 */
	public function parseVariables(string $contents): string
	{
		$contentsWithoutPhpTags = CodeUtils::removePhpTags($contents);
		
		if (preg_match_all("/\{([^\{]+)\}/sU", $contentsWithoutPhpTags, $matches))
		{
			foreach ($matches[1] as $key => $value)
			{
				if (strpos($value, "\n") === false && $value[0] != " ")
				{
					$code = $this->generateVariableCode($value);
					$contents = str_replace($matches[0][$key], CodeUtils::code($code), $contents);
				}
			}
		}

		return $contents;
	}

	/**
     * Clean up the template by removing unnecessary code.
     *
     * @param string $contents The contents of the template.
     * @return string The cleaned up contents.
     */
	public function cleanupTemplate(string $contents): string
	{
		$contents = CodeUtils::code('$_v = &$this->vars;') . $contents;
		
		$contents = str_replace(CodeUtils::TAG_CLOSE . CodeUtils::TAG_OPEN, '', $contents);
		
		$contents = str_replace(CodeUtils::TAG_CLOSE . "\n" . CodeUtils::TAG_OPEN . ' ', '', $contents);
		
		$contents = str_replace('echo ;', '', $contents);
		
		foreach ($this->literals as $key => $value)
		{
			$contents = str_replace("[[" . $key . "]]", $value, $contents);
		}

		return $contents;
	}
}