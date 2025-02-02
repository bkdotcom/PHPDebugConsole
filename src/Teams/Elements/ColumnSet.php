<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams\Elements;

use bdk\Teams\Actions\ActionInterface;
use bdk\Teams\Enums;
use InvalidArgumentException;
use RuntimeException;

/**
 * ColumnSet divides a region into Columns, allowing elements to sit side-by-side
 */
class ColumnSet extends AbstractElement
{
    /**
     * Constructor
     *
     * @param Column[] $columns The array of Columns to divide the region into
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $columns = array())
    {
        self::assertColumns($columns);
        parent::__construct(array(
            'bleed' => null,
            'columns' => $columns,
            'horizontalAlignment' => null,
            'minHeight' => null,
            'selectAction' => null,
            'style' => null,
        ), 'ColumnSet');
    }

    /**
     * {@inheritDoc}
     */
    public function getContent($version)
    {
        if ($this->fields['columns'] === array()) {
            throw new RuntimeException('ColumnSet columns is empty');
        }

        $attrVersions = array(
            'bleed' => 1.2,
            'columns' => 1.0,
            'horizontalAlignment' => 1.2,
            'minHeight' => 1.5,
            'selectAction' => 1.1,
            'style' => 1.0,
        );

        $content = parent::getContent($version);
        foreach ($attrVersions as $name => $ver) {
            if ($version >= $ver) {
                /** @var mixed */
                $content[$name] = $this->fields[$name];
            }
        }

        return self::normalizeContent($content, $version);
    }

    /**
     * Return new instance with specified bleed
     *
     * Determines whether the element should bleed through its parent’s padding.
     *
     * @param bool $bleed Whether element should bleed
     *
     * @return static
     */
    public function withBleed($bleed = true)
    {
        self::assertBool($bleed, 'bleed');
        return $this->with('bleed', $bleed);
    }

    /**
     * Return new instance with specified columns
     *
     * @param Column[] $columns The array of Columns to divide the region into
     *
     * @return static
     */
    public function withColumns(array $columns = array())
    {
        self::assertColumns($columns);
        return $this->with('columns', $columns);
    }

    /**
     * Return new instance with specified horizontal alignment
     *
     * Controls the horizontal alignment of the ColumnSet.
     * When not specified, the value of horizontalAlignment is inherited from the parent container.
     * If no parent container has horizontalAlignment set, it defaults to Left.
     *
     * @param Enums::HORIZONTAL_ALIGNMENT_* $alignment Horizontal alignment
     *
     * @return static
     */
    public function withHorizontalAlignment($alignment)
    {
        self::assertEnumValue($alignment, 'HORIZONTAL_ALIGNMENT_', 'alignment');
        return $this->with('horizontalAlignment', $alignment);
    }

    /**
     * Return new instance with specified minHeight
     *
     * @param string $minHeight Specifies the minimum height of the container in pixels, like "80px"
     *
     * @return static
     */
    public function withMinHeight($minHeight)
    {
        self::assertPx($minHeight, __METHOD__);
        return $this->with('minHeight', $minHeight);
    }

    /**
     * Return new instance with specified select action
     *
     * An Action that will be invoked when the Container is tapped or
     * selected.
     *
     * Action.ShowCard is not supported.
     *
     * @param ActionInterface|null $action Action
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withSelectAction($action = null)
    {
        self::assertType($action, 'bdk\Teams\Actions\ActionInterface');

        if ($action && $action->get('type') === 'Action.ShowCard') {
            throw new InvalidArgumentException('ColumnSet selectAction does not support ShowCard');
        }
        return $this->with('selectAction', $action);
    }

    /**
     * Return new instance with specified container style
     *
     * @param Enums::CONTAINER_STYLE_* $style Container style
     *
     * @return static
     */
    public function withStyle($style)
    {
        self::assertEnumValue($style, 'CONTAINER_STYLE_', 'style');
        return $this->with('style', $style);
    }

    /**
     * Assert each column instance of Column
     *
     * @param Column[] $columns Columns to check
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function assertColumns(array $columns)
    {
        foreach ($columns as $i => $column) {
            if ($column instanceof Column) {
                continue;
            }
            throw new InvalidArgumentException('ColumnSet: Non-column found at index ' . $i);
        }
    }
}
