<?php

/**
 * @file
 * Contains \DrupalProject\composer\ScriptHandler.
 */

namespace DrupalProject\composer;

use Composer\Semver\Comparator;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ScriptHandler {

  protected static function getDrupalRoot($project_root) {
    return $project_root . '/web';
  }

  public static function createRequiredFiles(Event $event) {
    $fs = new Filesystem();
    $root = static::getDrupalRoot(getcwd());

    $dirs = [
      'modules',
      'profiles',
      'themes',
    ];

    // Required for unit testing
    foreach ($dirs as $dir) {
      if (!$fs->exists($root . '/'. $dir)) {
        $fs->mkdir($root . '/'. $dir);
        $fs->touch($root . '/'. $dir . '/.gitkeep');
      }
    }

    // Prepare the settings file for installation
    if (!$fs->exists($root . '/sites/default/settings.php') and $fs->exists($root . '/sites/default/default.settings.php')) {
      $fs->copy($root . '/sites/default/default.settings.php', $root . '/sites/default/settings.php');
      $fs->chmod($root . '/sites/default/settings.php', 0666);
      $event->getIO()->write("Create a sites/default/settings.php file with chmod 0666");
    }

    // Prepare the services file for installation
    if (!$fs->exists($root . '/sites/default/services.yml') and $fs->exists($root . '/sites/default/default.services.yml')) {
      $fs->copy($root . '/sites/default/default.services.yml', $root . '/sites/default/services.yml');
      $fs->chmod($root . '/sites/default/services.yml', 0666);
      $event->getIO()->write("Create a sites/default/services.yml file with chmod 0666");
    }

    // Create the files directory with chmod 0777
    if (!$fs->exists($root . '/sites/default/files')) {
      $oldmask = umask(0);
      $fs->mkdir($root . '/sites/default/files', 0777);
      umask($oldmask);
      $event->getIO()->write("Create a sites/default/files directory with chmod 0777");
    }
  }

  /**
   * Checks if the installed version of Composer is compatible.
   *
   * Composer 1.0.0 and higher consider a `composer install` without having a
   * lock file present as equal to `composer update`. We do not ship with a lock
   * file to avoid merge conflicts downstream, meaning that if a project is
   * installed with an older version of Composer the scaffolding of Drupal will
   * not be triggered. We check this here instead of in drupal-scaffold to be
   * able to give immediate feedback to the end user, rather than failing the
   * installation after going through the lengthy process of compiling and
   * downloading the Composer dependencies.
   *
   * @see https://github.com/composer/composer/pull/5035
   */
  public static function checkComposerVersion(Event $event) {
    $composer = $event->getComposer();
    $io = $event->getIO();

    $version = $composer::VERSION;

    // The dev-channel of composer uses the git revision as version number,
    // try to the branch alias instead.
    if (preg_match('/^[0-9a-f]{40}$/i', $version)) {
      $version = $composer::BRANCH_ALIAS_VERSION;
    }

    // If Composer is installed through git we have no easy way to determine if
    // it is new enough, just display a warning.
    if ($version === '@package_version@' || $version === '@package_branch_alias_version@') {
      $io->writeError('<warning>You are running a development version of Composer. If you experience problems, please update Composer to the latest stable version.</warning>');
    }
    elseif (Comparator::lessThan($version, '1.0.0')) {
      $io->writeError('<error>Drupal-project requires Composer version 1.0.0 or higher. Please update your Composer before continuing</error>.');
      exit(1);
    }
  }

  /**
   * Remove unnecessary files.
   * Remove .git folder from modules and themes development branches.
   *
   * @see https://github.com/drupal-composer/drupal-project/issues/223#issuecomment-266417254
   */
  public static function clearRepo() {
    $root = dirname(dirname(__DIR__)) . '';
    // Git sub folders.
    exec('find ' . $root . '/drush -name \'.git\' | xargs rm -rf');
    exec('find ' . $root . '/web/libraries -name \'.git\' | xargs rm -rf');
    exec('find ' . $root . '/web/modules -name \'.git\' | xargs rm -rf');
    exec('find ' . $root . '/web/profiles -name \'.git\' | xargs rm -rf');
    exec('find ' . $root . '/vendor -name \'.git\' | xargs rm -rf');

    // Examples and build folders.
    exec('rm -f example.gitignore .eslintignore > /dev/null 2>&1');
    exec('find ' . $root . '/web/libraries -type d -iname "sample"           | xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . '/web/libraries -type d -iname "samples"          | xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . '/web/libraries -type d -iname "example"          | xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . '/web/libraries -type d -iname "examples"         | xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . '/web/libraries -type d -iname "doc"              | xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . '/web/libraries -type d -iname "docs"             | xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . '/web/libraries -type d -iname "tests"            | xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . '/web/libraries -type d -iname "node_modules"     | xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . '/web/libraries -type d -iname "bower_components" | xargs rm -rf > /dev/null 2>&1');

    // Single unnecessary file.
    exec('find ' . $root . ' -name "*.*~" | xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . ' -iname "*travis*" | xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . ' -iname "LICENSE*"| xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . ' -iname "CHANGELOG.*"| xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . ' -iname "MAINTAINERS.*"| xargs rm -rf > /dev/null 2>&1');
    exec('find ' . $root . ' -iname ".DS_Store"| xargs rm -rf > /dev/null 2>&1');
  }

  // This is called by the QuickSilver deploy hook to convert from
  // a 'lean' repository to a 'fat' repository. This should only be
  // called when using this repository as a custom upstream, and
  // updating it with `terminus composer <site>.<env> update`. This
  // is not used in the GitHub PR workflow.
  public static function prepareForPantheon()
  {
    // Get rid of any .git directories that Composer may have added.
    // n.b. Ideally, there are none of these, as removing them may
    // impair Composer's ability to update them later. However, leaving
    // them in place prevents us from pushing to Pantheon.
    $dirsToDelete = [];
    $finder = new Finder();
    foreach (
      $finder
        ->directories()
        ->in(getcwd())
        ->ignoreDotFiles(false)
        ->ignoreVCS(false)
        ->depth('> 0')
        ->name('.git')
      as $dir) {
      $dirsToDelete[] = $dir;
    }
    $fs = new Filesystem();
    $fs->remove($dirsToDelete);

    // Fix up .gitignore: remove everything above the "::: cut :::" line
    $gitignoreFile = getcwd() . '/.gitignore';
    $gitignoreContents = file_get_contents($gitignoreFile);
    $gitignoreContents = preg_replace('/.*::: cut :::*/s', '', $gitignoreContents);
    file_put_contents($gitignoreFile, $gitignoreContents);
  }
}
