<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../core/class/mitsubishimelcloud.class.php';

/** Fonction exécutée automatiquement après l'installation du plugin */
function mitsubishimelcloud_install() {
  // Create the 2 neededs cron
  $cron = cron::byClassAndFunction('mitsubishimelcloud', 'SynchronizeMELCloud');
  if (!is_object($cron)) {
    $cron = new cron();
    $cron->setClass('mitsubishimelcloud');
    $cron->setFunction('SynchronizeMELCloud');
    $cron->setEnable(1);
    $cron->setDeamon(0);
    $cron->setSchedule(checkAndFixCron(mitsubishimelcloud::DEFAULT_SYNC_CRON));
    $cron->setTimeout('60');
    $cron->save();
  }
  $cron = cron::byClassAndFunction('mitsubishimelcloud', 'SynchronizeSplit');
  if (!is_object($cron)) {
    $cron = new cron();
    $cron->setClass('mitsubishimelcloud');
    $cron->setFunction('SynchronizeSplit');
    $cron->setEnable(1);
    $cron->setDeamon(0);
    $cron->setSchedule(checkAndFixCron(mitsubishimelcloud::DEFAULT_SPLIT_CRON));
    $cron->setTimeout('60');
    $cron->save();
  }

  // Add some configuration, language as French, and latest MELCloud version (when plugin was created)
  $Language = config::byKey('Language', 'mitsubishimelcloud');
  if (empty($Language)) {
    config::save('Language', '7', 'mitsubishimelcloud');
  }
  $AppVersion = config::byKey('AppVersion', 'mitsubishimelcloud');
  if (empty($AppVersion)) {
    config::save('AppVersion', '1.25.0.1', 'mitsubishimelcloud');
  }
}

/** Fonction exécutée automatiquement après la mise à jour du plugin */
function mitsubishimelcloud_update() {
  log::add('mitsubishimelcloud', 'info', '<------------ Start installation update ------------');

  log::add('mitsubishimelcloud', 'debug', 'Update complete synchronisation cron parameters to MELCloud server');
  $cron = cron::byClassAndFunction('mitsubishimelcloud', 'SynchronizeMELCloud');
  if (!is_object($cron)) {
    $cron = new cron();
    $cron->setClass('mitsubishimelcloud');
    $cron->setFunction('SynchronizeMELCloud');
    $cron->setEnable(1);
    $cron->setDeamon(0);
    $cron->setSchedule(checkAndFixCron(mitsubishimelcloud::DEFAULT_SYNC_CRON));
    $cron->setTimeout('60');
    $cron->save();
  } else {
    $cron->setSchedule(checkAndFixCron(mitsubishimelcloud::DEFAULT_SYNC_CRON));
    $cron->setTimeout('2');
    $cron->save();
  }
  
  log::add('mitsubishimelcloud', 'debug', 'Update synchronisation of splits cron parameters to MELCloud server');
  $cron = cron::byClassAndFunction('mitsubishimelcloud', 'SynchronizeSplit');
  if (!is_object($cron)) {
    $cron = new cron();
    $cron->setClass('mitsubishimelcloud');
    $cron->setFunction('SynchronizeSplit');
    $cron->setEnable(1);
    $cron->setDeamon(0);
    $cron->setSchedule(checkAndFixCron(mitsubishimelcloud::DEFAULT_SPLIT_CRON));
    $cron->setTimeout('60');
    $cron->save();
  } else {
    $cron->setTimeout('2');
    $cron->save();
  }

  $cron->stop();

  // Remove unused files
  log::add('mitsubishimelcloud', 'debug', 'Remove old files');
  if (file_exists(getRootPath()."/plugins/mitsubishimelcloud/core/template/css/mitsubishimelcloud.css")) rrmdir(getRootPath()."/plugins/mitsubishimelcloud/core/template/css/mitsubishimelcloud.css");
  if (file_exists(getRootPath()."/plugins/mitsubishimelcloud/core/template/dashboard/cmd.action.slider.FanSpeedMitsubishi.html")) rrmdir(getRootPath()."/plugins/mitsubishimelcloud/core/template/dashboard/cmd.action.slider.FanSpeedMitsubishi.html");
  if (file_exists(getRootPath()."/plugins/mitsubishimelcloud/core/template/dashboard/cmd.action.slider.ModeMitsubishi.html")) rrmdir(getRootPath()."/plugins/mitsubishimelcloud/core/template/dashboard/cmd.action.slider.ModeMitsubishi.html");
  if (file_exists(getRootPath()."/plugins/mitsubishimelcloud/core/template/js/mitsubishimelcloud.js")) rrmdir(getRootPath()."/plugins/mitsubishimelcloud/core/template/js/mitsubishimelcloud.js");
  if (file_exists(getRootPath()."/plugins/mitsubishimelcloud/core/template/js")) rrmdir(getRootPath()."/plugins/mitsubishimelcloud/core/template/js");
  if (file_exists(getRootPath()."/plugins/mitsubishimelcloud/core/template/mobile/cmd.action.other.templeteTemplate.html")) rrmdir(getRootPath()."/plugins/mitsubishimelcloud/core/template/mobile/cmd.action.other.templeteTemplate.html");

  //Update MELCloud app version
  log::add('mitsubishimelcloud', 'debug', 'Update MELCloud app version');
  $AppVersion = config::byKey('AppVersion', 'mitsubishimelcloud');
  config::save('AppVersion', '1.26.0.0', 'mitsubishimelcloud');

  // Syncrhonise MELCLoud
  log::add('mitsubishimelcloud', 'debug', 'Syncrhonization to update th plugin');
  mitsubishimelcloud::SynchronizeMELCloud('PluginUpdate');

  log::add('mitsubishimelcloud', 'info', '------------ Complete installation update ------------>');
}

/** Fonction exécutée automatiquement après la suppression du plugin */
function mitsubishimelcloud_remove() {
  $cron = cron::byClassAndFunction('mitsubishimelcloud', 'SynchronizeMELCloud');
  if (is_object($cron)) {
    $cron->remove();
  }

  $cron = cron::byClassAndFunction('mitsubishimelcloud', 'SynchronizeSplit');
  if (is_object($cron)) {
    $cron->remove();
  }
}

?>