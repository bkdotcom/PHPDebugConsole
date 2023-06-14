<?php

namespace bdk\Teams\Elements;

use InvalidArgumentException;

/**
 * FactSet card element
 */
class FactSet extends AbstractElement
{
    /**
     * Constructor
     *
     * @param Fact[]|array<string, string>[] $facts Facts
     */
    public function __construct(array $facts = array())
    {
        parent::__construct();
        $this->type = 'FactSet';
        $this->fields = \array_merge($this->fields, array(
            'facts' => self::asFacts($facts),
        ));
    }

    /**
     * Returns content of card element
     *
     * @param float $version Card version
     *
     * @return array
     */
    public function getContent($version)
    {
        $content = parent::getContent($version);
        $content['facts'] = $this->fields['facts'];
        return self::normalizeContent($content, $version);
    }

    /**
     * Adds fact to element
     *
     * @param Fact $fact Fact
     *
     * @return static
     */
    public function withAddedFact(Fact $fact)
    {
        return $this->withAdded('facts', $fact);
    }

    /**
     * Return new instance with provided facts
     *
     * @param array $facts Fact objects of key/value array
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withFacts(array $facts)
    {
        if ($facts === array()) {
            throw new InvalidArgumentException(\sprintf(
                '%s - Facts must be non-empty',
                __METHOD__
            ));
        }
        return $this->with('facts', self::asFacts($facts));
    }

    /**
     * "Normalize" facts / name/values to Facts
     *
     * @param array $facts Key/value array or list of Fact objects
     *
     * @return Fact[]
     *
     * @throws InvalidArgumentException
     */
    private function asFacts(array $facts)
    {
        $factsNew = array();
        \array_walk($facts, static function ($value, $key) use (&$factsNew) {
            if ($value instanceof Fact) {
                $factsNew[] = $value;
                return;
            }
            if (\is_string($value) || \is_numeric($value)) {
                $factsNew[] = new Fact($key, $value);
                return;
            }
            throw new InvalidArgumentException(\sprintf(
                'Invalid Fact or value encountered at %s. Expected Fact, string, or numeric. %s provided.',
                $key,
                self::getTypeDebug($value)
            ));
        });
        return $factsNew;
    }
}
