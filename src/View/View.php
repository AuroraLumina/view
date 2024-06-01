<?php

namespace AuroraLumina\View;

use Exception;
use AuroraLumina\View\ViewConfiguration;

/**
 * Class View
 * Represents a view renderer.
 */
class View
{
    /**
     * @var ViewConfiguration $configuration The configuration for view templates.
     */
    private ViewConfiguration $configuration;

    /**
     * View constructor.
     * 
     * @param ViewConfiguration $configuration The configuration for view templates.
     */
    public function __construct(ViewConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }
    
    /**
     * Render a view.
     * 
     * @param string $view The name of the view to render.
     * @return string The rendered content of the view.
     * @throws Exception If the template file is not found.
     */
    protected function renderView(string $view): string
    {
        $viewPath = $this->configuration->getTemplatePath() . $view;

        if (!file_exists($viewPath))
        {
            throw new Exception('Template not found.');
        }

        $content = file_get_contents($viewPath);
        return $content;
    }

    /**
     * Render a view.
     * 
     * @param string $view The name of the view to render.
     * @return string The rendered content of the view.
     * @throws Exception If the template file is not found.
     */
    public function render(string $view): string
    {
        return $this->renderView($view);
    }
}
