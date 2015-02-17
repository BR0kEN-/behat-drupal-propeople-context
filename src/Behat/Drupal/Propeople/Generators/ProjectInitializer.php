<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\Generators;

class ProjectInitializer
{
    public static function composerPostInstallCommand()
    {
        var_dump(func_get_args());
    }
}
