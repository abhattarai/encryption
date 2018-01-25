<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */



namespace OCA\Encryption\Tests\Hooks;


use OCA\Encryption\Crypto\Crypt;
use OCP\ISession;
use OCA\Encryption\Hooks\UserHooks;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * Class UserHooksTest
 *
 * @group DB
 * @package OCA\Encryption\Tests\Hooks
 */
class UserHooksTest extends TestCase {
	use UserTrait;
	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	private $utilMock;
	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	private $recoveryMock;
	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	private $sessionMock;
	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	private $keyManagerMock;
	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	private $userManagerMock;

	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	private $userSetupMock;
	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	private $userSessionMock;
	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	private $cryptMock;
	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	private $loggerMock;
	/**
	 * @var UserHooks
	 */
	private $instance;

	/** @var  EventDispatcher */
	private $eventDispatcher;

	private $params;

	public function testLogin() {
		$this->userSetupMock->expects($this->once())
			->method('setupUser')
			->willReturnOnConsecutiveCalls(true, false);

		$this->keyManagerMock->expects($this->once())
			->method('init')
			->with('testUser', 'password');

		$this->assertNull($this->instance->login($this->params));
	}

	public function testLoginDispatcherEvent() {
		$this->keyManagerMock->expects($this->any())
			->method('userHasKeys')
			->willReturn(true);

		$this->userSetupMock->expects($this->any())
			->method('setupUser')
			->willReturn(true);

		$this->keyManagerMock->expects($this->once())
			->method('init')
			->with('testUser', 'password');

		$this->instance->addHooks();
		\OC::$server->getEventDispatcher()->dispatch('user.beforelogin', $this->params);
		$this->assertTrue(true);
	}

	public function testLogout() {
		$this->sessionMock->expects($this->once())
			->method('clear');
		$this->instance->logout();
		$this->assertTrue(true);
	}

	public function testLogoutDispatcherEvent() {
		$this->sessionMock->expects($this->once())
			->method('clear');
		$this->instance->addHooks();
		\OC::$server->getEventDispatcher()->dispatch('user.beforelogout', new GenericEvent(null, []));
		$this->assertTrue(true);
	}

	public function testPostCreateUser() {
		$this->userSetupMock->expects($this->once())
			->method('setupUser');

		$this->instance->postCreateUser($this->params);
		$this->assertTrue(true);
	}

	public function testPostCreateUserDispatcherEvent() {
		$this->userSetupMock->expects($this->once())
			->method('setupUser')
			->willReturn(true);
		$this->instance->addHooks();
		\OC::$server->getEventDispatcher()->dispatch('user.aftercreateuser', $this->params);
		$this->assertTrue(true);
	}

	public function testPostDeleteUser() {
		$this->keyManagerMock->expects($this->once())
			->method('deletePublicKey')
			->with('testUser');

		$this->instance->postDeleteUser($this->params);
		$this->assertTrue(true);
	}

	public function testPostDeleteUserDispatcherEvent() {
		$this->keyManagerMock->expects($this->once())
			->method('deletePublicKey')
			->with('testUser');
		$this->instance->addHooks();
		\OC::$server->getEventDispatcher()->dispatch('user.afterdelete', $this->params);
		$this->assertTrue(true);
	}

	/**
	 * @dataProvider dataTestPreSetPassphrase
	 */
	public function testPreSetPassphrase($canChange) {

		/** @var UserHooks | \PHPUnit_Framework_MockObject_MockObject  $instance */
		$instance = $this->getMockBuilder('OCA\Encryption\Hooks\UserHooks')
			->setConstructorArgs(
				[
					$this->keyManagerMock,
					$this->userManagerMock,
					$this->loggerMock,
					$this->userSetupMock,
					$this->userSessionMock,
					$this->utilMock,
					$this->sessionMock,
					$this->cryptMock,
					$this->recoveryMock,
					$this->eventDispatcher
				]
			)
			->setMethods(['setPassphrase'])
			->getMock();

		$userMock = $this->createMock('OCP\IUser');

		$this->userManagerMock->expects($this->once())
			->method('get')
			->with($this->params['uid'])
			->willReturn($userMock);
		$userMock->expects($this->once())
			->method('canChangePassword')
			->willReturn($canChange);

		if ($canChange) {
			// in this case the password will be changed in the post hook
			$instance->expects($this->never())->method('setPassphrase');
		} else {
			// if user can't change the password we update the encryption
			// key password already in the pre hook
			$instance->expects($this->once())
				->method('setPassphrase')
				->with($this->params);
		}

		$instance->preSetPassphrase($this->params);
	}

	/**
	 * @dataProvider dataTestPreSetPassphrase
	 * @param $canChange
	 */
	public function testPreSetPassphraseDispatcherEvent($canChange) {

		/** @var UserHooks | \PHPUnit_Framework_MockObject_MockObject  $instance */
		$instance = $this->getMockBuilder('OCA\Encryption\Hooks\UserHooks')
			->setConstructorArgs(
				[
					$this->keyManagerMock,
					$this->userManagerMock,
					$this->loggerMock,
					$this->userSetupMock,
					$this->userSessionMock,
					$this->utilMock,
					$this->sessionMock,
					$this->cryptMock,
					$this->recoveryMock,
					$this->eventDispatcher
				]
			)
			->setMethods(['setPassphrase'])
			->getMock();

		$userMock = $this->createMock('OCP\IUser');

		$this->userManagerMock->expects($this->once())
			->method('get')
			->with($this->params['uid'])
			->willReturn($userMock);
		$userMock->expects($this->once())
			->method('canChangePassword')
			->willReturn($canChange);

		if ($canChange) {
			// in this case the password will be changed in the post hook
			$instance->expects($this->never())->method('setPassphrase');
		} else {
			// if user can't change the password we update the encryption
			// key password already in the pre hook
			$instance->expects($this->once())
				->method('setPassphrase')
				->with($this->params);
		}
		$instance->addHooks();
		\OC::$server->getEventDispatcher()->dispatch('user.beforesetpassword', $this->params);
		$this->assertTrue(true);
	}

	public function dataTestPreSetPassphrase() {
		return [
			[true],
			[false]
		];
	}

	public function testSetPassphrase() {
		$this->sessionMock->expects($this->exactly(4))
			->method('getPrivateKey')
			->willReturnOnConsecutiveCalls(true, false);

		$this->cryptMock->expects($this->exactly(4))
			->method('encryptPrivateKey')
			->willReturn(true);

		$this->cryptMock->expects($this->any())
			->method('generateHeader')
			->willReturn(Crypt::HEADER_START . ':Cipher:test:' . Crypt::HEADER_END);

		$this->keyManagerMock->expects($this->exactly(4))
			->method('setPrivateKey')
			->willReturnCallback(function ($user, $key) {
				$header = substr($key, 0, strlen(Crypt::HEADER_START));
				$this->assertSame(
					Crypt::HEADER_START,
					$header, 'every encrypted file should start with a header');
			});

		$this->assertNull($this->instance->setPassphrase($this->params));
		$this->params['recoveryPassword'] = 'password';

		$this->recoveryMock->expects($this->exactly(3))
			->method('isRecoveryEnabledForUser')
			->with('testUser')
			->willReturnOnConsecutiveCalls(true, false);


		$this->instance = $this->getMockBuilder('OCA\Encryption\Hooks\UserHooks')
			->setConstructorArgs(
				[
					$this->keyManagerMock,
					$this->userManagerMock,
					$this->loggerMock,
					$this->userSetupMock,
					$this->userSessionMock,
					$this->utilMock,
					$this->sessionMock,
					$this->cryptMock,
					$this->recoveryMock,
					$this->eventDispatcher
				]
			)->setMethods(['initMountPoints'])->getMock();

		$this->instance->expects($this->exactly(3))->method('initMountPoints');

		// Test first if statement
		$this->assertNull($this->instance->setPassphrase($this->params));

		// Test Second if conditional
		$this->keyManagerMock->expects($this->exactly(2))
			->method('userHasKeys')
			->with('testUser')
			->willReturn(true);

		$this->assertNull($this->instance->setPassphrase($this->params));

		// Test third and final if condition
		$this->utilMock->expects($this->once())
			->method('userHasFiles')
			->with('testUser')
			->willReturn(false);

		$this->cryptMock->expects($this->once())
			->method('createKeyPair');

		$this->keyManagerMock->expects($this->once())
			->method('setPrivateKey');

		$this->recoveryMock->expects($this->once())
			->method('recoverUsersFiles')
			->with('password', 'testUser');

		$this->assertNull($this->instance->setPassphrase($this->params));
	}

	public function testSetPassphraseDispatcherEvent() {
		\OC::$server->getSession()->set('privateKey', "Hello");
		$this->instance->addHooks();
		$this->keyManagerMock->expects($this->any())
			->method('getPublicKey')
			->willReturn("foo");
		$this->sessionMock->expects($this->exactly(1))
			->method('getPrivateKey')
			->willReturnOnConsecutiveCalls(true, false);

		$this->cryptMock->expects($this->exactly(1))
			->method('encryptPrivateKey')
			->willReturn(true);

		$this->cryptMock->expects($this->any())
			->method('generateHeader')
			->willReturn(Crypt::HEADER_START . ':Cipher:test:' . Crypt::HEADER_END);

		$this->keyManagerMock->expects($this->exactly(1))
			->method('setPrivateKey')
			->willReturnCallback(function ($user, $key) {
				$header = substr($key, 0, strlen(Crypt::HEADER_START));
				$this->assertSame(
					Crypt::HEADER_START,
					$header, 'every encrypted file should start with a header');
			});

		\OC::$server->getEventDispatcher()->dispatch('user.aftersetpassphrase', $this->params);
	}

	public function testSetPasswordNoUser() {
		$this->sessionMock->expects($this->once())
			->method('getPrivateKey')
			->willReturn(true);

		$userSessionMock = $this->getMockBuilder('OCP\IUserSession')
			->disableOriginalConstructor()
			->getMock();

		$userSessionMock->expects($this->any())->method('getUser')->will($this->returnValue(null));

		$this->recoveryMock->expects($this->once())
			->method('isRecoveryEnabledForUser')
			->with('testUser')
			->willReturn(false);

		$userHooks = $this->getMockBuilder('OCA\Encryption\Hooks\UserHooks')
			->setConstructorArgs(
				[
					$this->keyManagerMock,
					$this->userManagerMock,
					$this->loggerMock,
					$this->userSetupMock,
					$userSessionMock,
					$this->utilMock,
					$this->sessionMock,
					$this->cryptMock,
					$this->recoveryMock,
					$this->eventDispatcher
				]
			)->setMethods(['initMountPoints'])->getMock();

		/** @var \OCA\Encryption\Hooks\UserHooks $userHooks */
		$this->assertNull($userHooks->setPassphrase($this->params));
	}

	public function testPostPasswordReset() {
		$this->keyManagerMock->expects($this->once())
			->method('deleteUserKeys')
			->with('testUser');

		$this->userSetupMock->expects($this->once())
			->method('setupUser')
			->with('testUser', 'password');

		$this->instance->postPasswordReset($this->params);
		$this->assertTrue(true);
	}

	protected function setUp() {
		parent::setUp();
		$this->createUser('testUser');
		$this->loggerMock = $this->createMock('OCP\ILogger');
		$this->keyManagerMock = $this->getMockBuilder('OCA\Encryption\KeyManager')
			->disableOriginalConstructor()
			->getMock();
		$this->userManagerMock = $this->getMockBuilder('OCP\IUserManager')
			->disableOriginalConstructor()
			->getMock();
		$this->userSetupMock = $this->getMockBuilder('OCA\Encryption\Users\Setup')
			->disableOriginalConstructor()
			->getMock();

		$this->userSessionMock = $this->getMockBuilder('OCP\IUserSession')
			->disableOriginalConstructor()
			->setMethods([
				'isLoggedIn',
				'getUID',
				'login',
				'logout',
				'setUser',
				'getUser',
				'canChangePassword'
			])
			->getMock();

		$this->userSessionMock->expects($this->any())->method('getUID')->will($this->returnValue('testUser'));

		$this->userSessionMock->expects($this->any())
			->method($this->anything())
			->will($this->returnSelf());

		$utilMock = $this->getMockBuilder('OCA\Encryption\Util')
			->disableOriginalConstructor()
			->getMock();

		$sessionMock = $this->getMockBuilder('OCA\Encryption\Session')
			->disableOriginalConstructor()
			->getMock();

		$this->cryptMock = $this->getMockBuilder('OCA\Encryption\Crypto\Crypt')
			->disableOriginalConstructor()
			->getMock();
		$recoveryMock = $this->getMockBuilder('OCA\Encryption\Recovery')
			->disableOriginalConstructor()
			->getMock();

		$this->sessionMock = $sessionMock;
		$this->recoveryMock = $recoveryMock;
		$this->utilMock = $utilMock;
		$this->utilMock->expects($this->any())->method('isMasterKeyEnabled')->willReturn(false);

		$this->eventDispatcher = \OC::$server->getEventDispatcher();

		$this->instance = $this->getMockBuilder('OCA\Encryption\Hooks\UserHooks')
			->setConstructorArgs(
				[
					$this->keyManagerMock,
					$this->userManagerMock,
					$this->loggerMock,
					$this->userSetupMock,
					$this->userSessionMock,
					$this->utilMock,
					$this->sessionMock,
					$this->cryptMock,
					$this->recoveryMock,
					$this->eventDispatcher
				]
			)->setMethods(['setupFS'])->getMock();

		$this->params = new GenericEvent(null, ['uid' => 'testUser', 'password' => 'password']);
	}

}
