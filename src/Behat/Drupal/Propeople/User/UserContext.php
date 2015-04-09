<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\User;

// Helpers.
use Behat\Gherkin\Node\TableNode;

class UserContext extends RawUserContext
{
    /**
     * @param string $roles
     *   User roles, separated by comma.
     * @param TableNode $fields
     *   | Field machine name or label | Value |
     *
     * @throws \EntityMetadataWrapperException
     *   When user object cannot be saved.
     * @throws \Exception
     *   When required fields are not filled.
     *
     * @example
     * Then I am logged in as a user with "CRM Client" role and filled fields
     *   | Full name                | Sergey Bondarenko |
     *   | Position                 | Developer         |
     *   | field_crm_user_company   | Propeople         |
     *
     * @Given /^(?:|I am )logged in as a user with "([^"]*)" role(?:|s)(?:| and filled fields:)$/
     *
     * @user @api
     */
    public function createDrupalUser($roles, TableNode $fields = null)
    {
        $user = $this->createUserWithRoles($roles);

        if ($fields) {
            $entity = self::entityWrapper($user->uid);
            $required = self::getUserEntityFields('required');

            // Fill fields. Field can be found by name or label.
            foreach ($fields->getRowsHash() as $field_name => $value) {
                $field_info = self::getUserEntityFieldInfo($field_name);

                if (empty($field_info)) {
                    continue;
                }

                $field_name = $field_info['field_name'];

                switch ($field_info['type']) {
                    case 'taxonomy_term_reference':
                        // Try to find taxonomy term by it name.
                        $terms = taxonomy_term_load_multiple([], ['name' => $value]);

                        if (!$terms) {
                            throw new \InvalidArgumentException(sprintf('Taxonomy term "%s" does no exist.', $value));
                        }

                        $value = key($terms);
                        break;
                }

                $entity->{$field_name}->set($value);

                // Remove field from $required if it was there and filled.
                if (isset($required[$field_name])) {
                    unset($required[$field_name]);
                }
            }

            // Throw an exception when one of required fields was not filled.
            if (!empty($required)) {
                throw new \Exception(sprintf(
                    'The following fields "%s" are required and has not filled.',
                    implode('", "', $required)
                ));
            }

            $entity->save();
        }

        $this->loginUser();
    }

    /**
     * @param TableNode $credentials
     *   | username | BR0kEN |
     *   | password | p4sswd |
     *
     * @throws \Exception
     *
     * @Given /^(?:|I )am logged in with credentials:/
     *
     * @user
     */
    public function loginWithCredentials(TableNode $credentials)
    {
        $this->fillLoginForm($credentials->getRowsHash());
    }
}
