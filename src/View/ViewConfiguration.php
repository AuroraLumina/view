<?php

namespace AuroraLumina\View;

/**
 * Class ViewConfiguration
 * Represents the configuration for the view templates.
 */
class ViewConfiguration
{
    /**
     * @var array<string> $paths The paths to the directory containing view templates.
     */
    private array $paths;

    /**
     * ViewConfiguration constructor.
     * 
     * @param string $paths The paths to the directory containing view templates.
     */
    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }
    
    /**
     * Get the path to the directory containing view templates.
     * 
     * @return array<string> The path to the directory containing view templates.
     */
    public function getTemplatePaths(): array
    {
        return $this->paths;
    }
}
