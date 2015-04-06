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
            /* @var \EntityDrupalWrapper $entity */
            $entity = entity_metadata_wrapper('user', user_load($user->uid));
            $locators = array();
            $required = array();

            // The fields in "$locators" array stored by machine name of a field
            // and duplicates by field label.
            foreach (field_info_instances('user', 'user') as $field_name => $definition) {
                $locators[$field_name] = $definition;
                $locators[$definition['label']] = $definition;

                if ($definition['required']) {
                    $required[$field_name] = $definition['label'];
                }
            }

            // Fill fields. Field can be found by name or label.
            foreach ($fields->getRowsHash() as $field_name => $value) {
                if (!isset($locators[$field_name])) {
                    continue;
                }

                $field_name = $locators[$field_name]['field_name'];
                $field_info = field_info_field($field_name);

                if ($field_info['type'] == 'taxonomy_term_reference') {
                    $settings = reset($field_info['settings']['allowed_values']);
                    $taxonomy = taxonomy_vocabulary_machine_name_load($settings['vocabulary']);
                    $term_exist = false;

                    // Find taxonomy term by it name.
                    foreach (taxonomy_get_tree($taxonomy->vid, $settings['parent']) as $term) {
                        if ($term->name == $value) {
                            $value = $term->tid;
                            $term_exist = true;
                            break;
                        }
                    }

                    if (!$term_exist) {
                        throw new \InvalidArgumentException(sprintf(
                            'Taxonomy term "%s" does no exist in "%s" vocabulary',
                            $value,
                            $taxonomy->name
                        ));
                    }
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
     *
     * @throws \Exception
     *
     * @Given /^(?:|I )am logged in with credentials:/
     *
     * @user
     */
    public function loginWithCredentials(TableNode $credentials)
    {
        $data = $credentials->getRowsHash();
        $this->fillLoginForm($data);

        if (!$this->isLoggedIn()) {
            throw new \Exception(sprintf(
                'Unable to login with "%s" and "%s" credentials',
                $data['name'],
                $data['pass']
            ));
        }
    }
}
