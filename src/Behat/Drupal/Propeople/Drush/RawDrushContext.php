<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Drush;

// Contexts.
use Behat\Drupal\Propeople\RawPropeopleContext;

class RawDrushContext extends RawPropeopleContext
{
    /**
     * @param string $username
     *
     * @return string
     */
    public function getOneTimeLoginLink($username)
    {
        return $this->getDrushDriver()->drush('uli', [
          $username,
          '--browser=0',
          "--uri={$this->getBaseUrl()}",
        ]);
    }
}
