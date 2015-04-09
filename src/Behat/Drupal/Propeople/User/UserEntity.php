<?php
/**
 * @author Sergey Bondarenko, <broken@propeople.com.ua>
 */
namespace Behat\Drupal\Propeople\User;

trait UserEntity
{
    /**
     * Load the entity wrapper for a user.
     *
     * @param int $uid
     *   User ID.
     *
     * @return \EntityDrupalWrapper
     */
    public static function entityWrapper($uid)
    {
        return entity_metadata_wrapper('user', user_load($uid));
    }

    /**
     * @param string $group
     *   The name of a group. Can be "locators" or "required" only.
     *
     * @return array
     *   If group is "required" that an array with labels of all required fields
     *   will be returned, if "locators" - will return an array with field names,
     *   keyed by label or machine name.
     */
    public static function getUserEntityFields($group = '')
    {
        static $results = [];

        if (empty($results)) {
            // The fields in "locators" array stored by machine name of a field and duplicates by field label.
            foreach (field_info_instances('user', 'user') as $field_name => $definition) {
                $results['locators'][$definition['label']] = $results['locators'][$field_name] = $field_name;

                if ($definition['required']) {
                    $results['required'][$field_name] = $definition['label'];
                }
            }
        }

        return empty($results[$group]) ? $results : $results[$group];
    }

    /**
     * @param string $field_name
     *   Machine name or label of a field.
     *
     * @return array
     *   Drupal field definition.
     */
    public static function getUserEntityFieldInfo($field_name)
    {
        $locators = self::getUserEntityFields('locators');
        $field = [];

        // Try to find a field by label or machine name.
        if (isset($locators[$field_name])) {
            $field = field_info_field($locators[$field_name]);
        }

        // This check is necessary for always return an array only.
        return empty($field) ? [] : $field;
    }
}
