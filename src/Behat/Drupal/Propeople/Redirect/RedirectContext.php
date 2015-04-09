<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Redirect;

// Helpers.
use Behat\Gherkin\Node\TableNode;
use Behat\Mink as Mink;

class RedirectContext extends RawRedirectContext
{
    private $waitForRedirect;
    private $startUrl = '';

    /**
     * @param int $wait_for_redirect
     *   The time to wait for page opening.
     */
    public function __construct($wait_for_redirect = null)
    {
        $this->waitForRedirect = $wait_for_redirect ?: 15;
    }

    /**
     * @BeforeStep @redirect
     */
    public function beforeShouldBeRedirected()
    {
        $this->startUrl = $this->unTrailingSlashIt($this->getSession()->getCurrentUrl());
    }

    /**
     * @param string $page
     *   Expected page URL.
     *
     * @throws \Exception
     * @throws \OverflowException
     *
     * @redirect
     * @Then /^(?:|I )should be redirected(?:| on "([^"]*)")$/
     */
    public function shouldBeRedirected($page = null)
    {
        $seconds = 0;

        while ($this->waitForRedirect >= $seconds++) {
            $url = $this->unTrailingSlashIt($this->getSession()->getCurrentUrl());
            sleep(1);

            if ($url != $this->startUrl) {
                if (isset($page)) {
                    $page = $this->unTrailingSlashIt($page);

                    if (!in_array($url, [$page, $this->getMinkParameter('base_url') . "/$page"])) {
                        continue;
                    }
                }

                return;
            }
        }

        throw new \OverflowException('The waiting time is over.');
    }

    /**
     * @param $not
     * @param TableNode $paths
     *
     * @throws \Exception
     *
     * @Given /^user should(| not) have an access to the following pages:$/
     */
    public function checkUserAccessToPages($not, TableNode $paths)
    {
        $result = [];
        $code = $not ? 403 : 200;

        // Use "GoutteDriver" to have an ability to check answer code.
        $driver = new Mink\Driver\GoutteDriver();
        $session = new Mink\Session($driver);
        $session->start();

        foreach (array_keys($paths->getRowsHash()) as $path) {
            $path = trim($path, '/');
            $session->visit($this->locatePath($path));

            if ($session->getStatusCode() !== $code) {
                $result[] = $path;
            }
        }

        if (!empty($result)) {
            throw new \Exception(sprintf(
                'The following paths: "%s" are %s accessible!',
                implode(', ', $result),
                $not ? '' : 'not'
            ));
        }
    }
}
