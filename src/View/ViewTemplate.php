<?php

namespace AuroraLumina\View;

use Exception;
use AuroraLumina\View\Parsers\TemplateParser;
use AuroraLumina\View\Processors\TemplateProcessor;

abstract class ViewTemplate
{
    /**
     * The paths to search for template files.
     *
     * @var array
     */
    protected array $paths;

    /**
     * The location where compiled templates will be stored.
     *
     * @var string
     */
    protected string $compileLocation;

    /**
     * Flag indicating whether compiled templates use absolute or relative paths.
     *
     * @var bool
     */
    protected bool $compileAbsolute;

    /**
     * Default variables to be passed to templates.
     *
     * @var array
     */
    protected array $defaults = [["ldelim", "{"], ["rdelim", "}"]];

    /**
     * Flag indicating whether to disable caching.
     *
     * @var bool
     */
    protected bool $nocache = false;

    /**
     * The stack of template data during parsing.
     *
     * @var array
     */
    protected array $stack = [];

    /**
     * The filename of the template being parsed.
     *
     * @var string|null
     */
    protected ?string $filename = null;

    /**
     * The source code of the template being parsed.
     *
     * @var string|null
     */
    protected ?string $source = null;

    /**
     * Variables to be passed to the template being parsed.
     *
     * @var array
     */
    protected array $vars = [];

    /**
     * The template compiler instance.
     * 
     * @var ViewCompiler
     */
    private ViewCompiler $compiler;

    /**
     * Constructor.
     * 
     * @param TemplateProcessor $processor The template processor instance.
     */
    public function __construct(TemplateProcessor $processor)
    {
        $this->compiler = new ViewCompiler($processor);
    }

    /**
     * Set default variables.
     *
     * @return void
     */
    protected function defaultVariables(): void
    {
        if (empty($this->stack))
        {
            $this->vars = [];
        }
        $this->filename = null;
        foreach ($this->defaults as $value)
        {
            $this->assign($value[0], $value[1]);
        }
    }

    
    /**
     * Add a default variable or variables to the template.
     *
     * @param string|array $k The key or array of key-value pairs.
     * @param mixed $v The value if a single key is provided.
     * @return void
     */
    public function addDefault(string $key, mixed $value = ''): void
    {
        $this->defaults[] = [$key, $value];
    }

    /**
     * Push the current template onto the stack.
     *
     * @return void
     */
    protected function push(): void
    {
        $this->stack[] = $this->filename;
    }

    /**
     * Pop the last template from the stack.
     *
     * @return void
     */
    protected function pop(): void
    {
        $this->filename = array_pop($this->stack);
    }

    /**
     * Load a template file.
     *
     * @param string $filename The name of the template file to load.
     * @return bool True if the template is loaded successfully, false otherwise.
     */
    protected function load(string $filename): bool
    {
        if (($path = $this->findPath($filename)) !== false)
        {
            $fileOriginal = $path . $filename;
            $fileCompiled = $this->compilePath($path) . $filename;

            if (!file_exists($fileCompiled) || (file_exists($fileOriginal) && filemtime($fileOriginal) > filemtime($fileCompiled)))
            {
                if (!$this->compile($fileOriginal, $fileCompiled))
                {
                    throw new Exception(sprintf("Template file '%s' doesn't exist.", $filename));
                }
            }

            $this->filename = $fileCompiled;
            $this->source = $filename;
            return true;
        }

        return false;
    }

    /**
     * Compile a template file.
     *
     * @param string $s The source template file.
     * @param string $d The destination compiled file.
     * @return bool True if compilation is successful, false otherwise.
     */
    protected function compile(string $template, string $out): bool
    {
        return $this->compiler->compile($template, $out, function($file) {
            return $this->findPath($file);
        }, $this->nocache);
    }

    /**
     * Set the paths to search for templates.
     *
     * @param array<string> ...$paths The paths to set.
     * @return void
     */
    protected function setPaths(array $paths): void
    {
        if (empty($paths))
        {
            $paths = ["templates/"];
        }
        $this->paths = $paths;
    }

    /**
     * Set the location for compiled templates.
     *
     * @param string $path The path for compiled templates.
     * @param bool $isAbsolute Whether the path is absolute or not.
     * @return void
     */
    public function setCompileLocation(string $path, bool $isAbsolute): void
    {
        $this->compileLocation = rtrim($path, "/") . "/";
        $this->compileAbsolute = $isAbsolute;
    }

    /**
     * Compile the path for a template file.
     *
     * @param string $path The path to compile.
     * @return string The compiled path.
     */
    protected function compilePath(string $path): string
    {
        return $this->compileAbsolute ? $this->compileLocation . $path : $path . $this->compileLocation;
    }

    /**
     * Find the path for a template file.
     *
     * @param string $filename The filename to find.
     * @return bool|string The path if found, false otherwise.
     */
    protected function findPath(string $filename): bool|string
    {
        foreach ($this->paths as $path)
        {
            $file = $path . $filename;
            if (file_exists($file) || file_exists($this->compilePath($path) . $filename))
            {
                return $path;
            }
        }

        return false;
    }

    /**
     * Assign variables to the template.
     *
     * @param string|array $key The key or array of key-value pairs.
     * @param mixed $value The value if a single key is provided.
     * @return void
     */
    protected function assign(string|array $key, mixed $value = ''): void
    {
        if (is_array($key))
        {
            $prefix = $value ? $value . "_" : '';
            foreach ($key as $inKey => $inValue)
            {
                $this->vars[$prefix . $inKey] = $inValue;
            }
        }
        else
        {
            $concat = str_starts_with($key, '.');
            $key = $concat ? substr($key, 1) : $key;
            $this->vars[$key] = ($concat && isset($this->vars[$key])) ?
                (is_array($value) ? array_merge($this->vars[$key], $value) :
                $this->vars[$key] . $value) : $value;
        }
    }

    /**
     * Get a variable value from the template.
     *
     * @param string $key The key of the variable.
     * @return mixed The value of the variable if exists, false otherwise.
     */
    protected function getVariable(string $key): mixed
    {
        return isset($this->vars[$key]) ? $this->vars[$key] : false;
    }

    /**
     * Render the template.
     *
     * @return void
     * @throws Exception If the filename is empty.
     */
    public function includeRender(): void
    {
        if ($this->filename === null)
        {
            throw new Exception(sprintf("Tried to render '%s'", $this->source));
        }
        include $this->filename;
    }

    /**
     * Get the rendered template as a string.
     *
     * @return string The rendered template.
     */
    public function get(): string
    {
        ob_start();
        $this->includeRender();
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
