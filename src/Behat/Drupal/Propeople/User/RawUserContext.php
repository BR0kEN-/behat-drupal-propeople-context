<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\User;

// Contexts.
use Behat\Drupal\Propeople\RawPropeopleContext;

class RawUserContext extends RawPropeopleContext
{
    use UserEntity;

    /**
     * @param string $roles
     *   Necessary user roles separated by comma.
     *
     * @return \stdClass
     */
    public function createUserWithRoles($roles)
    {
        $user = $this->createTestUser();

        foreach (array_map('trim', explode(',', $roles)) as $role) {
            if (!in_array(strtolower($role), ['authenticated', 'authenticated user'])) {
                // Only add roles other than 'authenticated user'.
                $this->getDriver()->userAddRole($user, $role);
            }
        }

        return $user;
    }

    /**
     * Login existing user.
     *
     * @throws \Exception
     *   When current user is unknown.
     */
    public function loginUser()
    {
        if ($this->isLoggedIn()) {
            $this->logout();
        }

        if (!$this->user) {
            throw new \Exception('Tried to login without a user.');
        }

        $this->fillLoginForm([
            'username' => $this->user->name,
            'password' => $this->user->pass,
        ]);
    }

    /**
     * @param array $props
     *   An array with two keys: "username" and "password". Both of
     *   them are required.
     * @param string $message
     *   An error message, that will be thrown when user cannot be authenticated.
     *
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     *   When one of a fields are not found.
     * @throws \Exception
     *   When login process failed.
     * @throws \WebDriver\Exception\NoSuchElement
     *   When log in button cannot be found.
     */
    public function fillLoginForm(array $props, $message = '')
    {
        $session = $this->getSession();
        $session->visit($this->locatePath('/user/login'));
        $page = $session->getPage();

        foreach (['username', 'password'] as $prop) {
            $page->fillField($this->getDrupalText($prop . '_field'), $props[$prop]);
        }

        $selector = $this->getDrupalText('log_in');
        $submit = $page->findButton($selector);

        $this->throwNoSuchElementException($selector, $submit);
        $submit->click();

        if (!$this->isLoggedIn()) {
            if (empty($message)) {
                $message = sprintf(
                    'Failed to login as a user "%s" with password "%s".',
                    $props['username'],
                    $props['password']
                );
            }

            throw new \Exception($message);
        }
    }

    public function isLoggedIn()
    {
        $session = $this->getSession();
        // We need to visit any page to start session, otherwise the next exception will
        // be thrown: "Unable to access the response content before visiting a page".
        $session->visit($this->locatePath('/'));
        $cookie = $session->getCookie(session_name());

        if ($cookie !== null) {
            return true;
        }

        $body = $session->getPage()->find('css', 'body');

        return $body && in_array('logged-in', explode(' ', $body->getAttribute('class')));
    }

    /**
     * @param array $data
     *   Additional data for user account.
     *
     * @return \stdClass
     */
    public function createTestUser(array $data = [])
    {
        $random = $this->getRandom();
        $username = $random->name(8);
        $user = $data + [
            'name' => $username,
            'pass' => $random->name(16),
            'mail' => "$username@example.com",
        ];

        $user = (object) $user;
        $this->userCreate($user);

        return $user;
    }
}
