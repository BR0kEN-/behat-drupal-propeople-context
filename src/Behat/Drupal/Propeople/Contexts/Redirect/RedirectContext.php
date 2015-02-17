<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Contexts\Redirect;

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
     * @BeforeStep @redirects
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
     * @redirects
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

                    if (!in_array($url, array($page, $this->getMinkParameter('base_url') . "/$page"))) {
                        continue;
                    }
                }

                return;
            }
        }

        throw new \OverflowException('The waiting time is over.');
    }
}
