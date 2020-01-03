<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector\Twig;

use bdk\Debug;
use bdk\Debug\Collector\Twig;
use Twig_Template;
use Twig_TemplateInterface;
use Exception;

/**
 * A Twig Template proxy
 */
class Template extends Twig_Template implements Twig_TemplateInterface
{

    protected $template;

    /**
     * @param Twig          $twig     Debug Twig_Environment wrapper
     * @param Twig_Template $template Twig_Template instance
     */
    public function __construct(Twig $twig, Twig_Template $template)
    {
        $this->twig = $twig;
        $this->template = $template;
    }

    /**
     * Magic method... inaccessible method called.
     *
     * @param string $name method name
     * @param array  $args method arguments
     *
     * @return mixed
     */
    public function __call($name, $args)
    {
        return \call_user_func_array(array($this->template, $name), $args);
    }

    /**
     * {@inheritDoc}
     */
    public function display(array $context, array $blocks = array())
    {
        $start = \microtime(true);
        $this->template->display($context, $blocks);
        $end = \microtime(true);
        $this->twig->addRenderedTemplate(array(
            'name' => $this->template->getTemplateName(),
            'duration' => $end - $start,
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function displayBlock($name, array $context, array $blocks = array(), $useBlocks = true)
    {
        $this->template->displayBlock($name, $context, $blocks, $useBlocks);
    }

    /**
     * {@inheritDoc}
     */
    public function displayParentBlock($name, array $context, array $blocks = array())
    {
        $this->template->displayParentBlock($name, $context, $blocks);
    }

    /**
     * {@inheritDoc}
     */
    public function getBlockNames(array $context = null, array $blocks = array())
    {
        return $this->template->getBlockNames();
    }

    /**
     * {@inheritDoc}
     */
    public function getBlocks()
    {
        return $this->template->getBlocks();
    }

    /**
     * {@inheritDoc}
     */
    public function doDisplay(array $context, array $blocks = array())
    {
        return $this->template->doDisplay($context, $blocks);
    }

    /**
     * {@inheritDoc}
     */
    public function getEnvironment()
    {
        return $this->template->getEnvironment();
    }

    /**
     * {@inheritDoc}
     */
    public function getParent(array $context)
    {
        return $this->template->getParent($context);
    }

    /**
     * {@inheritDoc}
     */
    public function getTemplateName()
    {
        return $this->template->getTemplateName();
    }

    /**
     * {@inheritDoc}
     */
    public function hasBlock($name, array $context = null, array $blocks = array())
    {
        return $this->template->hasBlock($name, $context, $blocks);
    }

    /**
     * {@inheritDoc}
     */
    public function isTraitable()
    {
        return $this->template->isTraitable();
    }

    /**
     * {@inheritDoc}
     */
    public function render(array $context)
    {
        $level = \ob_get_level();
        \ob_start();
        try {
            $this->display($context);
        } catch (Exception $e) {
            while (\ob_get_level() > $level) {
                \ob_end_clean();
            }
            throw $e;
        }
        return \ob_get_clean();
    }

    /**
     * {@inheritDoc}
     */
    public function renderBlock($name, array $context, array $blocks = array(), $useBlocks = true)
    {
        return $this->template->renderBlock($name, $context, $blocks, $useBlocks);
    }

    /**
     * {@inheritDoc}
     */
    public function renderParentBlock($name, array $context, array $blocks = array())
    {
        return $this->template->renderParentBlock($name, $context, $blocks);
    }
}
