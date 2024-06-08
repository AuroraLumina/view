<?php

namespace AuroraLumina\View;

use Exception;
use AuroraLumina\View\ViewConfiguration;

/**
 * Class View
 * Represents a view renderer.
 */
class View extends ViewTemplate
{
    /**
     * Constructs a new View object.
     *
     * Initializes the View with the provided configuration.
     * Sets the compile location and template paths based on the configuration.
     *
     * @param ViewConfiguration $configuration The configuration for the view.
     */
    public function __construct(ViewConfiguration $configuration)
    {
        $this->setPaths($configuration->getTemplatePaths());
        $this->setCompileLocation($configuration->getCompileLocation(), $configuration->isCompileAbsolute());
    }

    /**
     * Render a view.
     * 
     * @param string $view The name of the view to render.
     * @param array $data Optional data to pass to the view.
     * @return string The rendered content of the view.
     * @throws Exception If the template file is not found.
     */
    public function render(string $view, array $data = []): string
    {
        return $this->renderView($view, $data);
    }

    /**
     * Render a view.
     * 
     * @param string $view The name of the view to render.
     * @return string The rendered content of the view.
     * @throws Exception If the template file is not found.
     */
    protected function renderView(string $view, array $data): string
    {
        $this->defaultVariables();

        $this->load($view);

        foreach ($data as $key => $value)
        {
            $this->assign($key, $value);
        }

        return $this->get();
    }
}
