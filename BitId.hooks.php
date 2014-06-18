<?php

/**
 * Redirect classes to hijack the core UserLogin and CreateAccount facilities, because
 * they're so badly written as to be impossible to extend
 */

class BitIdHooks {

	public static function onSpecialPage_initList( &$specialPagesList ) {
		global $wgBitIdLoginOnly, $wgSpecialPageGroups, $wgUser;

		# redirect all special login pages to our own BitId login pages
		# but only for entitled users

		$addBitIdSpecialPagesList = array();

		
		if ( $wgBitIdLoginOnly
			&& !$wgUser->isAllowed( 'bitid-create-account-without-bitid' )
			&& $wgUser->isAllowed( 'bitid-login-with-bitid' ) ) {

			$specialPagesList['Userlogin'] = 'SpecialBitIdLogin';

			# as Special:CreateAccount is an alias for Special:UserLogin/signup
			# we show our own BitId page here, too

			$specialPagesList['CreateAccount'] = 'SpecialBitIdLogin';

		}

		/*
		if ( !$wgUser->isLoggedIn()
			&& ( $wgUser->isAllowed( 'bitid-login-with-bitid' )
				|| $wgUser->isAllowed( 'bitid-create-account-with-bitid' ) ) ) {
			$addBitIdSpecialPagesList[] = 'Login';
		}
		*/

		$addBitIdSpecialPagesList[] = 'Login';
		#$addBitIdSpecialPagesList[] = 'Convert';
		#$addBitIdSpecialPagesList[] = 'Dashboard';

		# add the server-related Special pages

		foreach ( $addBitIdSpecialPagesList as $sp ) {
			$key = 'BitId' . $sp;
			$specialPagesList[$key] = 'SpecialBitId' . $sp;
			$wgSpecialPageGroups[$key] = 'bitid';
		}

		return true;
	}

	/**
	 * @param $personal_urls array
	 * @param $title Title
	 * @return bool
	 */
	public static function onPersonalUrls( &$personal_urls, &$title ) {
		global $wgBitIdHideBitIdLoginLink, $wgUser, $wgOut, $wgBitIdLoginOnly;

		if ( !$wgBitIdHideBitIdLoginLink
			&& ( $wgUser->getID() == 0 )) {

			$sk = $wgOut->getSkin();
			$returnto = $title->isSpecial( 'Userlogout' ) ? '' : ( 'returnto=' . $title->getPrefixedURL() );

			$personal_urls['bitidlogin'] = array(
				'text' => wfMessage( 'bitid' )->text(),
				'href' => $sk->makeSpecialUrl( 'BitIdLogin', $returnto ),
				'active' => $title->isSpecial( 'BitIdLogin' )
			);

			if ( $wgBitIdLoginOnly ) {
				# remove other login links
				foreach ( array( 'createaccount', 'login', 'anonlogin' ) as $k ) {
					if ( array_key_exists( $k, $personal_urls ) ) {
						unset( $personal_urls[$k] );
					}
				}
			}

		}

		return true;
	}

	/**
	 * Add the BitID module to the OutputPage.
	 *
	 * @param &$out OutputPage object
	 * @param &$skin Skin object
	 */
	public static function onBeforePageDisplay( $out, &$skin ) {
		global $wgBitIdLoginAnywhere;
		if( !$wgBitIdLoginAnywhere ) {
			return true;
		} elseif( !isset( $_SESSION ) ) {
			wfSetupSession();
		}

		$out->addModules( 'ext.bitid' );
		if( !LoginForm::getLoginToken() ) {
			LoginForm::setLoginToken();
		}
		$out->addHTML( Html::input(
			'wpLoginToken',
			LoginForm::getLoginToken(),
			'hidden'
		) );
		return true;
	}

	/**
	 * @param $user User
	 * @return string
	 */
	private static function getAssociatedBitIdsTable( $user ) {
		global $wgLang;

		$bitid_addrs_registration = BitId::getUserBitIdInformation( $user );
		$delTitle = SpecialPage::getTitleFor( 'BitIdConvert', 'Delete' );

		$rows = '';

		foreach ( $bitid_addrs_registration as $addr_reg ) {

			if ( !empty( $addr_reg->uoi_user_registration ) ) {
				$registrationTime = wfMessage(
					'bitid-addrs-registration-date-time',
					$wgLang->timeanddate( $addr_reg->uoi_user_registration, true ),
					$wgLang->date( $addr_reg->uoi_user_registration, true ),
					$wgLang->time( $addr_reg->uoi_user_registration, true )
				)->text();
			} else {
				$registrationTime = '';
			}

			$rows .= Xml::tags( 'tr', array(),
				Xml::tags( 'td',
					array(),
					Xml::element( 'a', array( 'href' => 'bitcoin:'.$addr_reg->uoi_openid ), $addr_reg->uoi_openid )
				) .
				Xml::tags( 'td',
					array(),
					$registrationTime
				) .
				Xml::tags( 'td',
					array(),
					Linker::link( $delTitle, wfMessage( 'bitid-addrs-delete' )->text(),
						array(),
						array( 'url' => $addr_reg->uoi_openid )
					)
				)
			) . "\n";
		}
		$info = Xml::tags( 'table', array( 'class' => 'wikitable' ),
			Xml::tags( 'tr', array(),
				Xml::element( 'th',
					array(),
					wfMessage( 'bitid-addr-url' )->text() ) .
				Xml::element( 'th',
					array(),
					wfMessage( 'bitid-addr-registration' )->text() ) .
				Xml::element( 'th',
					array(),
					wfMessage( 'bitid-addr-action' )->text() )
				) . "\n" .
			$rows
		);

		$info .= Linker::link(
			SpecialPage::getTitleFor( 'OpenIDConvert' ),
			wfMessage( 'openid-add-url' )->escaped()
		);
		
		return $info;

	}

	/**
	 * @param $user User
	 * @param $preferences array
	 * @return bool
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		global $wgHiddenPrefs, $wgAuth, $wgUser, $wgLang;
		
		// setting up user_properties up_property database key names
		// example 'bitid-userinfo-update-on-login-nickname'
		// FIXME: this could better be saved as a JSON encoded array in a single key

		$update = array();
		$update[ wfMessage( 'bitidnickname' )->text() ] = '-nickname';
		$update[ wfMessage( 'bitidemail' )->text() ] = '-email';
		if ( !in_array( 'realname', $wgHiddenPrefs ) ) {
			$update[ wfMessage( 'bitidfullname' )->text() ] = '-fullname';
		}
		$update[ wfMessage( 'bitidlanguage' )->text() ] = '-language';
		$update[ wfMessage( 'bitidtimezone' )->text() ] = '-timezone';

		$preferences['bitid-userinfo-update-on-login'] =
			array(
				'section' => 'bitid/bitid-userinfo-update-on-login',
				'type' => 'multiselect',
				'label-message' => 'bitid-userinfo-update-on-login-label',
				'options' => $update,
			);

		$preferences['bitid-associated-bitids'] =
			array(
				'section' => 'bitid/bitid-associated-bitids',
				'type' => 'info',
				'label-message' => 'bitid-associated-bitids-label',
				'default' => self::getAssociatedBitIdsTable( $user ),
				'raw' => true,
			);

		if ( $wgAuth->allowPasswordChange() ) {

			$resetlink = Linker::link(
				SpecialPage::getTitleFor( 'PasswordReset' ),
				wfMessage( 'passwordreset' )->escaped(),
				array(),
				array( 'returnto' => SpecialPage::getTitleFor( 'Preferences' ) )
			);

			if ( empty( $wgUser->mPassword ) && empty( $wgUser->mNewpassword ) ) {

 				$preferences['password'] = array(
					'section' => 'personal/info',
					'type' => 'info',
					'raw' => true,
					'default' => $resetlink,
					'label-message' => 'yourpassword',
				);

			} else {

				$preferences['resetpassword'] = array(
					'section' => 'personal/info',
					'type' => 'info',
					'raw' => true,
					'default' => $resetlink,
					'label-message' => null,
				);

			}

			global $wgCookieExpiration;

			if ( $wgCookieExpiration > 0 ) {

				unset( $preferences['rememberpassword'] );
				$preferences['rememberpassword'] = array(
					'section' => 'personal/info',
					'type' => 'toggle',
					'label' => wfMessage(
						'tog-rememberpassword',
						$wgLang->formatNum( ceil( $wgCookieExpiration / ( 3600 * 24 ) ) )
						)->escaped(),
				);

			}

		}

		return true;
	}

	/**
	 * @param $user User
	 * @return bool
	 */
	public static function onDeleteAccount( &$user ) {
		global $wgOut;

		if ( is_object( $user ) ) {

			$username = $user->getName();
			$userID = $user->getID();

  			$dbw = wfGetDB( DB_MASTER );

			$dbw->delete( 'bitid_users', array( 'uoi_user' => $userID ) );
			$wgOut->addHTML( "BitID " . wfMessage( 'usermerge-userdeleted', $username, $userID )->escaped() . "<br />\n" );

		}

		return true;

	}

	/**
	 * @param $fromUserObj User
	 * @param $toUserObj User
	 * @return bool
	 */
	public static function onMergeAccountFromTo( &$fromUserObj, &$toUserObj ) {
		global $wgOut, $wgBitIdMergeOnAccountMerge;

		if ( is_object( $fromUserObj ) && is_object( $toUserObj ) ) {
			$fromUsername = $fromUserObj->getName();
			$fromUserID = $fromUserObj->getID();
			$toUsername = $toUserObj->getName();
			$toUserID = $toUserObj->getID();

			if ( $wgBitIdMergeOnAccountMerge ) {
				$dbw = wfGetDB( DB_MASTER );

				$dbw->update( 'bitid_users', array( 'uoi_user' => $toUserID ), array( 'uoi_user' => $fromUserID ) );
				$wgOut->addHTML( "BitID " . wfMessage( 'usermerge-updating', 'bitid_users', $fromUsername, $toUsername )->escaped() . "<br />\n" );

			} else {

				$wgOut->addHTML( wfMessage( 'bitid-bitids-were-not-merged' )->escaped() . "<br />\n" );

			}
		}
		return true;
	}

	/**
	 * @param $updater DatabaseUpdater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater = null ) {
		switch ( $updater->getDB()->getType() ) {
		case "mysql":
			return self::MySQLSchemaUpdates( $updater );
		default:
			throw new MWException("BitId does not support {$updater->getDB()->getType()} yet.");
		}
	}

	/**
	 * @param $updater MysqlUpdater
	 * @return bool
	 */
	public static function MySQLSchemaUpdates( $updater = null ) {
		$updater->addExtensionTable( 'bitid_nonces',
			dirname( __FILE__ ) . '/scheme/nonces.sql', true );
		$updater->addExtensionTable( 'bitid_users',
			dirname( __FILE__ ) . '/scheme/users.sql', true );
		return true;
	}
}