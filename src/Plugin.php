<?php

namespace Addshore\Phergie\Plugin\Wikidata;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\DataModel\Revision;
use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;
use Wikibase\Api\WikibaseFactory;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;

class Plugin extends AbstractPlugin
{
	/**
	 * @var WikibaseFactory
	 */
	private $wikidata;

	public function __construct() {
		$this->wikidata = new WikibaseFactory( new MediawikiApi( 'http://wikidata.org/w/api.php' ) );
	}

	/**
	 * Return an array of commands and associated methods
	 *
	 * @return array
	 */
	public function getSubscribedEvents() {
		return array(
			'command.wikidata' => 'handleWikidataCommand',
		);
	}


	/**
	 * Main plugin handler for registered action commands
	 *
	 * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
	 * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
	 */
	public function handleWikidataCommand(Event $event, Queue $queue) {
		$queue->ircPrivmsg(
			$event->getSource(),
			$this->getMessage( $event->getCustomParams() )
		);
	}

	/**
	 * @param array $params
	 *
	 * @return string
	 */
	private function getMessage( array $params ) {
		$noOfParams = count( $params );
		if( $noOfParams === 0 ) {
			return 'You didn\'t pass any parameters!';
		}

		/** @var Item|null $item */
		$item = null;
		/** @var Revision|null $revision */
		$revision = null;

		if( preg_match( ItemId::PATTERN, $params[0] ) || preg_match( PropertyId::PATTERN, $params[0] ) ) {
			$revision = $this->wikidata->newRevisionGetter()->getFromId( $params[0] );
			if( $revision === false ) {
				return $params[0] . ': No such wikidata item';
			} else {
				$item = $revision->getContent()->getNativeData();
			}
		} else {
			return 'Failed to match an entity id in request';
		}

		if( $item instanceof Item ) {
			if ( $noOfParams === 1 ) {
				$msg = strtoupper( $params[0] );
				if ( $item->getFingerprint()->hasLabel( 'en' ) ) {
					$msg .= ': ' . $item->getFingerprint()->getLabel( 'en' )->getText();
					if ( $item->getFingerprint()->hasDescription( 'en' ) ) {
						$msg .= ' - \'' . $item->getFingerprint()->getDescription( 'en' )->getText() . '\'';
					}
				} else {
					$msg .= ': Exists but there is no en label';
				}
				return $msg;
			}
			if( $noOfParams === 2 ) {
				$msg = strtoupper( $params[0] );
				switch ( $params[1] ) {
					case 'labelcount':
						return $msg . ': Has ' . $item->getFingerprint()->getLabels()->count() . ' labels';
					case 'descriptioncount':
						return $msg . ': Has ' . $item->getFingerprint()->getDescriptions()->count() . ' descriptions';
					case 'claimcount':
					case 'statementcount':
						return $msg . ': Has ' . $item->getStatements()->count() . ' claims / statements';
					default:
						return $msg . ': Trying to get unknown attribute';
				}
			}
		}

		return 'Something went wrong';
	}

}