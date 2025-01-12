<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.4
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\ErrorHandler\Error;
use bdk\Teams\Cards\AdaptiveCard;
use bdk\Teams\Cards\CardInterface;
use bdk\Teams\Elements\FactSet;
use bdk\Teams\Elements\Table as TeamsTable;
use bdk\Teams\Elements\TableRow as TeamsTableRow;
use bdk\Teams\Elements\TextBlock as TeamsTextBlock;
use bdk\Teams\Enums;
use bdk\Teams\TeamsWebhook;
use RuntimeException;

/**
 * Send critical errors to Teams
 *
 * Not so much a route as a plugin (we only listen for errors)
 */
class Teams extends AbstractErrorRoute
{
    protected $cfg = array(
        'errorMask' => 0,
        'onClientInit' => null,
        'throttleMin' => 60, // 0 = no throttle
        'webhookUrl' => null, // default pulled from TEAMS_WEBHOOK_URL env var
    );

    /** @var TeamsWebhook */
    protected $teamsClient;

    protected $statsKey = 'teams';

    /**
     * {@inheritDoc}
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->cfg = \array_merge($this->cfg, array(
            'webhookUrl' => \getenv('TEAMS_WEBHOOK_URL'),
        ));
    }

    /**
     * Validate configuration values
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private function assertCfg()
    {
        if ($this->cfg['webhookUrl']) {
            return;
        }
        throw new RuntimeException(\sprintf(
            '%s: missing config value: %s.  Also tried env-var: %s',
            __CLASS__,
            'webhookUrl',
            'TEAMS_WEBHOOK_URL'
        ));
    }

    /**
     * Build Teams Table element with backtrace info
     *
     * @param Error $error Error instance
     *
     * @return TeamsTableRow
     */
    private function buildBacktraceTable(Error $error)
    {
        $rows = [
            (new TeamsTableRow(['file', 'line', 'function']))
                ->withStyle(Enums::CONTAINER_STYLE_ATTENTION),
        ];
        $frameDefault = array('file' => null, 'line' => null, 'function' => null);
        foreach ($error['backtrace'] as $frame) {
            $frame = \array_merge($frameDefault, $frame);
            $frame = \array_intersect_key($frame, $frameDefault);
            $rows[] = $frame;
        }
        return (new TeamsTable())
            ->withColumns(array(
                array('width' => 2),
                array('width' => 0.5, 'horizontalAlignment' => Enums::HORIZONTAL_ALIGNMENT_RIGHT),
                array('width' => 1),
            ))
            ->withFirstRowAsHeader()
            ->withGridStyle(Enums::CONTAINER_STYLE_ATTENTION)
            ->withShowGridLines()
            ->withRows($rows);
    }

    /**
     * {@inheritDoc}
     */
    protected function buildMessages(Error $error)
    {
        $icon = $error->isFatal()
            ? '🚫'
            : '⚠';
        $card = (new AdaptiveCard())
            ->withAddedElement(
                (new TeamsTextBlock($icon . ' ' . $error['typeStr']))->withStyle(Enums::TEXTBLOCK_STYLE_HEADING)
            )
            ->withAddedElement(
                (new TeamsTextBlock($this->getRequestMethodUri()))->withIsSubtle()
            )
            ->withAddedElement(
                (new TeamsTextBlock($error->getMessageText()))->withWrap()
            )
            ->withAddedElement(
                new FactSet(array(
                    'file' => $error['file'],
                    'line' => $error['line'],
                ))
            );
        if ($error->isFatal() && $error['backtrace']) {
            $card = $card->withAddedElement($this->buildBacktraceTable($error));
        }
        return [$card];
    }

    /**
     * Return SlackApi or SlackWebhook client depending on what config provided
     *
     * @return SlackApi|SlackWebhook
     */
    protected function getClient()
    {
        if ($this->teamsClient) {
            return $this->teamsClient;
        }
        $this->assertCfg();
        $this->teamsClient = new TeamsWebhook($this->cfg['webhookUrl']);
        if (\is_callable($this->cfg['onClientInit'])) {
            \call_user_func($this->cfg['onClientInit'], $this->teamsClient);
        }
        return $this->teamsClient;
    }

    /**
     * {@inheritDoc}
     */
    protected function sendMessages(array $messages)
    {
        foreach ($messages as $message) {
            $this->sendMessage($message);
        }
    }

    /**
     * Send message to Teams
     *
     * @param CardInterface $card Card message
     *
     * @return array
     */
    protected function sendMessage(CardInterface $card)
    {
        $teamsClient = $this->getClient();
        return $teamsClient->post($card);
    }
}
