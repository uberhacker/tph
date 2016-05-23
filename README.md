# Terminus Plugin Help

Terminus plugin to provide advanced help for plugins

## Installation:

Refer to the [Terminus Wiki](https://github.com/pantheon-systems/terminus/wiki/Plugins).

## Usage:
```
$ terminus plugin help plugin-name-1 [plugin-name-2] ... [--browse | --print]
```
If --browse or --print are not provided, the default is --browse.

## Examples:
```
$ terminus plugin help awesome-plugin
$ terminus plugin help awesome-plugin --browse
```
Get advanced help for the plugin awesome-plugin and open in the default browser
```
$ terminus plugin help awesome-plugin --print
```
Get advanced help for the plugin awesome-plugin and display in the terminal window
```
$ terminus plugin help
```
Get advanced help if within the plugin directory
