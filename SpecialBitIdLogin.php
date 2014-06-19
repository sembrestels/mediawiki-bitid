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
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		$headers = getallheaders();
		
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

		// ALL THOSE VARIABLES HAVE TO BE SANITIZED !

		$signValid = $this->bitid->isMessageSignatureValidSafe($variables['address'], $variables['signature'], $variables['uri'], true);
		$nonce = $this->bitid->extractNonce(urldecode($variables['uri']));
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
				$this->login();
			}
		}
	}
	
	private function ajax() {
		// check if this nonce is logged or not
		$dbr = wfGetDB(DB_SLAVE);
		$_address = $dbr->select('bitid_nonces', array('address'), array('nonce' => $_POST['nonce']));
		foreach ($_address as $addr) {
			$address = $addr->address;
		}
		if($address!==false) {
			// Create session so the user could log in
			$this->login();
		}
		//return address/false to tell the VIEW it could log in now or not
		echo json_encode($address);
		exit();
	}
	
	private function login() {
		unset($_SESSION['bitid_nonce']);
	}
	
	private function save_nonce($nonce) {
		$_SESSION['bitid_nonce'] = $nonce;
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert('bitid_nonces', array('nonce'=> $nonce), __METHOD__, array('IGNORE'));
	}
}
