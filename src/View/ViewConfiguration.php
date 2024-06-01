<?php

namespace AuroraLumina\View;

/**
 * Class ViewConfiguration
 * Represents the configuration for the view templates.
 */
class ViewConfiguration
{
    /**
     * @var string $path The path to the directory containing view templates.
     */
    private string $path;

    /**
     * ViewConfiguration constructor.
     * 
     * @param string $path The path to the directory containing view templates.
     */
    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/') . '/';
    }
    
    /**
     * Get the path to the directory containing view templates.
     * 
     * @return string The path to the directory containing view templates.
     */
    public function getTemplatePath(): string
    {
        return $this->path;
    }
}
