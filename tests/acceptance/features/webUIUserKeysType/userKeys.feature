@webUI @skipOnEncryptionType:masterkey @skipOnStorage:ceph
Feature: encrypt files using user specific keys
  As an admin
  I want to be able to encrypt user files using user specific keys
  So that users can use specific keys for encrypting their files

  Background:
    Given app "encryption" has been enabled
    And these users have been created with skeleton files but not initialized:
      | username       |
      | brand-new-user |
    And encryption has been enabled

  @issue-33
  Scenario: encrypt all files using user keys based encryption via the occ command
    Given these users have been initialized:
      | username       |
      | brand-new-user |
    When the administrator sets the encryption type to "user-keys" using the occ command
    And the administrator encrypts all data using the occ command
    Then the command should have failed with exit code 1
    #Then the command should have been successful
    #And file "textfile0.txt" of user "brand-new-user" should be encrypted

  Scenario: file gets encrypted if the encryption is enabled and administrator has not encrypted all files but the user has logged in
    When the administrator sets the encryption type to "user-keys" using the occ command
    And user "brand-new-user" has logged in using the webUI
    Then file "textfile0.txt" of user "brand-new-user" should be encrypted

  Scenario: decrypt user keys based encryption of all users
    Given these users have been created with skeleton files but not initialized:
      | username         |
      | another-new-user |
    And the administrator has set the encryption type to "user-keys"
    And the administrator has browsed to the admin encryption settings page
    And the administrator has enabled recovery key and set the recovery key to "recoverypass"
    And the administrator has browsed to the personal encryption settings page
    And the administrator has enabled password recovery
    And the administrator has logged out of the webUI
    And user "brand-new-user" has logged in using the webUI
    And the user has browsed to the personal encryption settings page
    And the user has enabled password recovery
    And the user has logged out of the webUI
    And user "another-new-user" has logged in using the webUI
    And the user has browsed to the personal encryption settings page
    And the user has enabled password recovery
    When the administrator decrypts user keys based encryption with recovery key "recoverypass" using the occ command
    Then file "textfile0.txt" of user "brand-new-user" should not be encrypted
    And file "textfile0.txt" of user "another-new-user" should not be encrypted