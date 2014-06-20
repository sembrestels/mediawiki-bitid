<?php
/**
 * Implements Special:BitIdLogin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

require_once dirname(__FILE__) . "/vendors/BitID.php";

/**
 * Implements Special:BitIdLogin
 *
 * @ingroup SpecialPage
 */
class SpecialBitIdLogin extends SpecialPage {
	function __construct() {
		parent::__construct('BitIdLogin');
		$this->bitid = new BitID();
	}
	function execute($par) {
		global $wgRequest, $wgUser, $wgOut;

		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		$headers = getallheaders();

		$address = isset($_SESSION['bitid_address'])? $_SESSION['bitid_address'] : false;
		
		if ($address && ($user = self::getUserFromAddress($address)) && $user instanceof User) {
			$wgUser = $user;
			$this->displaySuccessLogin($_SESSION['bitid_address']);
			return;
		} elseif ($wgUser->getID() != 0) {
			$this->alreadyLoggedIn();
			return;
		}
		if ($address) {
			if ($par == 'ChooseName') {
				$this->chooseName();
			} else {
				$this->chooseNameForm($address);
			}
			return;
		}

		if ($request->getText('ajax')) {
			$this->ajax();
		} elseif ($request->getText('uri') || isset($headers['Content-Type']) && $headers['Content-Type'] == 'application/json') {
			$this->callback(array(
				'uri' => $request->getText('uri'),
				'address' => $request->getText('address'),
				'signature' => $request->getText('signature'),
			));
			$output->redirect(Title::newFromText('Special:BitIdLogin')->getFullURL());
		}

		$nonce = (isset($_SESSION['bitid_nonce']))? $_SESSION['bitid_nonce'] : $this->bitid->generateNonce();

		$bitid_callback_uri = Title::newFromText('Special:BitIdLogin')->getFullURL();
		$bitid_uri = $this->bitid->buildURI($bitid_callback_uri, $nonce);
		$this->save_nonce($nonce);

		$this->main_view($output, $bitid_uri);
		$this->manual_view($output, $bitid_uri);
	}
	
	private function main_view($output, $bitid_uri) {
	
		$bitid_qr = $this->bitid->qrCode($bitid_uri);
	
		$output->addHTML('<span id="qr-code">');
		
		$output->addWikiText(
"===Scan this QRcode with your BitID enabled mobile wallet.===
You can also click on the QRcode if you have a BitID enabled desktop wallet.");

		$output->addHTML(
"<a href=\"$bitid_uri\"><img alt=\"Click on QRcode to activate compatible desktop wallet\" border=\"0\" src=\"$bitid_qr\" /></a>");

		$output->addWikiText("No compatible wallet? Use [[Special:BitIdLogin#bitid-manual-signing | manual signing]].");
		
		$output->addHTML(HTML::hidden('nonce', $this->bitid->extractNonce($bitid_uri)));
		
		$output->addHTML('</span>');
	}
	
	private function manual_view($output, $bitid_uri) {
		$output->addHTML('<span id="manual-signing" style="display:none">');
		
		$output->addWikiText(		
"===Manual signing===
The user experience is quite combersome, but it has the advangage of being compatible with all wallets including Bitcoin Core.

Please sign the challenge in the box below using the private key of this Bitcoin address you want to identify yourself with. Copy the text, open your wallet, choose your Bitcoin address, select the sign message function, paste the text into the message input and sign. After it is done, copy and paste the signature into the field below.

Cumbersome. Yep. Much better with a simple scan or click using a compatible wallet :)");

		$action_uri = Title::newFromText('Special:BitIdLogin')->getFullURL();
		$output->addHTML("<form action=\"$action_uri\">");

		$output->addHTML('<p>'.HTML::input('uri', $bitid_uri, 'text', array('readonly' => 'readonly', 'style' => 'width:450px')).'</p>');
		
		$output->addHTML('<p><label>Address</label><br/>'.HTML::input('address', null, null, array('placeholder' => 'Enter your public bitcoin address', 'style' => 'width:450px')).'</p>');
		
		$output->addHTML('<p><label>Signature</label><br/>'.HTML::input('signature', null, null, array('placeholder' => 'Enter the signature', 'style' => 'width:450px')).'</p>');
		
		$output->addHTML(HTML::hidden('title', 'Special:BitIdLogin'));

		$output->addHTML('<p>'.HTML::input('signin', 'Sign in!', 'submit').'</p>');

		$output->addWikiText("Back to [[Special:BitIdLogin#bitid-qr-code | QR code]].");
		
		$output->addHTML('</form>');
		
		$output->addHTML('</span>');
	}
	
	private function callback($variables) {

		header('Access-Control-Allow-Origin: *');
		$post_data = json_decode(file_get_contents('php://input'), true);
		// SIGNED VIA PHONE WALLET (data is send as payload)
		if($post_data!==null) {
			$variables = $post_data;
		}
		$uri = urldecode($variables['uri']);

		// ALL THOSE VARIABLES HAVE TO BE SANITIZED !
		$signValid = $this->bitid->isMessageSignatureValidSafe($variables['address'], $variables['signature'], $uri, true);
		$nonce = $this->bitid->extractNonce($uri);
		$signValid = true; // For testing porpouses
		if($signValid) {
			$dbw = wfGetDB(DB_MASTER);
			$dbw->update('bitid_nonces', array('address' => $variables['address']), array('nonce' => $nonce));

			// SIGNED VIA PHONE WALLET (data was sent as payload)
			if($post_data!==null) {
				//DO NOTHING
			} else {
				// SIGNED MANUALLY (data is stored in $_POST+$_REQUEST vs payload)
				// SHOW SOMETHING PRETTY TO THE USER
				$this->login($variables['address']);
			}
		} else {
			header("HTTP/1.0 401 Unauthorized");
			exit();
		}
	}
	
	private function ajax() {
		// check if this nonce is logged or not
		$dbr = wfGetDB(DB_SLAVE);
		$_address = $dbr->select('bitid_nonces', array('address'), array('nonce' => $_POST['nonce']));
		foreach ($_address as $addr) {
			$address = $addr->address;
		}
		if($address) {
			// Create session so the user could log in
			$this->login($address);
		}
		//return address/false to tell the VIEW it could log in now or not
		echo json_encode($address);
		exit();
	}
	
	private function save_nonce($nonce) {
		$_SESSION['bitid_nonce'] = $nonce;
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert('bitid_nonces', array('nonce'=> $nonce), __METHOD__, array('IGNORE'));
	}
	
	private function login($address, $data = array()) {
		global $wgUser;
		
		unset($_SESSION['bitid_nonce']);
		$_SESSION['bitid_address'] = $address;
		
		$user = self::getUserFromAddress($address);

		if ($user instanceof User) {
			# $this->updateUser($user, $data); # update from wallet
			$wgUser = $user;
		} else {
			# $this->saveValues($address, $data);
		}
	}
	
	/**
	 * 
	 * @param String $address
	 * @return null|User
	 */
	private static function getUserFromAddress($address) {
		
		$dbr = wfGetDB( DB_SLAVE );

		$id = $dbr->selectField(
			'bitid_users',
			'uoi_user',
			array( 'uoi_bitid' => $address ),
			__METHOD__
		);

		if ( $id ) {
			return User::newFromId( $id );
		} else {
			return null;
		}
	}
	
	/**
	 * Displays a form to let the user choose an account to attach with the
	 * given BitID
	 *
	 * @param $bitid String: BitID address
	 * @param $data Array: options get from BitID
	 * @param $messagekey String or null: message name to display at the top
	 */
	function chooseNameForm( $bitid, $data = array(), $messagekey = null ) {
		global $wgAuth, $wgOut, $wgBitIdAllowExistingAccountSelection,
			$wgHiddenPrefs, $wgUser, $wgBitIdAllowNewAccountname;

		if ( $messagekey ) {
			$wgOut->addWikiMsg( $messagekey );
		}
		$wgOut->addWikiMsg( 'bitidchooseinstructions' );

		$wgOut->addHTML(
			Xml::openElement( 'form',
				array(
					'action' => $this->getTitle( 'ChooseName' )->getLocalUrl(),
					'method' => 'POST'
				)
			) .
			Xml::fieldset( wfMessage( 'bitidchooselegend' )->text(),
				false,
				array(
					'id' => 'mw-bitid-choosename'
				)
			) .
			Xml::openElement( 'table' )
		);
		$def = false;

		if ( $wgBitIdAllowExistingAccountSelection ) {
			# Let them attach it to an existing user

			# Grab the UserName in the cookie if it exists

			global $wgCookiePrefix;
			$name = '';
			if ( isset( $_COOKIE["{$wgCookiePrefix}UserName"] ) ) {
				$name = trim( $_COOKIE["{$wgCookiePrefix}UserName"] );
			}

			# show BitId Attributes
			$bidAttributesToAccept = array( 'fullname', 'nickname', 'email', 'language' );
			$bidAttributes = array();

			/*
			foreach ( $bidAttributesToAccept as $bidAttr ) {

				if ( ( $bidAttr == 'fullname' )
					&& ( in_array( 'realname', $wgHiddenPrefs ) ) ) {
					continue;
				}

				if ( array_key_exists( $bidAttr, $sreg ) ) {
					$checkName = 'wpUpdateUserInfo' . $bidAttr;
					$bidAttributes[] = Xml::tags( 'li',
						array(),
						Xml::check( $checkName,
							false,
							array(
								'id' => $checkName
							)
						) .
						Xml::tags( 'label',
							array(
								'for' => $checkName
							),
							wfMessage( "bitid$bidAttr" )->text() . wfMessage( 'colon-separator' )->escaped() .
							Xml::element( 'i',
								array(),
								$sreg[$bidAttr]
							)
						)
					);
				}
			}
			*/

			$bidAttributesUpdate = '';
			/*
			if ( count( $bidAttributes ) > 0 ) {
				$bidAttributesUpdate = "<br />\n" .
					wfMessage( 'bitidupdateuserinfo' )->text() . "\n" .
					Xml::tags( 'ul',
						array(),
						implode( "\n", $bidAttributes )
					);
			}
			*/

			$wgOut->addHTML(
				Xml::openElement( 'tr' ) .
				Xml::tags( 'td',
					array(
						'class' => 'mw-label'
					),
					Xml::radio( 'wpNameChoice',
						'existing',
						!$def,
						array(
							'id' => 'wpNameChoiceExisting'
						)
					)
				) .
				Xml::tags( 'td',
					array(
						'class' => 'mw-input'
					),
					Xml::label( wfMessage( 'bitidchooseexisting' )->text(), 'wpNameChoiceExisting' ) . "<br />" .
					wfMessage( 'bitidchooseusername' )->text() .
					Xml::input( 'wpExistingName',
						16,
						$name,
						array(
							'id' => 'wpExistingName'
						)
					) . " " .
					wfMessage( 'bitidchoosepassword' )->text() .
					Xml::password( 'wpExistingPassword' ) .
					$bidAttributesUpdate
				) .
				Xml::closeElement( 'tr' )
			);

		if ( $wgAuth->allowPasswordChange() ) {

			$wgOut->addHTML(
				Xml::openElement( 'tr' ) .

				Xml::tags( 'td',
					array(),
					"&nbsp;"
				) .

				Xml::tags( 'td',
					array(),
					Linker::link(
						SpecialPage::getTitleFor( 'PasswordReset' ),
						wfMessage( 'passwordreset' )->escaped(),
						array(),
						array( 'returnto' => SpecialPage::getTitleFor( 'BitIdLogin' ) )
					)
				) .

				Xml::closeElement( 'tr' )
			);

		}



			$def = true;

		} // $wgBitIdAllowExistingAccountSelection

		# These are only available if the visitor is allowed to create account
		if ( $wgUser->isAllowed( 'createaccount' )
			&& $wgUser->isAllowed( 'bitid-create-account-with-bitid' )
			&& !$wgUser->isBlockedFromCreateAccount() ) {

			/*
			if ( $wgBitIdProposeUsernameFromSREG ) {

				# These options won't exist if we can't get them.
				if ( array_key_exists( 'nickname', $sreg ) && $this->userNameOK( $sreg['nickname'] ) ) {
					$wgOut->addHTML(
						Xml::openElement( 'tr' ) .
						Xml::tags( 'td',
							array(
								'class' => 'mw-label'
							),
							Xml::radio( 'wpNameChoice',
								'nick',
								!$def,
								array(
									'id' => 'wpNameChoiceNick'
								)
							)
						) .
						Xml::tags( 'td',
							array(
								'class' => 'mw-input'
							),
							Xml::label( wfMessage( 'bitidchoosenick', $sreg['nickname'] )->escaped(), 'wpNameChoiceNick' )
						) .
						Xml::closeElement( 'tr' )
					);
				}

				# These options won't exist if we can't get them.
				$fullname = null;
				if ( array_key_exists( 'fullname', $sreg ) ) {
					$fullname = $sreg['fullname'];
				}

				$axName = $this->getAXUserName( $ax );
				if ( $axName !== null ) {
					$fullname = $axName;
				}

				if ( $fullname && $this->userNameOK( $fullname ) ) {
					$wgOut->addHTML(
						Xml::openElement( 'tr' ) .
						Xml::tags( 'td',
							array(
								'class' => 'mw-label'
							),
							Xml::radio( 'wpNameChoice',
								'full',
								!$def,
								array(
									'id' => 'wpNameChoiceFull'
								)
							)
						) .
						Xml::tags( 'td',
							array(
								'class' => 'mw-input'
							),
							Xml::label( wfMessage( 'bitidchoosefull', $fullname )->escaped(), 'wpNameChoiceFull' )
						) .
						Xml::closeElement( 'tr' )
					);
					$def = true;
				}

				$idname = $this->toUserName( $bitid );
				if ( $idname && $this->userNameOK( $idname ) ) {
					$wgOut->addHTML(
						Xml::openElement( 'tr' ) .
						Xml::tags( 'td',
							array(
								'class' => 'mw-label'
							),
							Xml::radio( 'wpNameChoice',
								'url',
								!$def,
								array(
									'id' => 'wpNameChoiceUrl'
								)
							)
						) .
						Xml::tags( 'td',
							array(
								'class' => 'mw-input'
							),
							Xml::label( wfMessage( 'bitidchooseurl', $idname )->text(), 'wpNameChoiceUrl' )
						) .
						Xml::closeElement( 'tr' )
					);
					$def = true;
				}
			} // if $wgBitIdProposeUsernameFromSREG
			*/

			if ( $wgBitIdAllowNewAccountname ) {
				$wgOut->addHTML(

				Xml::openElement( 'tr' ) .
				Xml::tags( 'td',
					array(
						'class' => 'mw-label'
					),
					Xml::radio( 'wpNameChoice',
						'manual',
						!$def,
						array(
							'id' => 'wpNameChoiceManual'
						)
					)
				) .
				Xml::tags( 'td',
					array(
						'class' => 'mw-input'
					),
					Xml::label( wfMessage( 'bitidchoosemanual' )->text(), 'wpNameChoiceManual' ) . '&#160;' .
					Xml::input( 'wpNameValue',
						16,
						false,
						array(
							'id' => 'wpNameValue'
						)
					)
				) .
				Xml::closeElement( 'tr' )
				);
			}

		} // These are only available if all visitors are allowed to create accounts

		LoginForm::setLoginToken();

		# These are always available
		$wgOut->addHTML(
			Xml::openElement( 'tr' ) .
			Xml::tags( 'td',
				array(),
				''
			) .
			Xml::tags( 'td',
				array(
					'class' => 'mw-submit'
				),
				Xml::submitButton( MediawikiBitId::loginOrCreateAccountOrConvertButtonLabel(), array( 'name' => 'wpOK' ) ) .
				Xml::submitButton( wfMessage( 'cancel' )->text(), array( 'name' => 'wpCancel' ) )
			) .
			Xml::closeElement( 'tr' ) .
			Xml::closeElement( 'table' ) .
			Xml::closeElement( 'fieldset' ) .
			Html::Hidden( 'bitidChooseNameBeforeLoginToken', LoginForm::getLoginToken() ) .
			Xml::closeElement( 'form' )
		);
	}

	/**
	 * Handle "Choose name" form submission
	 */
	function chooseName() {
		global $wgRequest, $wgUser, $wgOut;

		if ( LoginForm::getLoginToken() != $wgRequest->getVal( 'bitidChooseNameBeforeLoginToken' ) ) {
			$wgOut->showErrorPage( 'bitiderror', 'bitid-error-request-forgery' );
			return;
		}

		$bitid = $_SESSION['bitid_address'];
		$data = array();
		
		if ( is_null( $bitid ) ) {
			# No messing around, here
			$wgOut->showErrorPage( 'bitiderror', 'bitiderrortext' );
			return;
		}

		if ( $wgRequest->getCheck( 'wpCancel' ) ) {
			unset($_SESSION['bitid_address']);
			$wgOut->showErrorPage( 'bitidcancel', 'bitidcanceltext' );
			return;
		}

		$choice = $wgRequest->getText( 'wpNameChoice' );
		$nameValue = $wgRequest->getText( 'wpNameValue' );

		if ( $choice == 'existing' ) {

			$user = $this->attachUser( $bitid, $data,
				$wgRequest->getText( 'wpExistingName' ),
				$wgRequest->getText( 'wpExistingPassword' )
			);

			if ( is_null( $user ) || !$user ) {
				$this->chooseNameForm( $bitid, null, 'wrongpassword' );
				return;
			}

			/*
			$force = array();
			foreach ( array( 'fullname', 'nickname', 'email', 'language' ) as $option ) {
				if ( $wgRequest->getCheck( 'wpUpdateUserInfo' . $option ) ) {
					$force[] = $option;
				}
			}
			*/

			# $this->updateUser( $user, $sreg, $ax );

		} else {

			$name = $this->getUserName( $bitid, $data, $choice, $nameValue );

			if ( !$name || !$this->userNameOK( $name ) ) {
				$this->chooseNameForm( $bitid );
				return;
			}

			$user = $this->createUser( $bitid, null, $name );
		}

		if ( is_null( $user ) ) {
			$wgOut->showErrorPage( 'bitiderror', 'bitiderrortext' );
			return;
		}

		$wgUser = $user;
		unset($_SESSION['bitid_address']);
		$this->displaySuccessLogin( $bitid );
	}
	
	/**
	 * Display the final "Successful login"
	 *
	 * @param $address String: BitID address
	 */
	function displaySuccessLogin( $address ) {
		global $wgUser, $wgOut;

		$this->setupSession();
		RequestContext::getMain()->setUser( $wgUser );
		$wgUser->SetCookies();
		unset($_SESSION['bitid_address']);

		# Run any hooks; ignore results
		$inject_html = '';
		wfRunHooks( 'UserLoginComplete', array( &$wgUser, &$inject_html ) );

		# Set a cookie for later check-immediate use

		$this->loginSetCookie( $address );

		$wgOut->setPageTitle( wfMessage( 'bitidsuccess' )->text() );
		$wgOut->setRobotPolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );
		$wgOut->addWikiMsg( 'bitidsuccesstext', $wgUser->getName(), $address );
		$wgOut->addHtml( $inject_html );
		list( $returnto, $returntoquery ) = $this->returnTo();
		$wgOut->returnToMain( null, $returnto, $returntoquery );
	}
	
	/**
	 * Displays an info message saying that the user is already logged-in
	 */
	function alreadyLoggedIn() {
		global $wgUser, $wgOut;

		$wgOut->setPageTitle( wfMessage( 'bitidalreadyloggedin' )->text() );
		$wgOut->setRobotPolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );
		$wgOut->addWikiMsg( 'bitidalreadyloggedintext', $wgUser->getName() );
		list( $returnto, $returntoquery ) = $this->returnTo();
		$wgOut->returnToMain( null, $returnto, $returntoquery );
	}
	
	function returnTo() {
		$returnto = isset( $_SESSION['bitid_returnto'] ) ? $_SESSION['bitid_returnto'] : '';
		$returntoquery = isset( $_SESSION['bitid_returntoquery'] ) ? $_SESSION['bitid_returntoquery'] : '';
		return array( $returnto, $returntoquery );
	}
	
	/**
	 * @param $bitid
	 * @param $data
	 * @param $name
	 * @param $password
	 * @return bool|null|User
	 */
	function attachUser( $bitid, $data = array(), $name, $password ) {
		global $wgAuth;

		$user = User::newFromName( $name );

		if ( $user->checkPassword( $password ) ) {

			// de-validate the temporary password
			$user->setNewPassword( null );
			MediawikiBitId::addUserAddress( $user, $bitid );

			return $user;

		}

		if ( $user->checkTemporaryPassword( $password ) ) {

			$wgAuth->updateUser( $user );
			$user->saveSettings();

			$reset = new SpecialChangePassword();
			$reset->setContext( $this->getContext()->setUser( $user ) );
			$reset->execute( null );

			return null;

		}

		return null;

	}

	
	/**
	 * @param $bitid
	 * @param $data
	 * @param $choice
	 * @param $nameValue
	 * @return mixed|null|string
	 */
	function getUserName( $bitid, $data, $choice, $nameValue ) {
		global $wgBitIdAllowNewAccountname;

		switch ( $choice ) {
		/*
		case 'nick':
		 	if ( $wgBitIdProposeUsernameFromSREG ) {
				return ( ( array_key_exists( 'nickname', $sreg ) ) ? $sreg['nickname'] : null );
			}
			break;
		case 'full':
			if ( !$wgBitIdProposeUsernameFromSREG ) {
				return;
			}
		 	# check the SREG first; only return a value if non-null
			$fullname = ( ( array_key_exists( 'fullname', $sreg ) ) ? $sreg['fullname'] : null );
			if ( !is_null( $fullname ) ) {
			 	return $fullname;
			}

			# try AX
			$fullname = ( ( array_key_exists( 'http://axschema.org/namePerson/first', $ax )
				|| array_key_exists( 'http://axschema.org/namePerson/last', $ax ) ) ?
				$ax['http://axschema.org/namePerson/first'][0] . " " . $ax['http://axschema.org/namePerson/last'][0] : null
			);

			return $fullname;
		case 'url':
			if ( $wgBitIdProposeUsernameFromSREG ) {
				return $this->toUserName( $bitid );
			}
			break;
		*/
		case 'manual':
			if ( $wgBitIdAllowNewAccountname ) {
				return $nameValue;
			}
		 default:
			return null;
		}
	}
	
	function createUser( $bitid, $data, $name ) {
		global $wgUser, $wgAuth;

		# Check permissions of the creating $wgUser
		if ( !$wgUser->isAllowed( 'createaccount' )
			|| !$wgUser->isAllowed( 'bitid-create-account-with-bitid' ) ) {
			wfDebug( "BitID: User is not allowed to create an account.\n" );
			return null;
		} elseif ( $wgUser->isBlockedFromCreateAccount() ) {
			wfDebug( "BitID: User is blocked.\n" );
			return null;
		}

		$user = User::newFromName( $name );

		if ( !$user ) {
			wfDebug( "BitID: Error adding new user.\n" );
			return null;
		}

		$user->addToDatabase();

		if ( !$user->getId() ) {
			wfDebug( "BitID: Error adding new user.\n" );
		} else {
			$wgAuth->initUser( $user );
			$wgAuth->updateUser( $user );

			$wgUser = $user;

			# new user account: not opened by mail
   			wfRunHooks( 'AddNewAccount', array( $user, false ) );
			$user->addNewUserLogEntry();

			# Update site stats
			$ssUpdate = new SiteStatsUpdate( 0, 0, 0, 0, 1 );
			$ssUpdate->doUpdate();

			MediawikiBitId::addUserAddress( $user, $bitid );
			//$this->updateUser( $user, $data, true );
			$user->saveSettings();
			return $user;
		}
	}
	
	/**
	 * Is this name OK to use as a user name?
	 */
	function userNameOK( $name ) {
		global $wgReservedUsernames;
		return ( 0 == User::idFromName( $name ) &&
				!in_array( $name, $wgReservedUsernames ) );
	}
	
	protected function setupSession() {
		if ( session_id() == '' ) {
			wfSetupSession();
		}
	}
	
	/**
	 * @param $bitid
	 */
	function loginSetCookie( $bitid ) {
		global $wgRequest, $wgBitIdCookieExpiration;
		$wgRequest->response()->setcookie( 'BitID', $bitid, time() +  $wgBitIdCookieExpiration );
	}

}
