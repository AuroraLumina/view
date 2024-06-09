<?php

namespace AuroraLumina\View\Processors;

use AuroraLumina\View\Utils\CodeUtils;
use AuroraLumina\View\Utils\FileUtils;
use AuroraLumina\View\Utils\ArrayUtils;

/**
 * Class TemplateProcessor
 * 
 * Processes templates and performs variable substitutions.
 */
class TemplateProcessor
{
    /**
     * An array to store global variables.
     *
     * @var array
     */
    private array $globalVariables = [];

	/**
     * Returns the number of global variables.
     *
     * @return int
     */
	public function globalVariables(): int
	{
		return count($this->globalVariables);
	}

	/**
     * Process include statements in the template.
     *
     * @param string $contents The contents of the template.
     * @param callable $find_path A callback function to find the path of included files.
     * @return string The processed contents.
     */
	public function processIncludes(string $contents, callable $find_path): string
    {
        while (preg_match_all("/\{include\ (.*?)\}/s", $contents, $matches))
		{
            $matches = array_unique($matches[1]);
            foreach ($matches as $file)
			{
                $content = "<!-- " . $file . " -->";
                if (($function = call_user_func($find_path, $file)) !== false)
				{
                    $content = FileUtils::loadContents($function . $file);
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
	public function processLoads(string $contents): string
	{
		while (preg_match_all("/\{load\ (.*?)\}/s", $contents, $matches))
		{
			$matches = array_unique($matches[1]);
			
			foreach ($matches as $file)
			{
				$fileVar = (substr($file, 0, 1) == '$') ? $this->splitExp($file) : '"' . $file . '"';
				
				$code = CodeUtils::code('$this->push();$this->load(' . $fileVar . ');$this->assign($_v);$this->render();$this->pop();');

				$contents = str_replace("{load " . $file . "}", $code, $contents);
			}
		}

		return $contents;
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

		ArrayUtils::addLastVariableIfNotInArray($variable, $variables, $objects);

		return [$objects, $variables];
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
     * Declare global variables in the template.
     *
     * @param string $contents The contents of the template.
     * @return string The processed contents.
     */
	public function declareGlobalVariables(string $contents): string
	{
		$globals = array_unique($this->globalVariables);
		return CodeUtils::code('global ' . implode(", ", $globals) . ';') . $contents;
	}

	/**
	 * Split the expression and process variables in it.
	 *
	 * This function splits the expression into tokens and processes variables within it.
	 *
	 * @param string $exp The expression to split.
	 * @return string The processed expression.
	 */
	public function splitExp(string $exp): string
	{
		$code = CodeUtils::prepareCode($exp);
		$tokens = token_get_all($code);
		[$objects, $variables] = $this->extractObjectsAndVariables($tokens);
		$this->processGlobalVariables($objects, $variables);
		ArrayUtils::sortVariables($variables);

		foreach ($variables as $var) 
		{
			if ($var != '$this') 
			{
				$exp = str_replace($var, $this->getVariable($var), $exp);
			}
		}

		return $exp;
	}
}