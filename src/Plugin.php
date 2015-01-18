<?php

namespace Wikidata\Phergie\Plugin\Wikidata;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\DataModel\Revision;
use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;
use Wikibase\Api\WikibaseFactory;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Term\Term;

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
		if( count( $params ) === 0 ) {
			return 'You didn\'t pass any parameters!';
		}
		if( !array_key_exists( 1, $params ) ) {
			$params[1] = 'default';
		}
		if( !array_key_exists( 2, $params ) ) {
			$params[2] = 'default';
		}

		/** @var Item|Property|null $item */
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

		if( $item instanceof Item || $item instanceof Property ) {
			$msg = strtoupper( $params[0] );

			switch ( $params[1] ) {
				case 'label':
				case 'labels':
					$labels = $item->getFingerprint()->getLabels();
					switch ( $params[2] ) {
						case 'count':
						case 'default':
							return $msg . ': Has ' . $labels->count() . ' label(s)';
						case 'languages':
						case 'langs':
						case 'list':
							$labelLanguages = array();
							foreach( $labels->getIterator() as $label ) {
								/** @var Term $label */
								$labelLanguages[] = $label->getLanguageCode();
							}
							return $msg . ': Has labels for languages: ' . implode( ', ', $labelLanguages );
							break;
						default:
							if( $labels->hasTermForLanguage( $params[2] ) ) {
								return $msg . ': ' . $params[2] . ' label is "' . $labels->getByLanguage( $params[2] )->getText() . '"';
							} else {
								return $msg . ': Exists but there is no ' . $params[2] . ' label';
							}
					}
					break;
				case 'description':
				case 'descriptions':
					$descriptions = $item->getFingerprint()->getLabels();
					switch ( $params[2] ) {
						case 'count':
						case 'default':
							return $msg . ': Has ' . $descriptions->count() . ' description(s)';
						case 'languages':
						case 'langs':
						case 'list':
							$descriptionLanguages = array();
							foreach( $descriptions->getIterator() as $description ) {
								/** @var Term $description */
								$descriptionLanguages[] = $description->getLanguageCode();
							}
							return $msg . ': Has descriptions for languages: ' . implode( ', ', $descriptionLanguages );
							break;
						default:
							if( $descriptions->hasTermForLanguage( $params[2] ) ) {
								return $msg . ': ' . $params[2] . ' description is "' . $descriptions->getByLanguage( $params[2] )->getText() . '"';
							} else {
								return $msg . ': Exists but there is no ' . $params[2] . ' description';
							}
					}
					break;
				case 'aliases':
				case 'alias':
					$aliasGroup = $item->getFingerprint()->getAliasGroups();
					switch ( $params[2] ) {
						case 'languages':
						case 'lang':
						case 'list':
						case 'default':
							// TODO
							return 'TODO';
							break;
						default:
							if( $aliasGroup->hasGroupForLanguage( $params[2] ) ) {
								return $msg . ': ' . $params[2] . ' aliases are "' . implode( ', ', $aliasGroup->getByLanguage( $params[2] )->getAliases() ) . '"';
							} else {
								return $msg . ': Exists but there are no ' . $params[2] . ' aliases';
							}
					}
					break;
				case 'claim':
				case 'claims':
				case 'statement':
				case 'statements':
					// TODO
					return 'TODO';
					break;
				case 'sitelink':
				case 'sitelinks':
				case 'site':
				case 'sites':
					$siteLinkList = $item->getSiteLinkList();
					switch ( $params[2] ) {
						case 'count':
						case 'default':
							return $msg . ': Has ' . $siteLinkList->count() . ' sitelinks(s)';
						case 'sites':
						case 'list':
							$siteIds = array();
							foreach( $siteLinkList->getIterator() as $sitelink ) {
								/** @var SiteLink $sitelink */
								$siteIds[] = $sitelink->getSiteId();
							}
							return $msg . ': Has sitelinks for sites: ' . implode( ', ', $siteIds );
							break;
						default:
							if( $siteLinkList->hasLinkWithSiteId( $params[2] ) ) {
								return $msg . ': ' . $params[2] . ' sitelink is "' . $siteLinkList->getBySiteId( $params[2] )->getPageName() . '"';
							} else {
								return $msg . ': Exists but there is no ' . $params[2] . ' sitelink';
							}
					}
					break;
				case 'lastsummary':
				case 'summary':
					return $msg . ': Last summary was: ' . $revision->getEditInfo()->getSummary();
					break;
				case 'lastuser':
				case 'user':
					return $msg . ': Last edited by: ' . $revision->getUser();
					break;
				case 'pageid':
					return $msg . ': Page Id: ' . $revision->getPageId();
					break;
				case 'lastedited':
				case 'edited':
					return $msg . ': Last edited at: ' . $revision->getTimestamp();
					break;
				default:
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
		}

		return 'Something went wrong';
	}

}