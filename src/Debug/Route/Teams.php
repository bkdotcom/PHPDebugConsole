<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\Teams\Cards\AdaptiveCard;
use bdk\Teams\Cards\CardInterface;
use bdk\Teams\Elements\FactSet;
use bdk\Teams\Elements\Table as TeamsTable;
use bdk\Teams\Elements\TableRow as TeamsTableRow;
use bdk\Teams\Elements\TextBlock as TeamsTextBlock;
use bdk\Teams\Enums;
use bdk\Teams\TeamsWebhook;

/**
 * Send critical errors to Teams
 *
 * Not so much a route as a plugin (we only listen for errors)
 */
class Teams extends AbstractRoute
{
    use ErrorThrottleTrait;

    protected $cfg = array(
        'errorMask' => 0,
        'onClientInit' => null,
        'throttleMin' => 60, // 0 = no throttle
        'webhookUrl' => null, // default pulled from TEAMS_WEBHOOK_URL env var
    );

    /** @var TeamsWebhook */
    protected $teamsClient;

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->cfg = \array_merge($this->cfg, array(
            'errorMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_WARNING | E_USER_ERROR,
            'webhookUrl' => \getenv('TEAMS_WEBHOOK_URL'),
        ));
        $debug->errorHandler->setCfg('enableStats', true);
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            ErrorHandler::EVENT_ERROR => array('onError', -1),
        );
    }

    /**
     * ErrorHandler::EVENT_ERROR event subscriber
     *
     * @param Error $error error/event object
     *
     * @return void
     */
    public function onError(Error $error)
    {
        if ($this->shouldSend($error, 'teams') === false) {
            return;
        }
        $card = $this->buildMessage($error);
        $this->sendMessage($card);
    }

    /**
     * Return SlackApi or SlackWebhook client depending on what config provided
     *
     * @return SlackApi|SlackWebhook
     */
    protected function getTeamsClient()
    {
        if ($this->teamsClient) {
            return $this->teamsClient;
        }
        $this->teamsClient = new TeamsWebhook($this->cfg['webhookUrl']);
        if (\is_callable($this->cfg['onClientInit'])) {
            \call_user_func($this->cfg['onClientInit'], $this->teamsClient);
        }
        return $this->teamsClient;
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
        $teamsClient = $this->getTeamsClient();
        return $teamsClient->post($card);
    }

    /**
     * Build Teams error message
     *
     * @param Error $error Error instance
     *
     * @return CardInterface
     */
    private function buildMessage(Error $error)
    {
        $icon = $error->isFatal()
            ? '🚫'
            : '⚠';
        $card = (new AdaptiveCard())
            ->withAddedElement(
                (new TeamsTextBlock($icon . ' ' . $error['typeStr']))
                    ->withStyle(Enums::TEXTBLOCK_STYLE_HEADING)
            )
            ->withAddedElement(
                (new TeamsTextBlock(
                    $this->debug->isCli()
                        ? '$: ' . \implode(' ', $this->debug->getServerParam('argv', array()))
                        : $this->debug->serverRequest->getMethod()
                            . ' ' . $this->debug->redact((string) $this->debug->serverRequest->getUri())
                ))->withIsSubtle()
            )
            ->withAddedElement(
                (new TeamsTextBlock($error->getMessageText()))
                    ->withWrap()
            )
            ->withAddedElement(
                new FactSet(array(
                    'file' => $error['file'],
                    'line' => $error['line'],
                ))
            );
        if ($error->isFatal() && $error['backtrace']) {
            $card = $card->withAddedElement(
                $this->buildBacktraceTable($error)
            );
        }
        return $card;
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
        $rows = array(
            (new TeamsTableRow(array('file', 'line', 'function')))
                ->withStyle(Enums::CONTAINER_STYLE_ATTENTION),
        );
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
}
