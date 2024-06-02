<?php

namespace AuroraLumina\View;

/**
 * Class Compiler
 * 
 * This class is responsible for compiling AuroraLumina templates into PHP code.
 */
class ViewCompiler
{
    /** @var string $tagOpen The opening PHP tag */
    private string $tagOpen = "<?php";

    /** @var string $tagClose The closing PHP tag */
    private string $tagClose = "?>";

    /** @var array $globalVariables An array to store global variables */
    private array $globalVariables = [];

    /** @var array $literals An array to store literals */
    private array $literals = [];

	/**
     * Load the contents of a file.
     *
     * @param string $filename The name of the file to load.
     * @return string The contents of the file.
     */
	protected function loadContents(string $filename): string
	{
		$contents = file_get_contents($filename);
		
		if ($contents !== false && substr($contents, 0, 3) === "\xEF\xBB\xBF")
		{
			return substr($contents, 3);
		}
		
		return $contents;
	}

	/**
     * Process include statements in the template.
     *
     * @param string $contents The contents of the template.
     * @param callable $find_path A callback function to find the path of included files.
     * @return string The processed contents.
     */
	private function processIncludes(string $contents, callable $find_path): string
    {
        while (preg_match_all("/\{include\ (.*?)\}/s", $contents, $matches))
		{
            $matches = array_unique($matches[1]);
            foreach ($matches as $file)
			{
                $content = "<!-- " . $file . " -->";
                if (($function = call_user_func($find_path, $file)) !== false)
				{
                    $content = $this->loadContents($function . $file);
                }
                $contents = str_replace("{include " . $file . "}", $content, $contents);
            }
        }
        return $contents;
    }

	/**
     * Process load statements in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	private function processLoads(string $contents): string
	{
		while (preg_match_all("/\{load\ (.*?)\}/s", $contents, $matches))
		{
			$matches = array_unique($matches[1]);
			
			foreach ($matches as $file)
			{
				$fileVar = (substr($file, 0, 1) == '$') ? $this->splitExp($file) : '"' . $file . '"';
				
				$code = $this->code('$this->push();$this->load(' . $fileVar . ');$this->assign($_v);$this->render();$this->pop();');

				$contents = str_replace("{load " . $file . "}", $code, $contents);
			}
		}

		return $contents;
	}

	/**
     * Replace {nocache} tag with appropriate code.
     *
     * @param string $contents The contents of the template.
     * @param bool $nocache Flag indicating whether to use nocache.
     * @return string The processed contents.
     */
	private function replaceNocacheTag(string $contents, bool $nocache): string
	{
		$nocache = $nocache ? $this->code("@unlink(__FILE__);") : "";
		return str_replace("{*nocache*}", $nocache, $contents);
	}

	/**
     * Declare global variables in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	private function declareGlobalVariables(string $contents): string
	{
		$globals = array_unique($this->globalVariables);
		return $this->code('global ' . implode(", ", $globals) . ';') . $contents;
	}

	/**
     * Clean up the template by removing unnecessary code.
     *
     * @param string $contents The contents of the template.
     * @return string The cleaned up contents.
     */

	private function cleanupTemplate(string $contents): string
	{
		$contents = $this->code('$_v = &$this->vars;') . $contents;
		
		$contents = str_replace($this->tagClose . $this->tagOpen, '', $contents);
		
		$contents = str_replace($this->tagClose . "\n" . $this->tagOpen . ' ', '', $contents);
		
		$contents = str_replace('echo ;', '', $contents);
		
		foreach ($this->literals as $key => $value)
		{
			$contents = str_replace("[[" . $key . "]]", $value, $contents);
		}

		return $contents;
	}

	/**
     * Create the output directory if it does not exist.
     *
     * @param string $output_filename The name of the output file.
     * @return void
     */
	private function createOutputDirectory(string $output_filename): void
	{
		$output_directory = dirname($output_filename);
		if (!file_exists($output_directory))
		{
			mkdir($output_directory, 0777, true);
		}
	}

	/**
     * Write the contents to a file.
     *
     * @param string $output_filename The name of the output file.
     * @param string $contents The contents to write to the file.
     * @return bool True if writing was successful, false otherwise.
     */
	private function writeToFile(string $output_filename, string $contents): bool
	{
		if ($file = @fopen($output_filename, "w"))
		{
			fwrite($file, $contents);
			fclose($file);
			return true;
		}
		return false;
	}

	/**
     * Compile the template.
     *
     * @param string $filename The name of the template file.
     * @param string $output_filename The name of the output file.
     * @param callable $find_path A callback function to find the path of included files.
     * @param bool $nocache Flag indicating whether to use nocache.
     * @return int Status code indicating success or failure of compilation.
     */

	function compile(string $filename, string $output_filename, callable $find_path, bool $nocache): int
	{
		$contents = $this->loadContents($filename);

		if ($contents !== false && $contents !== "")
		{
			$contents = $this->processIncludes($contents, $find_path);
			$contents = $this->processLoads($contents);
			$contents = $this->replaceNocacheTag($contents, $nocache);
			$contents = $this->stripComments($contents);
			$contents = $this->parseConstants($contents);
			$contents = $this->parseFunctions($contents, $filename);
			$contents = $this->parseExpressions($contents);
			$contents = $this->parseVariables($contents);

			if (!empty($this->globalVariables))
			{
				$contents = $this->declareGlobalVariables($contents);
			}

			$contents = $this->cleanupTemplate($contents);

			$this->createOutputDirectory($output_filename);

			if ($this->writeToFile($output_filename, $contents))
			{
				return 1;
			}
		}

		return 0;
	}

	/**
     * Process strip comments in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	private function stripComments(string $contents): string
	{
		return preg_replace("/\{\*.+\*\}/sU", "", $contents);
	}

	/**
     * Replace constant definitions in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	private function parseConstants(string $contents): string
	{
		return preg_replace_callback(
			"/\{(\_[a-zA-Z0-9\_]+)\}/",
			function ($matches)
			{
				return $this->code("echo " . $matches[1] . ";");
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
     * Parse script tags in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	private function parseScriptTags(string $contents): string
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
			$blockCode = "if (!function_exists('" . $name . "_" . $lambda . "')) { function " . $name . "_" . $lambda . "(\$_v) {" . $this->tagClose . $code['content'] . $this->tagOpen . " } }";
			$contents = str_replace($code['src'], $this->code($blockCode), $contents);
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
			$contents = str_replace("{block:" . $name . "}", $this->code($name . "_" . $lambda . "(&\$_v);"), $contents);
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
	private function parseFunctions(string $contents, string $filename): string
	{
		// Extrair blocos e funções inline
		$extractedFunctions = $this->extractBlockAndInlineFunctions($contents);
		$blocks = $extractedFunctions['blocks'];
		$inlines = $extractedFunctions['inlines'];

		// Processar tags <script>
		$contents = $this->parseScriptTags($contents);

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
				
				$leftExp = $this->splitExp($e_left);
				
				$code = ($leftExp !== "array") ? "if (!empty(" . $leftExp . "))" : "";
				
				$code .= "foreach (" . $leftExp . " as " . $this->splitExp($e_right[0]);
				if (count($e_right) == 2)
				{
					$code .= '=>' . $this->splitExp($e_right[1]);
				}
				$code .= ') {';
				$contents = str_replace($matches[0][$key], trim($this->code($code)), $contents);
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
				
				$code = $value . "(" . $this->splitExp($matches[2][$key]) . ") {";
					
				if ($value === "elseif")
				{
					$code = "}" . $code;
				}
				
				$contents = str_replace($matches[0][$key], $this->code($code), $contents);
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
					$code = $this->splitExp($code);
				}

				$code .= ";";

				$contents = str_replace($fullMatch, $this->code($code), $contents);
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
		$contents = str_replace("{else}", $this->code("}else{"), $contents);
		$contents = str_replace(["{/foreach}", "{/while}", "{/for}", "{/if}"], trim($this->code("}")), $contents);
		return $contents;
	}

	/**
     * Parse expressions in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	private function parseExpressions(string $contents): string
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
	 * Parse variables in the template.
	 *
	 * This method parses variables enclosed within curly braces in the template contents.
	 * It processes each variable and generates appropriate PHP code for rendering.
	 *
	 * @param string $contents The contents of the template.
	 * @return string The processed contents.
	 */
	private function parseVariables(string $contents): string
	{
		$contentsWithoutPhpTags = $this->removePhpTags($contents);
		
		if (preg_match_all("/\{([^\{]+)\}/sU", $contentsWithoutPhpTags, $matches))
		{
			foreach ($matches[1] as $key => $value)
			{
				if (strpos($value, "\n") === false && $value[0] != " ")
				{
					$code = $this->generateVariableCode($value);
					$contents = str_replace($matches[0][$key], $this->code($code), $contents);
				}
			}
		}

		return $contents;
	}

	/**
	 * Remove PHP tags from the template contents.
	 *
	 * This method removes PHP code blocks from the template contents to avoid conflicts with the parser.
	 *
	 * @param string $contents The contents of the template.
	 * @return string The contents with PHP tags removed.
	 */
	private function removePhpTags(string $contents): string
	{
		return preg_replace("/\<\?php.+\?\>/sU", "", $contents);
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
			$left = $this->splitExp($left);

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
			return "echo " . $this->splitExp($value) . ";";
		}
	}

	/**
	 * Split the expression and process variables in it.
	 *
	 * This function splits the expression into tokens and processes variables within it.
	 *
	 * @param string $exp The expression to split.
	 * @return string The processed expression.
	 */
	private function splitExp(string $exp): string
	{
		$code = $this->prepareCode($exp);
		$tokens = token_get_all($code);
		[$objects, $variables] = $this->extractObjectsAndVariables($tokens);
		$this->processGlobalVariables($objects, $variables);
		$this->sortVariables($variables);

		foreach ($variables as $var) 
		{
			if ($var != '$this') 
			{
				$exp = str_replace($var, $this->getVariable($var), $exp);
			}
		}

		return $exp;
	}

	/**
	 * Prepare the code by replacing dots.
	 *
	 * @param string $exp The expression to prepare.
	 * @return string The prepared code.
	 */
	private function prepareCode(string $exp): string
	{
		return str_replace(".", "__1", "<?php if (" . $exp . ") { ?>");
	}

	/**
	 * Extract objects and variables from tokens.
	 *
	 * @param array $tokens The tokens array.
	 * @return array An array containing objects and variables.
	 */
	private function extractObjectsAndVariables(array $tokens): array
	{
		$objects = [];
		$variables = [];
		$variable = null;
		$variableContinues = false;

		foreach ($tokens as $key => $value)
		{
			if (is_array($value))
			{
				if ($value[0] == T_OBJECT_OPERATOR)
				{
					$variableContinues = false;
					$objects[] = $variable;
				}
				if ($value[0] == T_VARIABLE)
				{
					$this->processVariableToken($value[1], $variable, $variableContinues, $objects, $variables);
				}
				$value[0] = token_name($value[0]);
				$tokens[$key] = $value;
			}
		}

		$this->addLastVariableIfNotInArray($variable, $variables, $objects);

		return [$objects, $variables];
	}

	/**
	 * Process a variable token.
	 *
	 * @param string $tokenValue The value of the variable token.
	 * @param string|null &$variable The current variable being processed.
	 * @param bool &$variableContinues Flag indicating if the variable continues.
	 * @param array &$objects Array containing objects.
	 * @param array &$variables Array containing variables.
	 * @return void
	 */
	private function processVariableToken(string $tokenValue, ?string &$variable, bool &$variableContinues, array &$objects, array &$variables): void
	{
		if (!$variableContinues && isset($variable) && !in_array($variable, $objects))
		{
			$variables[] = $variable;
		}
		$variable = $variableContinues ? $variable . $tokenValue : $tokenValue;
		if (strstr($variable, "__1") !== false)
		{
			$this->processDottedVariable($variable, $variableContinues);
		}
		else if ($variableContinues)
		{
			$variableContinues = false;
		}
	}

	/**
	 * Process a dotted variable.
	 *
	 * @param string &$variable The dotted variable string.
	 * @param bool &$variableContinues Flag indicating if the variable continues.
	 * @return void
	 */
	private function processDottedVariable(string &$variable, bool &$variableContinues): void
	{
		$variable = str_replace("__1", ".", $variable);
		$variableContinues = false;
		if (substr($variable, -1) == ".")
		{
			$variableContinues = true;
		}
	}

	/**
	 * Add the last variable if it's not already in the array.
	 *
	 * @param string|null $variable The last variable.
	 * @param array &$variables Array containing variables.
	 * @param array &$objects Array containing objects.
	 * @return void
	 */
	private function addLastVariableIfNotInArray(?string $variable, array &$variables, array &$objects): void
	{
		if (isset($variable) && !in_array($variable, $variables) && !in_array($variable, $objects))
		{
			$variables[] = $variable;
		}
	}

	/**
	 * Process global variables.
	 *
	 * @param array $objects The objects array.
	 * @return void
	 */
	private function processGlobalVariables(array $objects, array &$variables): void
	{
		foreach ($objects as $object) 
		{
			if ($object !== null && $object !== '$this' && is_object($GLOBALS[substr($object, 1)])) 
			{
				$this->globalVariables[] = $object;
			} 
			else 
			{
				$variables[] = $object;
			}
		}
	}

	/**
	 * Sort variables by length and alphabetically.
	 *
	 * @param array $variables The variables array.
	 * @return void
	 */
	private function sortVariables(array &$variables): void
	{
		usort($variables, function ($a, $b) 
		{
			if (strlen($a) == strlen($b)) 
			{
				if ($a == $b) 
				{
					return 0;
				}
				return ($a < $b) ? 1 : -1;
			}
			return (strlen($a) < strlen($b)) ? 1 : -1;
		});
	}

	/**
	 * Returns the processed variable string.
	 *
	 * This function processes the input variable string, handling composite variables and returning the processed string.
	 *
	 * @param string $var The variable string to be processed.
	 * @return string The processed variable string.
	 */
	private function getVariable(string $variable): string
	{
		$leftModifier = ltrim($variable, '$');
		
		if ($this->isCompositeVariable($variable))
		{
			return $this->processCompositeVariable($leftModifier);
		}
		
		return $variable;
	}

	/**
	 * Checks if the given variable is composite.
	 *
	 * This function checks if the variable is composite, i.e., if it contains multiple indices separated by dots.
	 *
	 * @param string $var The variable string to be checked.
	 * @return bool True if the variable is composite, false otherwise.
	 */
	private function isCompositeVariable(string $var): bool
	{
		return ($var[0] !== '"' && $var[0] !== "'");
	}

	/**
	 * Processes a composite variable string.
	 *
	 * This function processes a composite variable string by splitting it into parts and constructing the processed string.
	 *
	 * @param string $var The composite variable string to be processed.
	 * @return string The processed composite variable string.
	 */
	private function processCompositeVariable(string $variable, string $stringVariable = '$_v'): string
	{
		$tableIndices = explode('.', $variable);

		foreach ($tableIndices as $index)
		{
			$stringVariable .= $this->buildIndexString($index);
		}

		return $stringVariable;
	}

	/**
	 * Builds the string representation of an index for a composite variable.
	 *
	 * This function constructs the string representation of an index for a composite variable.
	 *
	 * @param string $index The index string to be processed.
	 * @return string The processed index string.
	 */
	private function buildIndexString(string $index): string
	{
		if ($index[0] === '$')
		{
			return "[" . $this->getVariable($index) . "]";
		} else {
			return "['" . $index . "']";
		}
	}
	
	/**
     * Wrap code in PHP tags.
     *
     * @param string $s The code to wrap.
     * @return string The code wrapped in PHP tags.
     */
	public function code($s): string 
	{
		return $this->tagOpen." ".$s.$this->tagClose;
	}
}