<?php

namespace Addshore\Phergie\Plugin\Wikidata;

use Mediawiki\Api\MediawikiApi;
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
		$params = $event->getCustomParams();
		$noOfParams = count( $params );
		if( $noOfParams === 1 ) {
			if( preg_match( ItemId::PATTERN, $params[0] ) || preg_match( PropertyId::PATTERN, $params[0] ) ) {
				/** @var Item $item */
				$revision = $this->wikidata->newRevisionGetter()->getFromId( $params[0] );
				if( $revision === false ) {
					$queue->ircPrivmsg( $event->getSource(), strtoupper( $params[0] ) . ': No such wikidata item' );
				} else {
					$item = $revision->getContent()->getNativeData();
					if( $item->getFingerprint()->hasLabel( 'en' ) ) {
						$queue->ircPrivmsg(
							$event->getSource(),
							strtoupper( $params[0] ) . ': ' . $item->getFingerprint()->getLabel( 'en' )->getText()
						);
					} else {
						$queue->ircPrivmsg( $event->getSource(), strtoupper( $params[0] ) . ': Exists but there is no en label' );
					}
				}
			}
		}
	}

}