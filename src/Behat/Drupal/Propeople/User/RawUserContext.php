<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\User;

// Contexts.
use Behat\Drupal\Propeople\RawPropeopleContext;

class RawUserContext extends RawPropeopleContext
{
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
            if (!in_array(strtolower($role), array('authenticated', 'authenticated user'))) {
                // Only add roles other than 'authenticated user'.
                $this->getDriver()->userAddRole($user, $role);
            }
        }

        return $user;
    }

    public function loginUser()
    {
        if ($this->isLoggedIn()) {
            $this->logout();
        }

        if (!$this->user) {
            throw new \Exception('Tried to login without a user.');
        }

        $this->fillLoginForm((array) $this->user);

        if (!$this->isLoggedIn()) {
            throw new \Exception(sprintf(
                'Failed to log in as user "%s" with role "%s"',
                $this->user->name,
                $this->user->role
            ));
        }
    }

    public function fillLoginForm(array $props)
    {
        $session = $this->getSession();
        $session->visit($this->locatePath('/user/login'));
        $page = $session->getPage();

        foreach (array('username' => 'name', 'password' => 'pass') as $param => $prop) {
            $page->fillField($this->getDrupalText($param . '_field'), $props[$prop]);
        }

        $submit = $page->findButton($this->getDrupalText('log_in'));

        if (empty($submit)) {
            throw new \Exception(sprintf('No submit button at %s', $session->getCurrentUrl()));
        }

        // Log in.
        $submit->click();
    }
}
