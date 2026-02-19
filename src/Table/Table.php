<?php

namespace bdk\Table;

use bdk\Debug\Utility\ArrayUtil;
use bdk\Table\Element;
use bdk\Table\TableRow;

/**
 * Represents a table of data
 */
class Table extends Element
{
    /** @var Element|null */
    protected $caption;

    /** @var array<string,mixed> */
    protected $defaults = array(
        'tagName' => 'table',
    );

    /** @var string */
    protected $tagName = 'table';

    /** @var Element|null */
    protected $tbody;

    /** @var Element|null */
    protected $tfoot;

    /** @var Element|null */
    protected $thead;

    /**
     * Constructor
     *
     * @param array $children Table (body) rows
     */
    public function __construct(array $children = array())
    {
        $this->setChildren($children);
    }

    /**
     * {@inheritDoc}
     */
    public function __serialize()
    {
        $data = parent::__serialize() + array(
            'caption' => $this->getCaption(),
            'footer' => $this->getFooter(),
            'header' => $this->getHeader(),
            'rows' => $this->getRows(),
        );
        \ksort($data);
        if (isset($data['meta']['columns'])) {
            foreach ($data['meta']['columns'] as &$column) {
                \ksort($column);
            }
        }
        return \array_filter($data);
    }

    /**
     * Get Caption
     *
     * @return Element|null
     */
    public function getCaption()
    {
        return $this->caption;
    }

    /**
     * {@inheritDoc}
     */
    public function getChildren()
    {
        return \array_filter([
            $this->caption,
            $this->thead,
            $this->tbody,
            $this->tfoot,
        ]);
    }

    /**
     * Get footer row
     *
     * @return TableRow|list<TableRow>|null
     */
    public function getFooter()
    {
        if ($this->tfoot === null) {
            return null;
        }
        $rows = $this->tfoot->getChildren();
        return \count($rows) > 1
            ? $rows
            : $rows[0];
    }

    /**
     * Get header row
     *
     * @return TableRow|list<TableRow>|null
     */
    public function getHeader()
    {
        if ($this->thead === null) {
            return null;
        }
        $rows = $this->thead->getChildren();
        return \count($rows) > 1
            ? $rows
            : $rows[0];
    }

    /**
     * Get (body)Rows
     *
     * @return array<TableRow>
     */
    public function getRows()
    {
        return $this->tbody->getChildren();
    }

    /**
     * Append row to tbody
     *
     * @param array|TableRow $row Table row
     *
     * @return $this
     */
    public function appendRow($row)
    {
        $row = $this->tableRow($row);
        $this->tbody->appendChild($row);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setCaption($caption)
    {
        if ($caption === null) {
            $this->caption = null;
            return $this;
        }
        if (!($caption instanceof Element)) {
            $caption = new Element('caption', $caption);
        }
        $caption->setParent($this);
        $caption->setDefaults(array(
            'tagName' => 'caption',
        ));
        $this->caption = $caption;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setChildren(array $children)
    {
        $this->caption = null;
        $this->thead = null;
        $this->setTbody();
        $this->tfoot = null;
        if (ArrayUtil::isList($children) === false) {
            $this->setProperties($children);
            return $this;
        }
        \array_walk($children, function ($child) {
            $tagName = $child instanceof Element ? $child->getTagname() : null;
            if (\in_array($tagName, ['caption', 'thead', 'tbody', 'tfoot'], true)) {
                $child->setParent($this);
                $this->{$tagName} = $child;
                return;
            }
            $this->appendRow($child);
        });
        return $this;
    }

    /**
     * Set footer row
     *
     * @param array|TableRow $footer Footer row
     *
     * @return $this
     */
    public function setFooter($footer)
    {
        if ($footer === null) {
            $this->tfoot = null;
            return $this;
        }
        $footerRow = $this->tableRow($footer);
        $this->tfoot = (new Element('tfoot'))
            ->setParent($this)
            ->setChildren([$footerRow]);
        return $this;
    }

    /**
     * Set header row
     *
     * @param array|TableRow $header Header row
     *
     * @return $this
     */
    public function setHeader($header)
    {
        if ($header === null) {
            $this->thead = null;
            return $this;
        }
        $headerRow = $this->tableRow($header);
        $cells = $headerRow->getCells();
        foreach ($cells as $cell) {
            $cell->setTagname('th')->setDefaults(array(
                'attribs' => array('scope' => 'col'),
                'tagName' => 'th',
            ));
        }
        $this->thead = (new Element('thead'))
            ->setParent($this)
            ->setChildren([$headerRow]);
        return $this;
    }

    /**
     * Set (body) rows
     *
     * @param array<TableRow>|array<array> $rows Table rows
     *
     * @return $this
     */
    public function setRows(array $rows)
    {
        $this->tbody->setChildren(
            \array_map([$this, 'tableRow'], \array_values($rows))
        );
        return $this;
    }

    /**
     * Initialize tbody child
     *
     * @return void
     */
    private function setTbody()
    {
        $this->tbody = new Element('tbody');
        $this->tbody->setParent($this);
    }

    /**
     * Convert array to TableRow if necessary
     *
     * @param array|TableRow $row TableRow
     *
     * @return TableRow
     */
    private function tableRow($row)
    {
        return $row instanceof TableRow
            ? $row
            : new TableRow($row);
    }
}
