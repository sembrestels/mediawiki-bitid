<?php

/**
 * This file is part of the MediaWiki extension BitID.
 * Copyright (C) 2014 David Llop <sembrestels@riseup.net>
 * Copyright (C) 2012 Tyler Romeo <tylerromeo@gmail.com>
 *
 * Extension:BitId is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * Extension:BitId is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with Extension:BitId.  If not, see <http://www.gnu.org/licenses/>.
 */

$wgBitIdLoginOnly = false;
$wgBitIdAllowExistingAccountSelection = true;
$wgBitIdAllowNewAccountname = true;
$wgBitIdCookieExpiration = 365 * 24 * 60 * 60;

define( 'MEDIAWIKI_BITID_VERSION', '0.1.0' );
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'BitId',
	'version' => MEDIAWIKI_BITID_VERSION,
	'author' => 'David Llop',
	'url' => 'https://www.mediawiki.org/wiki/Extension:BitId',
	'descriptionmsg' => 'bitid-desc'
);

$wgBitIdLoginAnywhere = true;

$wgHooks['PersonalUrls'][] = 'BitIdHooks::onPersonalUrls';
$wgHooks['BeforePageDisplay'][] = 'BitIdHooks::onBeforePageDisplay';
$wgHooks['SpecialPage_initList'][] = 'BitIdHooks::onSpecialPage_initList';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'BitIdHooks::onLoadExtensionSchemaUpdates';
$wgHooks['DeleteAccount'][] = 'BitIdHooks::onDeleteAccount';
$wgHooks['MergeAccountFromTo'][] = 'BitIdHooks::onMergeAccountFromTo';
$wgHooks['GetPreferences'][] = 'BitIdHooks::onGetPreferences';

$wgMessagesDirs['BitId'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['BitIdAlias'] = __DIR__ . "/BitId.alias.php";

$wgAutoloadClasses['SpecialBitIdLogin'] = __DIR__ . '/SpecialBitIdLogin.php';
$wgAutoloadClasses['SpecialBitIdConvert'] = __DIR__ . '/SpecialBitIdConvert.php';
$wgAutoloadClasses['SpecialBitIdDashboard'] = __DIR__ . '/SpecialBitIdDashboard.php';
$wgAutoloadClasses['BitIdHooks'] = __DIR__ . '/BitId.hooks.php';
$wgSpecialPages['BitIdLogin'] = 'SpecialBitIdLogin';
$wgSpecialPages['BitIdConvert'] = 'SpecialBitIdConvert';
$wgSpecialPages['BitIdDashboard'] = 'SpecialBitIdDashboard';


# new user rights
$wgAvailableRights[] = 'bitid-converter-access';
$wgAvailableRights[] = 'bitid-dashboard-access';
$wgAvailableRights[] = 'bitid-dashboard-admin';
$wgAvailableRights[] = 'bitid-login-with-bitid';
$wgAvailableRights[] = 'bitid-create-account-with-bitid';
$wgAvailableRights[] = 'bitid-create-account-without-bitid';

# allow everyone to login with BitID
$wgGroupPermissions['*']['bitid-login-with-bitid'] = true;

# uncomment to allow users read access the dashboard
# $wgGroupPermissions['user']['bitid-dashboard-access'] = true;

# allow users to add or convert BitIDs to their accounts
# but only if we do not enforce the use of a certain provider
# if $wgBitIDForcedProvider is set, the permission is set false
$wgGroupPermissions['user']['bitid-converter-access'] = true;

# allow sysops read access the dashboard and
# allow sysops to administrate the BitID settings (feature under construction)
$wgGroupPermissions['sysop']['bitid-dashboard-access'] = true;
$wgGroupPermissions['sysop']['bitid-dashboard-admin'] = true;

# allow sysops always to create accounts
# i.e. also in case of $wgBitIDLoginOnly==true
$wgGroupPermissions['*']['bitid-login-with-bitid'] = true;
$wgGroupPermissions['*']['bitid-create-account-with-bitid'] = true;
$wgGroupPermissions['*']['bitid-create-account-without-bitid'] = false;
$wgGroupPermissions['sysop']['bitid-create-account-without-bitid'] = true;


$wgResourceModules['ext.bitid'] = array(
	'scripts' => array('js/bitid_hooks.js'),
	'styles' => array(),
	'messages' => array(),
	'dependencies' => array( 'mediawiki.api', 'mediawiki.Title' ),
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'BitId'
);

class MediawikiBitId {
	
	/**
	 * Find the user with the given bitid
	 *
	 * @param $user
	 * @return array return the registered BitID addresses and registration timestamps (if available)
	 */
	public static function getUserBitIdInformation( $user ) {
		$bitid_addrs_registration = array();

		if ( $user instanceof User && $user->getId() != 0 ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				array( 'bitid_users' ),
				array( 'uoi_bitid', 'uoi_user_registration' ),
				array( 'uoi_user' => $user->getId() ),
				__METHOD__
			);

			foreach ( $res as $row ) {
				$bitid_addrs_registration[] = $row;
			}
			$res->free();
		}
		return $bitid_addrs_registration;
	}
	
	/**
	 * 
	 * @param String $address
	 * @return null|User
	 */
	public static function getUserFromAddress($address) {
		
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
	 * @return string
	 */
	public static function loginOrCreateAccountOrConvertButtonLabel() {
		global $wgUser, $wgOut;

		if ( $wgOut->getTitle()->equals( SpecialPage::getTitleFor( 'BitIdConvert' ) ) ) {

			return wfMessage( 'bitid-selection-button-convert' )->text();

		} else {

			if ( $wgUser->isAllowed( 'bitid-create-account-with-bitid' )
				&& !$wgUser->isAllowed( 'bitid-login-with-bitid' ) ) {
				return wfMessage( 'bitid-selection-button-create-account' )->text();
			}

			if ( !$wgUser->isAllowed( 'bitid-create-account-with-bitid' )
				&& $wgUser->isAllowed( 'bitid-login-with-bitid' ) ) {
				return wfMessage( 'bitid-selection-button-login' )->text();
			}

			return wfMessage( 'bitid-selection-button-login-or-create-account' )->text();

		}


	}
	
	/**
	 * @param $user User
	 * @param $url string
	 */
	public static function addUserAddress( $user, $address ) {
		$dbw = wfGetDB( DB_MASTER );

		$dbw->insert(
			'bitid_users',
			array(
				'uoi_user' => $user->getId(),
				'uoi_bitid' => $address,
				'uoi_user_registration' => $dbw->timestamp()
			),
			__METHOD__
		);
	}
	
	/**
	 * @param $user User
	 * @param $url string
	 * @return bool
	 */
	public static function removeUserAddress( $user, $url ) {
		$dbw = wfGetDB( DB_MASTER );

		$dbw->delete(
			'bitid_users',
			array(
				'uoi_user' => $user->getId(),
				'uoi_bitid' => $url
			),
			__METHOD__
		);

		return (bool)$dbw->affectedRows();
	}
}