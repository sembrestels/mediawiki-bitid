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

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'BitId',
	'version' => 0.1,
	'author' => 'David Llop',
	'url' => 'https://www.mediawiki.org/wiki/Extension:BitId',
	'descriptionmsg' => 'bitid-desc'
);

$wgBitIdLoginAnywhere = true;

$wgHooks['BeforePageDisplay'][] = 'efAddBitIdModule';
$wgHooks['UserLoginForm'][] = 'efAddBitIdLogin';
$wgHooks['PersonalUrls'][] = 'efAddBitIdLinks';
//$wgAutoloadClasses['ApiBitId'] = __DIR__ . '/ApiBitId.php';
//$wgAPIModules['bitid'] = 'ApiBitId';
$wgExtensionMessagesFiles['BitId'] = __DIR__ . '/BitId.i18n.php';
$wgAutoloadClasses['SpecialBitId'] = __DIR__ . '/SpecialBitId.php';
$wgSpecialPages['BitId'] = 'SpecialBitId';

$wgResourceModules['ext.bitid'] = array(
	'scripts' => array('js/bitid_hooks.js'),
	'styles' => array(),
	'messages' => array(),
	'dependencies' => array( 'mediawiki.api', 'mediawiki.Title' ),
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'BitId'
);

$wgHooks['LoadExtensionSchemaUpdates'][] = 'efCreateSqlTable';

/**
 * Add the BitID module to the OutputPage.
 *
 * @param &$out OutputPage object
 * @param &$skin Skin object
 */
function efAddBitIdModule( OutputPage &$out, Skin &$skin ) {
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
 * Add the BitID login button and necessary JavaScript modules
 * to the login form.
 *
 * @param $template QuickTemplate
 */
function efAddBitIdLogin( $template ) {
	$context = RequestContext::getMain();
	$out = $context->getOutput();
	$out->addModules( 'ext.bitid' );

	$label = wfMessage( 'bitid' )->escaped();
	$href = Title::newFromText('Special:BitId')->getFullURL();
	$bitidLink = Html::element( 'a', array('href' => $href, 'class' => 'mw-ui-button mw-ui-primary'), $label);
	$template->set( 'header', $bitidLink );
	return true;
}

/**
 * Add bitid login button to personal URLs.
 *
 * @param $personal_urls Array of personal URLs
 * @param $title Title currently being viewed
 */
function efAddBitIdLinks( array &$personal_urls, Title $title ) {
	global $wgBitIdLoginAnywhere;
	if( $wgBitIdLoginAnywhere && !isset( $personal_urls['logout'] ) ) {
		$context = RequestContext::getMain();
		$out = $context->getOutput();
		$out->addModules( 'ext.bitid' );

		$personal_urls['bitidlogin'] = array(
			'text' => wfMessage( 'bitid' ),
			'href' => Title::newFromText('Special:BitId')->getFullURL(),
			'active' => $title->isSpecial( 'Userlogin' )
		);
	}
	return true;
}

function efCreateSqlTable( DatabaseUpdater $updater ) {
	$updater->addExtensionTable( 'nonces',
		dirname( __FILE__ ) . '/scheme/nonces.sql', true );
	return true;
}