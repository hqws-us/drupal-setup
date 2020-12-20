<?php

namespace DrupalProject\composer;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class DrupalSettings.
 */
class DrupalSettings {

  /**
   * Get Project root.
   *
   * @param $project_root
   *
   * @return string
   */
  protected static function getDrupalRoot($project_root) {
    return $project_root . '/web';
  }

  /**
   * Create Drupal settings file.
   */
  public static function create(Event $event) {
    $root = static::getDrupalRoot(getcwd());
    $defaults = [
      'db_name' => 'default',
      'db_user' => 'root',
      'db_pass' => 'root',
      'db_host' => 'db',
      'driver' => 'mysql',
      'db_port' => '',
      'db_prefix' => '',
      'settings_path' => $root . '/sites/default/settings.local.php',
    ];

    $options = self::extractEnvironmentVariables(array_keys($defaults))
      + self::extractCliOptions($event->getArguments(), array_keys($defaults))
      + $defaults;

    $fs = new Filesystem();
    if (!$fs->exists($options['settings_path'])) {
      $fs->dumpFile($options['settings_path'], self::getDefaultDrupalSettingsContent($options));
      $event->getIO()->write(sprintf('Created file %s', $options['settings_path']));
    }
    else {
      $event->getIO()->write('Skipping creation of Drupal settings file - file already exists');
    }
  }

  /**
   * Deelet Drupal settings file.
   */
  public static function delete(Event $event) {
    $root = static::getDrupalRoot(getcwd());

    $defaults = [
      'settings_path' => $root . '/sites/default/settings.local.php',
    ];

    $options = self::extractEnvironmentVariables(array_keys($defaults))
      + self::extractCliOptions($event->getArguments(), array_keys($defaults))
      + $defaults;

    $fs = new Filesystem();
    if (!$fs->exists($options['settings_path'])) {
      $event->getIO()->write('Skipping deletion of Drupal settings file - file does not exists');
    }
    else {
      $fs->remove($options['settings_path']);
      $event->getIO()->write(sprintf('Deleted file %s', $options['settings_path']));
    }
  }

  /**
   * Return content for default Drupal settings file.
   */
  protected static function getDefaultDrupalSettingsContent($options) {
    // Constant salt for local development.
    $hash_salt = md5('settings');
    return <<<FILE
<?php

/**
 * @file
 * Generated Docksal settings.
 *
 * Do not modify this file if you need to override default settings.
 */

// Local DB settings.
\$databases = [
  'default' =>
    [
      'default' =>
        [
          'database' => '${options['db_name']}',
          'username' => '${options['db_user']}',
          'password' => '${options['db_pass']}',
          'host' => '${options['db_host']}',
          'port' => '${options['db_port']}',
          'driver' => 'mysql',
          'prefix' => '${options['db_prefix']}',
        ],
    ],
];

\$settings['hash_salt'] = '${hash_salt}';

\$settings['trusted_host_patterns'][] = '^.+$';

\$settings['file_private_path'] = 'sites/default/files/private';
\$settings['file_public_path'] = 'sites/default/files';

// No Caches

\$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';

\$config['system.logging']['error_level'] = 'verbose';

\$config['system.performance']['css']['preprocess'] = FALSE;
\$config['system.performance']['js']['preprocess'] = FALSE;

\$config['system.performance']['css']['preprocess'] = FALSE;
\$config['system.performance']['js']['preprocess'] = FALSE;

\$config['advagg.settings']['enabled'] = FALSE;

\$settings['cache']['bins']['render'] = 'cache.backend.null';

\$settings['cache']['bins']['page'] = 'cache.backend.null';

\$settings['rebuild_access'] = TRUE;

\$settings['skip_permissions_hardening'] = TRUE;

\$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

FILE;
  }

  /**
   * Extract options from environment variables.
   *
   * @param bool|array $allowed
   *   Array of allowed options.
   *
   * @return array
   *   Array of extracted options.
   */
  protected static function extractEnvironmentVariables(array $allowed) {
    $options = [];

    foreach ($allowed as $name) {
      $value = getenv(strtoupper($name));
      if ($value !== FALSE) {
        $options[$name] = $value;
      }
    }

    return $options;
  }

  /**
   * Extract options from CLI arguments.
   *
   * @param array $arguments
   *   Array of arguments.
   * @param bool|array $allowed
   *   Array of allowed options.
   *
   * @return array
   *   Array of extracted options.
   */
  protected static function extractCliOptions(array $arguments, array $allowed) {
    $options = [];

    foreach ($arguments as $argument) {
      if (strpos($argument, '--') === 0) {
        list($name, $value) = explode('=', $argument);
        $name = substr($name, strlen('--'));
        $options[$name] = $value;
        if (array_key_exists($name, $allowed) && !is_null($value)) {
          $options[$name] = $value;
        }
      }
    }

    return $options;
  }

}
