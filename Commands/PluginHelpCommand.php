<?php

namespace Terminus\Commands;

use Terminus\Commands\TerminusCommand;
use Terminus\Exceptions\TerminusException;
use Terminus\Utils;

/**
 * Manage Terminus plugins
 *
 * @command plugin
 */
class PluginHelpCommand extends TerminusCommand {

  /**
   * Advanced help for plugins
   *
   * @param array $options Options to construct the command object
   * @return PluginHelpCommand
   */
  public function __construct(array $options = []) {
    parent::__construct($options);
  }

  /**
   * Advanced help for plugins
   *
   * @subcommand help
   *
   * [--browse]
   * : Open help in the default browser
   *
   * [--print]
   * : Display help in the terminal window
   *
   * @param array $args Array of plugin names
   * @param array $assoc_args Array of display options
   */
  public function help($args, $assoc_args) {
    if (empty($args)) {
      $message = "Usage: terminus plugin help plugin-name-1 [plugin-name-2] ... [--browse | --print]";
      $this->failure($message);
    }

    if (empty($assoc_args)) {
      $assoc_args = array('browse' => 1);
    }
    $keys = array_keys($assoc_args);
    $assoc_arg = array_pop($keys);
    if (!in_array($assoc_arg, array('browse', 'print'))) {
      $message = "Invalid associative argument --$assoc_arg.";
      $this->failure($message);
    }

    switch (php_uname('s')) {
      case 'Linux':
        $cmd = 'xdg-open';
          break;
      case 'Darwin':
        $cmd = 'open';
          break;
      case 'Windows NT':
        $cmd = 'start';
          break;
      default:
        $this->failure('Operating system not supported.');
    }
    $plugins_dir = $this->getPluginDir();
    exec("ls \"$plugins_dir\"", $output);
    if (empty($output[0])) {
      $message = "No plugins installed.";
      $this->log()->notice($message);
    } else {
      $windows = Utils\isWindows();
      if ($windows) {
        $slash = '\\\\';
      } else {
        $slash = '/';
      }
      foreach ($args as $plugin) {
        $plugin = $plugins_dir . $plugin;
        $git_dir = $plugin . $slash . '.git';
        if (is_dir("$plugin") && is_dir("$git_dir")) {
          $readme = '';
          exec("cd \"$plugin\" && git remote -v", $output);
          foreach ($output as $line) {
            $parts = explode("\t", $line);
            if (isset($parts[1])) {
              $repo = explode(' ', $parts[1]);
              $repo = $repo[0];
              if (substr($repo, -4) == '.git') {
                $repo = substr($repo, 0, strlen($repo) - 4);
              }
              $readme = $repo . '/blob/master/README.md';
              break;
            }
          }
          if ($this->isValidUrl($readme)) {
            if ($assoc_arg == 'browse') {
              $command = sprintf('%s %s', $cmd, $readme);
              exec($command);
            } else {
              $message = array();
              if ($content = @file_get_contents($readme)) {
                $content = str_replace("\n", '<br />', $content);
                $content = str_replace('`', '', $content);
                preg_match('`<article class="markdown-body entry-content" itemprop="text">(.*)</article>`', $content, $matches);
                if (isset($matches[1])) {
                  $lines = explode('<br />', $matches[1]);
                  foreach ($lines as $l => $line) {
                    $newline = strip_tags($line);
                    $lines[$l] = html_entity_decode($newline);
                  }
                  print_r(implode("\n", $lines));
                } else {
                  $message = "Unable to display $readme.";
                  $this->log()->error($message);
                }
              } else {
                $message = "Unable to display $readme.";
                $this->log()->error($message);
              }
            }
          } else {
            $message = "Unable to locate $readme.";
            $this->log()->error($message);
          }
        } else {
          $message = "Unable to locate $plugin plugin.";
          $this->log()->error($message);
        }
      }
    }
  }

  /**
   * Get the plugin directory
   *
   * @param string $arg Plugin name
   * @return string Plugin directory
   */
  private function getPluginDir($arg = '') {
    $plugins_dir = getenv('TERMINUS_PLUGINS_DIR');
    $windows = Utils\isWindows();
    if (!$plugins_dir) {
      // Determine the correct $plugins_dir based on the operating system
      $home = getenv('HOME');
      if ($windows) {
        $system = '';
        if (getenv('MSYSTEM') !== null) {
          $system = strtoupper(substr(getenv('MSYSTEM'), 0, 4));
        }
        if ($system != 'MING') {
          $home = getenv('HOMEPATH');
        }
        $home = str_replace('\\', '\\\\', $home);
        $plugins_dir = $home . '\\\\terminus\\\\plugins\\\\';
      } else {
        $plugins_dir = $home . '/terminus/plugins/';
      }
    } else {
      // Make sure the proper trailing slash(es) exist
      if ($windows) {
        $slash = '\\\\';
        $chars = 2;
      } else {
        $slash = '/';
        $chars = 1;
      }
      if (substr("$plugins_dir", -$chars) != $slash) {
        $plugins_dir .= $slash;
      }
    }
    // Create the directory if it doesn't already exist
    if (!is_dir("$plugins_dir")) {
      mkdir("$plugins_dir", 0755, true);
    }
    return $plugins_dir . $arg;
  }

  /**
   * Check whether a plugin is valid
   *
   * @param string Repository URL
   * @param string Plugin name
   * @return string Plugin title, if found
   */
  private function isValidPlugin($repository, $plugin) {
    // Make sure the URL is valid
    $is_url = (filter_var($repository, FILTER_VALIDATE_URL) !== false);
    if (!$is_url) {
      return '';
    }
    // Make sure a subpath exists
    $parts = parse_url($repository);
    if (!isset($parts['path']) || ($parts['path'] == '/')) {
      return '';
    }
    // Search for a plugin title
    $plugin_data = @file_get_contents($repository . '/' . $plugin);
    if (!empty($plugin_data)) {
      preg_match('|<title>(.*)</title>|', $plugin_data, $match);
      if (isset($match[1])) {
        $title = $match[1];
        if (stripos($title, 'terminus') && stripos($title, 'plugin')) {
          return $title;
        }
        return '';
      }
      return '';
    }
    return '';
  }

  /**
   * Check whether a URL is valid
   *
   * @param string $url The URL to check
   * @return bool True if the URL returns a 200 status
   */
  private function isValidUrl($url = '') {
    if (!$url) {
      return false;
    }
    $headers = @get_headers($url);
    if (!isset($headers[0])) {
      return false;
    }
    return (strpos($headers[0], '200') !== false);
  }

}
