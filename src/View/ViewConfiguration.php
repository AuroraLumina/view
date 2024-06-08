<?php

namespace AuroraLumina\View;

/**
 * Represents the configuration for the view templates.
 */
class ViewConfiguration
{
    /**
     * @var array<string> $paths The paths to the directory containing view templates.
     */
    private array $paths;

    /**
     * The location where compiled templates will be stored.
     *
     * @var string
     */
    private string $compileLocation;

    /**
     * Flag indicating whether compiled templates use absolute or relative paths.
     *
     * @var bool
     */
    private bool $compileAbsolute;

    /**
     * Constructs a new ViewConfiguration instance.
     *
     * @param array<string> $paths The paths to the directory containing view templates.
     * @param string $compileLocation The location where compiled templates will be stored. Defaults to "cache/".
     * @param bool $compileAbsolute Flag indicating whether compiled templates use absolute or relative paths. Defaults to false.
     */
    public function __construct(array $paths, string $compileLocation = "cache/", bool $compileAbsolute = false)
    {
        $this->paths = $paths;
        $this->compileLocation = $compileLocation;
        $this->compileAbsolute = $compileAbsolute;
    }

    /**
     * Sets the location for compiled templates.
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
     * Gets the paths to the directory containing view templates.
     * 
     * @return array<string> The paths to the directory containing view templates.
     */
    public function getTemplatePaths(): array
    {
        return $this->paths;
    }

    /**
     * Sets the paths to search for templates.
     *
     * @param array<string> $paths The paths to set.
     * @return void
     */
    public function setPaths(array $paths): void
    {
        $this->paths = $paths;
    }

    /**
     * Gets the location for compiled templates.
     *
     * @return string The location for compiled templates.
     */
    public function getCompileLocation(): string
    {
        return $this->compileLocation;
    }

    /**
     * Checks if compiled templates use absolute paths.
     *
     * @return bool True if compiled templates use absolute paths, false otherwise.
     */
    public function isCompileAbsolute(): bool
    {
        return $this->compileAbsolute;
    }
}
