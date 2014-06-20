<?php
/**
 * SpecialBitIdConvert.php -- Convert existing account to BitId account
 * Copyright 2006,2007 Internet Brands (http://www.internetbrands.com/)
 * Copyright 2007,2008 Evan Prodromou <evan@prodromou.name>
 * Copyright 2014 David Llop
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @file
 * @author Evan Prodromou <evan@prodromou.name>
 * @author Thomas Gries
 * @author David Llop
 * @ingroup Extensions
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	exit( 1 );
}

require_once dirname(__FILE__) . "/vendors/BitID.php";

class SpecialBitIdConvert extends SpecialPage {

	function __construct() {
		parent::__construct( 'BitIdConvert', 'bitid-converter-access' );
	}

	function execute( $par ) {
		global $wgRequest, $wgUser, $wgOut, $wgBitIdProviders, $wgBitIdForcedProvider;

		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->setHeaders();

		$this->outputHeader();

		switch ( $par ) {

		case 'Finish':
			$this->finish();
			break;

		case 'Delete':
			$this->delete();
			break;

		default:
			
			$bitid_uri = SpecialBitIdLogin::executeBitId($wgOut, $wgRequest, $this->getTitle()->getFullUrl());

			$address = isset($_SESSION['bitid_address']) ? $_SESSION['bitid_address'] : null;

			if ( $address ) {
				$this->convert( $address );
			} else {
				$this->form($bitid_uri);
			}

		}
	}

	function convert( $bitid_url ) {
		global $wgUser, $wgOut, $wgRequest;

		# Expand Interwiki
		#$bitid_url = $this->interwikiExpand( $bitid_url );
		#wfDebug( "BitId: Attempting conversion with url: $bitid_url\n" );

		# Is this ID already taken?

		$other = MediawikiBitId::getUserFromAddress( $bitid_url );

		if ( isset( $other ) ) {
			if ( $other->getId() == $wgUser->getID() ) {
				$wgOut->showErrorPage(
					'bitiderror',
					'bitid-convert-already-your-bitid-text',
					array( $bitid_url )
				);
			} else {
				$wgOut->showErrorPage(
					'bitiderror',
					'bitid-convert-other-users-bitid-text',
					array( $bitid_url )
				);
			}
			unset($_SESSION['bitid_address']);
			return;
		}

		// If we're OK to here, let the user go log in
		SpecialBitIdLogin::login($bitid_url);
		$wgOut->redirect(Title::newFromText('Special:BitIdConvert/Finish')->getFullURL());

	}

	function form($bitid_uri) {
		global $wgOut, $wgUser;

		$wgOut->addHTML(
			Html::rawElement( 'form',
				array(
					'id' => 'bitid_form',
					'action' => $this->getTitle()->getLocalUrl(),
					'method' => 'post',
					'onsubmit' => 'bitid.update()'
				),
				Xml::fieldset( wfMessage( 'bitidconvertoraddmoreids' )->text() ) .
				Html::element( 'p',
					array(),
					wfMessage( 'bitidconvertinstructions' )->text()
				) .
				Xml::closeElement( 'fieldset' )
			)
		);
		
		SpecialBitIdLogin::main_view($wgOut, $bitid_uri);
		SpecialBitIdLogin::manual_view($wgOut, $bitid_uri, $this->getTitle()->getLocalUrl());

	}

	function delete() {
		global $wgUser, $wgOut, $wgRequest, $wgBitIdLoginOnly;

		$bitid = $wgRequest->getVal( 'url' );
		$user = MediawikiBitId::getUserFromAddress( $bitid );

		if ( $user->getId() == 0 || $user->getId() != $wgUser->getId() ) {
			$wgOut->showErrorPage( 'bitiderror', 'bitidconvertothertext' );
			return;
		}

		$wgOut->setPageTitle( wfMessage( 'bitiddelete' )->text() );

		# Check if the user is removing it's last BitId url
		$urls = MediawikiBitId::getUserBitIdInformation( $wgUser );
		if ( count( $urls ) == 1 ) {
			if ( $wgUser->mPassword == '' ) {
				$wgOut->showErrorPage( 'bitiderror', 'bitiddeleteerrornopassword' );
				return;
			} elseif ( $wgBitIdLoginOnly ) {
				$wgOut->showErrorPage( 'bitiderror', 'bitiddeleteerrorbitidonly' );
				return;
			}
		}

		if ( $wgRequest->wasPosted()
			&& $wgUser->matchEditToken( $wgRequest->getVal( 'bitidDeleteToken' ), $bitid ) ) {

			$ret = MediawikiBitId::removeUserAddress( $wgUser, $bitid );
			$wgOut->addWikiMsg( $ret ? 'bitiddelete-success' : 'bitiddelete-error' );
			return;
		}

		$wgOut->addWikiMsg( 'bitiddelete-text', $bitid );

		$wgOut->addHtml(
			Xml::openElement( 'form',
				array(
					'action' => $this->getTitle( 'Delete' )->getLocalUrl(),
					'method' => 'post'
				)
			) .
			Xml::submitButton( wfMessage( 'bitiddelete-button' )->text() ) .
			Html::Hidden( 'url', $bitid ) .
			Html::Hidden( 'bitidDeleteToken', $wgUser->getEditToken( $bitid ) ) .
			Xml::closeElement( 'form' )
		);
	}

	function finish() {
		global $wgUser, $wgOut;

		// This means the authentication succeeded.
		$bitid_url = $_SESSION['bitid_address'];
		unset($_SESSION['bitid_address']);

		if ( !isset( $bitid_url ) ) {
			wfDebug( "BitId: aborting in bitid converter because the bitid_url was missing\n" );
			$wgOut->showErrorPage( 'bitiderror', 'bitiderrortext' );
			return;
		}

		# We check again for dupes; this may be normalized or
		# reformatted by the server.

		$other = MediawikiBitId::getUserFromAddress( $bitid_url );

		if ( isset( $other ) ) {
			if ( $other->getId() == $wgUser->getID() ) {
				$wgOut->showErrorPage(
					'bitiderror', 
					'bitid-convert-already-your-bitid-text',
					array( $bitid_url )
				);
			} else {
				$wgOut->showErrorPage(
					'bitiderror',
					'bitid-convert-other-users-bitid-text',
					array( $bitid_url )
				);
			}
			return;
		}

		MediawikiBitId::addUserAddress( $wgUser, $bitid_url );

		SpecialBitIdLogin::loginSetCookie( $bitid_url );

		$wgOut->setPageTitle( wfMessage( 'bitidconvertsuccess' )->text() );
		$wgOut->setRobotPolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );
		$wgOut->addWikiMsg( 'bitidconvertsuccesstext', $bitid_url );
		$wgOut->returnToMain();
	}
}
