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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class mitsubishimelcloud extends eqLogic {
  /*     * *************************Attributs****************************** */
  const DEFAULT_SYNC_CRON = '38 2 * * *'; //default cron for synchronisation
  const DEFAULT_SPLIT_CRON = '*/5 * * * *'; //default cron to update splits data

  /*
   * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
   * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
   public static $_widgetPossibility = array();
   */

  /*     * ***********************Methode static*************************** */
  /** Get token from MELCloud*/
  public static function GetToken() {
    $Email = config::byKey('Email', __CLASS__);
    $Password = config::byKey('Password', __CLASS__);
    $Language = config::byKey('Language', __CLASS__);
    $AppVersion = config::byKey('AppVersion', __CLASS__);

    $client = new MitsubishiMelcouldClient();
    $response = $client->MelcloudToken($Email, $Password, $Language, $AppVersion);
    
    config::save('Token', $response, __CLASS__);
  }

  /** Collect heat pump data from MELCloud app */
  public static function SynchronizeMELCloud($action = 'cron') {
    $Token = config::byKey('Token', __CLASS__);
    log::add(__CLASS__, 'info', '<--- Start of heat pump synchronization launched by : '.$action);

    if($action == 'cron' AND empty($Token)) {
      // When function launched by cron, do not launch the function if no token available
      log::add(__CLASS__, 'debug', 'No cron for heat pump synchronization as no Token saved');
    } else {
      if($Token == '' || substr($Token, 0, 11) == 'Login ERROR') {
        message::add(__CLASS__, __('Please collect the MELCloud token before creating equipment.', __FILE__));
        log::add(__CLASS__, 'debug', 'Please collect the MELCloud token before creating equipment.');
      } else {
        log::add(__CLASS__, 'info', '===== Synchronize all data from MELCloud =====');

        $client = new MitsubishiMelcouldClient();
        $values = $client->MelcloudAllDevices($Token);

        foreach ($values as $maison) {
          log::add(__CLASS__, 'debug', 'Building: ' . $maison['Name']);
          for ($i = 0; $i < count($maison['Structure']['Devices']); $i++) {
            $device = $maison['Structure']['Devices'][$i];
            log::add(__CLASS__, 'debug', 'Synchronizing device ' . $i . ' ' . $device['DeviceName']);
            self::SynchronizeAllCommands('Synchro', $device);
          }
          // FLOORS
          for ($a = 0; $a < count($maison['Structure']['Floors']); $a++) {
            log::add(__CLASS__, 'debug', 'FLOORS ' . $a);
            // AREAS IN FLOORS
            for ($i = 0; $i < count($maison['Structure']['Floors'][$a]['Areas']); $i++) {
              for ($d = 0; $d < count($maison['Structure']['Floors'][$a]['Areas'][$i]['Devices']); $d++) {
                $device = $maison['Structure']['Floors'][$a]['Areas'][$i]['Devices'][$d];
                self::SynchronizeAllCommands('Synchro', $device);
              }
            }
            // FLOORS
            for ($i = 0; $i < count($maison['Structure']['Floors'][$a]['Devices']); $i++) {
              $device = $maison['Structure']['Floors'][$a]['Devices'][$i];
              self::SynchronizeAllCommands('Synchro', $device);
            }
          }
          // AREAS
          for ($a = 0; $a < count($maison['Structure']['Areas']); $a++) {
            log::add(__CLASS__, 'info', 'AREAS ' . $a);
            for ($i = 0; $i < count($maison['Structure']['Areas'][$a]['Devices']); $i++) {
              log::add(__CLASS__, 'info', 'machine AREAS ' . $i);
              $device = $maison['Structure']['Areas'][$a]['Devices'][$i];
              self::SynchronizeAllCommands('Synchro', $device);
            }
          }
        }
      }
    }
    log::add(__CLASS__, 'info', '<--- End of heat pump synchronization launched by : '.$action);
  }

  /** Collect split data from MELCloud app */
  public static function SynchronizeSplit($action = 'cron') {
    $Token = config::byKey('Token', __CLASS__);
    log::add(__CLASS__, 'info', '<--- Start Splits synchronization launched by : '.$action);

    if($action == 'cron' AND empty($Token)) {
      // When function launched by cron, do not launch the function if no token available
      log::add(__CLASS__, 'debug', 'No available token, cron desactivated');
    } else {
      if($Token == '' || substr($Token, 0, 11) == 'Login ERROR') {
        message::add(__CLASS__, __('Please collect the MELCloud token before creating equipment.', __FILE__));
        log::add(__CLASS__, 'debug', __('Please collect the MELCloud token before creating equipment.', __FILE__));
      } else {
        log::add(__CLASS__, 'info', '===== Synchronize split data from MELCloud app =====');
        
        
        $Splits = self::byType(__CLASS__);
        foreach($Splits as $Split) {
          // Only available on split that have already been activated once.
          if($Split->getConfiguration('deviceid') != '') {
            $client = new MitsubishiMelcouldClient();
            $Device = $client->MelcloudDeviceInfo(
              $Split->getConfiguration('deviceid'),
              $Split->getConfiguration('buildid'),
              $Token);
          
            $Device['FanSpeed'] = $Device['SetFanSpeed'];
            $Device['VaneVerticalDirection'] = $Device['VaneVertical'];
            $Device['VaneHorizontalDirection'] = $Device['VaneHorizontal'];
            $Info['Device'] = $Device;
            
            // Register data from server on Jeedom equipment
            self::SynchronizeAllCommands('Refresh', $Info, $Split);
          }
        }
      }
    }
    log::add(__CLASS__, 'info', '<--- End Splits synchronization launched by : '.$action);
  }

  /** Collect equipment information from Mitsubishi servers for the equipment */
  public static function SynchronizeAllCommands($type, $device = '', $DeviceLogicalId = '') {
    if($type == 'Synchro') {
      log::add(__CLASS__, 'debug', 'Synchronize : ' . $device['DeviceName']);
      if($device['DeviceID'] == '') return;
      log::add(__CLASS__, 'debug', $device['DeviceID'] . ' ' . $device['DeviceName']);

      $theEQlogic = eqLogic::byTypeAndSearchConfiguration(__CLASS__, '"MachineName":"' . $device['DeviceName'] . '"');

      if(count($theEQlogic) == 0) {
        // Create the equipment if it doesn't exist yet
        $mylogical = new mitsubishimelcloud();
        $mylogical->setIsVisible(0);
        $mylogical->setIsEnable(0);
        $mylogical->setEqType_name(__CLASS__);
        $mylogical->setName($device['DeviceName']);
        $mylogical->setConfiguration('MachineName', $device['DeviceName']);
        $mylogical->save();

        return;
      } else {
        // Update the equipment if it already exist
        $mylogical =  $theEQlogic[0];
        if($mylogical->getIsEnable()) {
          log::add(__CLASS__, 'debug', 'Set device ' . $device['Device']['DeviceID']);
          $mylogical->setConfiguration('deviceid', $device['Device']['DeviceID']);
          $mylogical->setConfiguration('buildid', $device['BuildingID']);

          if($device['Device']['DeviceType'] == '0') {
            log::add(__CLASS__, 'debug', 'Heat pump air/air');
            $mylogical->setConfiguration('typepac', 'air/air');
          } elseif($device['Device']['DeviceType'] == '1') {
            log::add(__CLASS__, 'debug', 'Heat pump air/water');
            $mylogical->setConfiguration('typepac', 'air/eau');
          } else {
            log::add(__CLASS__, 'error', 'No heat pump type found');
            return;
          }

          if($mylogical->getConfiguration('rubriques') == '') {
              $mylogical->setConfiguration('rubriques', print_r($device['Device'], true));
          }

          $mylogical->save();
          //$device = $device['Device'];
        }
      }
    } elseif($type == 'Refresh') {
      $mylogical = $DeviceLogicalId;
    }

    foreach ($mylogical->getCmd() as $cmd) {
      switch ($cmd->getLogicalId()) {
        case 'refresh':
        case 'On':
        case 'Off':
        case 'OperationMode':
        case 'VaneVerticalDirection':
        case 'VaneHorizontalDirection':
          // These commands doesn't need and update
          log::add(__CLASS__, 'debug', 'log for '.$cmd->getLogicalId().' : command not processed');
          break;
        
        case 'SetTemperature_Value':
        case 'OperationMode_Value':
        case 'FanSpeed_Value':
        case 'VaneVerticalDirection_Value':
        case 'VaneHorizontalDirection_Value':
          // We do the same operation for these 5 "xx_Value"
          $operation = str_replace('_Value', '', $cmd->getLogicalId());
          log::add(__CLASS__, 'debug', 'log for '.$operation.' : '.$cmd->getLogicalId().', value: '.$device['Device'][$operation]);
          $cmd->setCollectDate('');
          $cmd->event($device['Device'][$operation]);
          $cmd->save();
          break;

        case 'SetTemperature':
          // Define Min / Max temperature for slider AND current requested temperature
          if($type == 'Synchro') {
            $stepArray = array('step' => floatval($device['Device']['TemperatureIncrement']));
            $cmd->setDisplay('parameters', $stepArray);
            if($device['Device']['OperationMode'] == 1) {
              log::add(__CLASS__, 'debug', 'OperationMode : HEAT');
              log::add(__CLASS__, 'debug', 'Max / Min temperature definition: '.intval($device['Device']['MaxTempHeat']).' / '.intval($device['Device']['MinTempHeat']));
              $cmd->setConfiguration('maxValue', intval($device['Device']['MaxTempHeat']));
              $cmd->setConfiguration('minValue', intval($device['Device']['MinTempHeat']));
            } else {
              log::add(__CLASS__, 'debug', 'OperationMode : COOL');
              log::add(__CLASS__, 'debug', 'Max / Min temperature definition: '.intval($device['Device']['MaxTempCoolDry']).' / '.intval($device['Device']['MinTempCoolDry']));
              $cmd->setConfiguration('maxValue', intval($device['Device']['MaxTempCoolDry']));
              $cmd->setConfiguration('minValue', intval($device['Device']['MinTempCoolDry']));
            }
          }
          log::add(__CLASS__, 'debug', $cmd->getLogicalId().' : '.$device['Device'][$cmd->getLogicalId()]);
          $cmd->event($device['Device'][$cmd->getLogicalId()]);
          $cmd->save();
          break;

        case 'FanSpeed':
          log::add(__CLASS__, 'debug', 'log for FanSpeed : '.$cmd->getLogicalId().', value: '.$device['Device']['NumberOfFanSpeeds']);
          $cmd->setConfiguration('maxValue', $device['Device']['NumberOfFanSpeeds']);
          $cmd->save();
          break;

        case 'WeatherIcon1':
        case 'WeatherIcon2':
        case 'WeatherIcon3':
        case 'WeatherIcon4':
          log::add(__CLASS__, 'debug', 'log for weather icone, for day'.substr($cmd->getLogicalId(), -1));
          $cmd->event($device['Device']['WeatherObservations'][substr($cmd->getLogicalId(), -1) - 1]['Icon']);
          $cmd->save();
          break;

        case 'WeatherDay1':
        case 'WeatherDay2':
        case 'WeatherDay3':
        case 'WeatherDay4':
          log::add(__CLASS__, 'debug', 'log for weather day, for day '.substr($cmd->getLogicalId(), -1));
          $cmd->event($device['Device']['WeatherObservations'][substr($cmd->getLogicalId(), -1) - 1]['Day']);
          $cmd->save();
          break;

        case 'WeatherTemperature1':
        case 'WeatherTemperature2':
        case 'WeatherTemperature3':
        case 'WeatherTemperature4':
          log::add(__CLASS__, 'debug', 'log for weather temperature, for day '.substr($cmd->getLogicalId(), -1));
          $cmd->event($device['Device']['WeatherObservations'][substr($cmd->getLogicalId(), -1) - 1]['Temperature']);
          $cmd->save();
          break;

        case 'WeatherType1':
        case 'WeatherType2':
        case 'WeatherType3':
        case 'WeatherType4':
          log::add(__CLASS__, 'debug', 'log for weather type, for day '.substr($cmd->getLogicalId(), -1));
          $cmd->event($device['Device']['WeatherObservations'][substr($cmd->getLogicalId(), -1) - 1]['WeatherType']);
          $cmd->save();
          break;

        case 'WeatherCondition1':
        case 'WeatherCondition2':
        case 'WeatherCondition3':
        case 'WeatherCondition4':
          log::add(__CLASS__, 'debug', 'log for weather condition, for day '.substr($cmd->getLogicalId(), -1));
          $cmd->event($device['Device']['WeatherObservations'][substr($cmd->getLogicalId(), -1) - 1]['ConditionName']);
          $cmd->save();
          break;

        default:
          // For : Power, RoomTemperature
          log::add(__CLASS__, 'debug','log for weneral case : '.$cmd->getLogicalId().', value : '.$device['Device'][$cmd->getLogicalId()]);
          $cmd->event($device['Device'][$cmd->getLogicalId()]);
          $cmd->save();
          break;
      }
    }

    // Update
    $mylogical->Refresh();
    $mylogical->toHtml('dashboard');
    $mylogical->refreshWidget();
  }

  /** Send request to Mitsubishi servers */
  public static function SendDeviceUpdate($NewValue, $DeviceLogicalId, $Command, $Flag) {
    $Token = config::byKey('Token', __CLASS__);
    if($Token == '' || substr($Token, 0, 11) == 'Login ERROR') {
      message::add(__CLASS__, __('Please collect the MELCloud token before creating equipment.', __FILE__));
      log::add(__CLASS__, 'debug', 'Please collect the MELCloud token before creating equipment.');
    } else {
      log::add(__CLASS__, 'info', 'Send new value '.$NewValue.' for '.$Command.' to MELCloud');
      
      $client = new MitsubishiMelcouldClient();
      $Device = $client->MelcloudDeviceInfo(
        $DeviceLogicalId->getConfiguration('deviceid'),
        $DeviceLogicalId->getConfiguration('buildid'),
        $Token);
      
      $Device[$Command] = $NewValue;
      $Device['EffectiveFlags'] = $Flag;
      $Device['HasPendingCommand'] = 'true';

      //Send the data to MELCloud server
      $UpdatedDevice = $client->MelcloudDeviceUpdate($Device, $Token);
      log::add(__CLASS__, 'debug', 'retour mise à jour device : '.json_encode($UpdatedDevice));
      //Update device info based on feedback from MELCloud server information
      $UpdatedDevice['FanSpeed'] = $UpdatedDevice['SetFanSpeed'];
      $UpdatedDevice['VaneVerticalDirection'] = $UpdatedDevice['VaneVertical'];
      $UpdatedDevice['VaneHorizontalDirection'] = $UpdatedDevice['VaneHorizontal'];
      $Info['Device'] = $UpdatedDevice;
      self::SynchronizeAllCommands('Refresh', $Info, $DeviceLogicalId);
      
    }
  }
  
  /** Collect weather symbol if not available localy */
  public function getWeatherSymbol($filename) {
    $url ='https://app.melcloud.com/css/weather';
    $localdir = __DIR__ ."/../../data/symbol";
    if(!file_exists("$localdir/$filename")) {
      $content = file_get_contents("$url/$filename");
      if($content === false) {
        log::add(__CLASS__, 'debug', "Unable to get file: $url/$filename");
        return("$url/$filename");
      }
      if(!is_dir($localdir)) @mkdir($localdir, 0777, true);
      $res = file_put_contents("$localdir/$filename", $content);
      if($res === false) {
        log::add(__CLASS__, 'debug', "Unable to save file: $localdir/$filename");
        return("$url/$filename");
      }
    }
    return("plugins/" . __CLASS__ ."/data/symbol/$filename");
  }

  /*     * *********************Méthodes d'instance************************* */
  /** Method called after saving your Jeedom equipment */
  public function postSave() {
    if($this->getConfiguration('deviceid') == ''){
      // If not yet saved, collect first heat pump information
      self::SynchronizeMELCloud('PostSave');
      if($this->getConfiguration('deviceid') == '') return;
    }

    // If equipment existing, we create required commands
    if($this->getConfiguration('deviceid') != '') {
      $i = 1;
      // Create common commande for both style :
      $refresh = $this->getCmd(null, 'refresh');
      if(!is_object($refresh)) {
        $refresh = (new mitsubishimelcloudCmd)
        ->setName(__('Actualiser', __FILE__))
        ->setLogicalId('refresh')
        ->setOrder($i)
        ->setIsVisible(1)
        ->setType('action')
        ->setSubType('other')
        ->setEqLogic_id($this->getId());
        $refresh->save();
      }

      $i++;
      $PowerState = $this->getCmd(null, 'Power');
      if(!is_object($PowerState)) {
        $PowerState = (new mitsubishimelcloudCmd)
        ->setName(__('Power', __FILE__))
        ->setLogicalId('Power')
        ->setOrder($i)
        ->setIsVisible(0)
        ->setIsHistorized(0)
        ->setType('info')
        ->setSubType('binary')
        ->setGeneric_type('ENERGY_STATE')
        ->setEqLogic_id($this->getId());
        $PowerState->save();
      }

      $i++;
      $On = $this->getCmd(null, 'On');
      if(!is_object($On)) {
        $On = (new mitsubishimelcloudCmd)
        ->setName(__('On', __FILE__))
        ->setLogicalId('On')
        ->setOrder($i)
        ->setIsVisible(1)
        ->setIsHistorized(0)
        ->setType('action')
        ->setSubType('other')
        ->setTemplate('dashboard', 'OnOffMitsubishi')
        ->setTemplate('mobile', 'OnOffMitsubishi')
        ->setDisplay('generic_type', 'ENERGY_ON')
        ->setConfiguration('updateCmdId', $PowerState->getEqLogic_id())
        ->setConfiguration('updateCmdToValue', 1)
        ->setEqLogic_id($this->getId());
        $On->save();
      }

      $i++;
      $Off = $this->getCmd(null, 'Off');
      if(!is_object($Off)) {
        $Off = (new mitsubishimelcloudCmd)
        ->setName(__('Off', __FILE__))
        ->setLogicalId('Off')
        ->setOrder($i)
        ->setIsVisible(1)
        ->setIsHistorized(0)
        ->setType('action')
        ->setSubType('other')
        ->setTemplate('dashboard', 'OnOffMitsubishi')
        ->setTemplate('mobile', 'OnOffMitsubishi')
        ->setDisplay('generic_type', 'ENERGY_OFF')
        ->setConfiguration('updateCmdId', $PowerState->getEqLogic_id())
        ->setConfiguration('updateCmdToValue', 0)
        ->setEqLogic_id($this->getId());
        $Off->save();
      }

      $i++;
      // Create command specific of each style :
      if($this->getConfiguration('typepac') == 'air/air'){
        $RoomTemperature = $this->getCmd(null, 'RoomTemperature');
        if(!is_object($RoomTemperature)) {
          $RoomTemperature = (new mitsubishimelcloudCmd)
          ->setName(__('Température de la pièce', __FILE__))
          ->setLogicalId('RoomTemperature')
          ->setOrder($i)
          ->setIsVisible(1)
          ->setIsHistorized(1)
          ->setType('info')
          ->setSubType('numeric')
          ->setUnite('°C')
          ->setTemplate('dashboard', 'TemperatureMitsubishi')
          ->setTemplate('mobile', 'TemperatureMitsubishi')
          ->setDisplay('generic_type', 'THERMOSTAT_TEMPERATURE')
          ->setEqLogic_id($this->getId());
          $RoomTemperature->save();
        }
        
        $i++;
        $SetTemperature_Value = $this->getCmd(null, 'SetTemperature_Value');
        if(!is_object($SetTemperature_Value)) {
          $SetTemperature_Value = (new mitsubishimelcloudCmd)
          ->setName(__('Valeur température consigne', __FILE__))
          ->setLogicalId('SetTemperature_Value')
          ->setOrder($i)
          ->setIsVisible(0)
          ->setIsHistorized(1)
          ->setType('info')
          ->setSubType('numeric')
          ->setUnite('°C')
          ->setDisplay('generic_type', 'THERMOSTAT_SETPOINT')
          ->setEqLogic_id($this->getId());
          $SetTemperature_Value->save();
        }
        
        $i++;
        $SetTemperature = $this->getCmd(null, 'SetTemperature');
        if(!is_object($SetTemperature)) {
          $SetTemperature = (new mitsubishimelcloudCmd)
          ->setName(__('Température consigne', __FILE__))
          ->setLogicalId('SetTemperature')
          ->setOrder($i)
          ->setIsVisible(1)
          ->setIsHistorized(0)
          ->setType('action')
          ->setSubType('slider')
          ->setConfiguration('minValue', 10)
          ->setConfiguration('maxValue', 30)
          ->setConfiguration('step', 1)
          ->setUnite('°C')
          ->setTemplate('dashboard', 'TemperatureMitsubishi')
          ->setTemplate('mobile', 'TemperatureMitsubishi')
          ->setDisplay('generic_type', 'THERMOSTAT_SETPOINT')
          ->setConfiguration('updateCmdId', $SetTemperature_Value->getEqLogic_id())
          ->setValue($SetTemperature_Value->getId())
          ->setEqLogic_id($this->getId());
          $SetTemperature->save();
        }
        
        $i++;
        $OperationMode_Value = $this->getCmd(null, 'OperationMode_Value');
        if(!is_object($OperationMode_Value)) {
          $OperationMode_Value = (new mitsubishimelcloudCmd)
          ->setName(__('Mode actif', __FILE__))
          ->setLogicalId('OperationMode_Value')
          ->setOrder($i)
          ->setIsVisible(0)
          ->setIsHistorized(1)
          ->setType('info')
          ->setSubType('numeric')
          ->setDisplay('generic_type', 'THERMOSTAT_MODE')
          ->setEqLogic_id($this->getId());
          $OperationMode_Value->save();
        }
        
        $i++;
        $OperationMode = $this->getCmd(null, 'OperationMode');
        if(!is_object($OperationMode)) {
          $OperationMode = (new mitsubishimelcloudCmd)
          ->setName(__('Mode', __FILE__))
          ->setLogicalId('OperationMode')
          ->setOrder($i)
          ->setIsVisible(1)
          ->setIsHistorized(0)
          ->setType('action')
          ->setSubType('slider')
          ->setConfiguration('minValue', 1)
          ->setConfiguration('maxValue', 8)
          ->setTemplate('dashboard', 'ModeMitsubishi')
          ->setTemplate('mobile', 'ModeMitsubishi')
          ->setDisplay('generic_type', 'THERMOSTAT_SET_MODE')
          ->setConfiguration('updateCmdId', $OperationMode_Value->getEqLogic_id())
          ->setValue($OperationMode_Value->getId())
          ->setEqLogic_id($this->getId());
          $OperationMode->save();
        }
        
        $i++;
        $FanSpeed_Value = $this->getCmd(null, 'FanSpeed_Value');
        if(!is_object($FanSpeed_Value)) {
          $FanSpeed_Value = (new mitsubishimelcloudCmd)
          ->setName(__('Valeur vitesse ventilation', __FILE__))
          ->setLogicalId('FanSpeed_Value')
          ->setOrder($i)
          ->setIsVisible(0)
          ->setIsHistorized(1)
          ->setType('info')
          ->setSubType('numeric')
          ->setDisplay('generic_type', 'FAN_SPEED_STATE')
          ->setEqLogic_id($this->getId());
          $FanSpeed_Value->save();
        }
        
        $i++;
        $FanSpeed = $this->getCmd(null, 'FanSpeed');
        if(!is_object($FanSpeed)) {
          $FanSpeed = (new mitsubishimelcloudCmd)
          ->setName(__('Vitesse ventilation', __FILE__))
          ->setLogicalId('FanSpeed')
          ->setOrder($i)
          ->setIsVisible(1)
          ->setIsHistorized(0)
          ->setType('action')
          ->setSubType('slider')
          ->setConfiguration('minValue', 0)
          ->setConfiguration('maxValue', 5)
          ->setTemplate('dashboard', 'FanSpeedMitsubishi')
          ->setTemplate('mobile', 'FanSpeedMitsubishi')
          ->setDisplay('generic_type', 'FAN_SPEED')
          ->setConfiguration('updateCmdId', $FanSpeed_Value->getEqLogic_id())
          ->setValue($FanSpeed_Value->getId())
          ->setEqLogic_id($this->getId());
          $FanSpeed->save();
        }
        
        $i++;
        $VaneVerticalDirection_Value = $this->getCmd(null, 'VaneVerticalDirection_Value');
        if(!is_object($VaneVerticalDirection_Value)) {
          $VaneVerticalDirection_Value = (new mitsubishimelcloudCmd)
          ->setName(__('Valeur position ailettes verticales', __FILE__))
          ->setLogicalId('VaneVerticalDirection_Value')
          ->setOrder($i)
          ->setIsVisible(0)
          ->setIsHistorized(1)
          ->setType('info')
          ->setSubType('numeric')
          ->setDisplay('generic_type', 'ROTATION_STATE')
          ->setEqLogic_id($this->getId());
          $VaneVerticalDirection_Value->save();
        }
        
        $i++;
        $VaneVerticalDirection = $this->getCmd(null, 'VaneVerticalDirection');
        if(!is_object($VaneVerticalDirection)) {
          $VaneVerticalDirection = (new mitsubishimelcloudCmd)
          ->setName(__('Position ailettes verticales', __FILE__))
          ->setLogicalId('VaneVerticalDirection')
          ->setOrder($i)
          ->setIsVisible(1)
          ->setIsHistorized(0)
          ->setType('action')
          ->setSubType('slider')
          ->setConfiguration(
              'listValue', 
              '0|Auto;1|1;2|2;3|3;4|4;5|5;7|Basculer'
            )
          ->setDisplay(
              'slider_placeholder',
              'Auto : 0 1 : 1 2 : 2 3 : 3 4 : 4 5 : 5 Basculer : 7'
            )
          ->setTemplate('dashboard', 'VaneVerticalDirectionMitsubishi')
          ->setTemplate('mobile', 'VaneVerticalDirectionMitsubishi')
          ->setDisplay('generic_type', 'ROTATION')
          ->setConfiguration('updateCmdId', $VaneVerticalDirection_Value->getEqLogic_id())
          ->setValue($VaneVerticalDirection_Value->getId())
          ->setEqLogic_id($this->getId());
          $VaneVerticalDirection->save();
        }
        
        $i++;
        $VaneHorizontalDirection_Value = $this->getCmd(null, 'VaneHorizontalDirection_Value');
        if(!is_object($VaneHorizontalDirection_Value)) {
          $VaneHorizontalDirection_Value = (new mitsubishimelcloudCmd)
          ->setName(__('Valeur position ailettes horizontales', __FILE__))
          ->setLogicalId('VaneHorizontalDirection_Value')
          ->setOrder($i)
          ->setIsVisible(0)
          ->setIsHistorized(1)
          ->setType('info')
          ->setSubType('numeric')
          ->setDisplay('generic_type', 'ROTATION_STATE')
          ->setEqLogic_id($this->getId());
          $VaneHorizontalDirection_Value->save();
        }
        
        $i++;
        $VaneHorizontalDirection = $this->getCmd(null, 'VaneHorizontalDirection');
        if(!is_object($VaneHorizontalDirection)) {
          $VaneHorizontalDirection = (new mitsubishimelcloudCmd)
          ->setName(__('Position ailettes horizontal', __FILE__))
          ->setLogicalId('VaneHorizontalDirection')
          ->setOrder($i)
          ->setIsVisible(1)
          ->setIsHistorized(0)
          ->setType('action')
          ->setSubType('slider')
          ->setConfiguration(
              'listValue', 
              '0|Auto;1|1;2|2;3|3;4|4;5|5;12|Basculer'
            )
          ->setDisplay(
              'slider_placeholder',
              'Auto : 0 1 : 1 2 : 2 3 : 3 4 : 4 5 : 5 Basculer : 12'
            )
          ->setTemplate('dashboard', 'VaneVerticalDirectionMitsubishi')
          ->setTemplate('mobile', 'VaneVerticalDirectionMitsubishi')
          ->setDisplay('generic_type', 'ROTATION')
          ->setConfiguration('updateCmdId', $VaneHorizontalDirection_Value->getEqLogic_id())
          ->setValue($VaneHorizontalDirection_Value->getId())
          ->setEqLogic_id($this->getId());
          $VaneHorizontalDirection->save();
        }

        for($j = 1; $j <=4; $j++) {
          $i++;
          $WeatherIcon[$j] = $this->getCmd(null, 'WeatherIcon'.$j);
          if(!is_object($WeatherIcon[$j])) {
            $WeatherIcon[$j] = (new mitsubishimelcloudCmd)
            ->setName(__('Icône météo jour n°', __FILE__).$j)
            ->setLogicalId('WeatherIcon'.$j)
            ->setOrder($i)
            ->setIsVisible(0)
            ->setIsHistorized(0)
            ->setType('info')
            ->setSubType('other')
            ->setDisplay('generic_type', 'GENERIC_INFO')
            ->setEqLogic_id($this->getId());
            $WeatherIcon[$j]->save();
          }
          
          $i++;
          $WeatherDay[$j] = $this->getCmd(null, 'WeatherDay'.$j);
          if(!is_object($WeatherDay[$j])) {
            $WeatherDay[$j] = (new mitsubishimelcloudCmd)
            ->setName(__('Jour météo n°', __FILE__).$j)
            ->setLogicalId('WeatherDay'.$j)
            ->setOrder($i)
            ->setIsVisible(0)
            ->setIsHistorized(0)
            ->setType('info')
            ->setSubType('other')
            ->setDisplay('generic_type', 'GENERIC_INFO')
            ->setEqLogic_id($this->getId());
            $WeatherDay[$j]->save();
          }

          $i++;
          $WeatherTemperature[$j] = $this->getCmd(null, 'WeatherTemperature'.$j);
          if(!is_object($WeatherTemperature[$j])) {
            $WeatherTemperature[$j] = (new mitsubishimelcloudCmd)
            ->setName(__('Température jour n°', __FILE__).$j)
            ->setLogicalId('WeatherTemperature'.$j)
            ->setOrder($i)
            ->setIsVisible(0)
            ->setIsHistorized(0)
            ->setType('info')
            ->setSubType('other')
            ->setDisplay('generic_type', 'WEATHER_TEMPERATURE')
            ->setEqLogic_id($this->getId());
            $WeatherTemperature[$j]->save();
          }

          $i++;
          $WeatherType[$j] = $this->getCmd(null, 'WeatherType'.$j);
          if(!is_object($WeatherType[$j])) {
            $WeatherType[$j] = (new mitsubishimelcloudCmd)
            ->setName(__('Type météo jour n°', __FILE__).$j)
            ->setLogicalId('WeatherType'.$j)
            ->setOrder($i)
            ->setIsVisible(0)
            ->setIsHistorized(0)
            ->setType('info')
            ->setSubType('other')
            ->setDisplay('generic_type', 'GENERIC_INFO')
            ->setEqLogic_id($this->getId());
            $WeatherType[$j]->save();
          }

          $i++;
          $WeatherCondition[$j] = $this->getCmd(null, 'WeatherCondition'.$j);
          if(!is_object($WeatherCondition[$j])) {
            $WeatherCondition[$j] = (new mitsubishimelcloudCmd)
            ->setName(__('Condition météo jour n°', __FILE__).$j)
            ->setLogicalId('WeatherCondition'.$j)
            ->setOrder($i)
            ->setIsVisible(0)
            ->setIsHistorized(0)
            ->setType('info')
            ->setSubType('other')
            ->setDisplay('generic_type', 'WEATHER_CONDITION')
            ->setEqLogic_id($this->getId());
            $WeatherCondition[$j]->save();
          }
        }
      } elseif($this->getConfiguration('typepac') == 'air/eau') {
        log::add(__CLASS__, 'error', 'Air/water not supported by the plugin at the moment. Please contact the developer');
        throw new Exception(__('Air/water not supported by the plugin at the moment. Please contact the developer', __FILE__));
        return;
      } else {
        log::add(__CLASS__, 'error', 'No type of heat pump found');
        return;
      }
    }

    if($this->getConfiguration('deviceid') != ''){
      // If correctly created, collect split information
      self::SynchronizeSplit('PostSave');
    }
  }

  /** Collect data to design the widget */
  public function toHtml($_version = 'dashboard') {
    $replace = $this->preToHtml($_version);
    if(!is_array($replace)) {
      return $replace;
    }
    $version = jeedom::versionAlias($_version);

    $replace['#TemplateWidth#'] = 804;
    $replace['#TemplateHeight#'] = 640;

    $Power = $this->getCmd(null, 'Power');
    $Power_Value = is_object($Power) ? $Power->execCmd() : '';
    $replace['#Power#'] = ($Power_Value == 1) ? 'checked' : '';
    $PowerOn = $this->getCmd(null, 'On');
    $replace['#On_Cmd#'] = is_object($PowerOn) ? $PowerOn->getId() : '';
    $PowerOff = $this->getCmd(null, 'Off');
    $replace['#Off_Cmd#'] = is_object($PowerOff) ? $PowerOff->getId() : '';

    $OperationMode_Value = $this->getCmd(null, 'OperationMode_Value');
    $replace['#OperationMode_Value#'] = is_object($OperationMode_Value) ? $OperationMode_Value->execCmd() : '';
    $OperationMode = $this->getCmd(null, 'OperationMode');
    $replace['#OperationMode_Cmd#'] = is_object($OperationMode) ? $OperationMode->getId() : '';

    $FanSpeed_Value = $this->getCmd(null, 'FanSpeed_Value');
    $replace['#FanSpeed_Value#'] = is_object($FanSpeed_Value) ? $FanSpeed_Value->execCmd() : '';
    $FanSpeed = $this->getCmd(null, 'FanSpeed');
    $replace['#FanSpeed_Cmd#'] = is_object($FanSpeed) ? $FanSpeed->getId() : '';

    $HoriVane_Value = $this->getCmd(null, 'VaneHorizontalDirection_Value');
    $replace['#HoriVane_Value#'] = is_object($HoriVane_Value) ? $HoriVane_Value->execCmd() : '';
    $HoriVane = $this->getCmd(null, 'VaneHorizontalDirection');
    $replace['#HoriVane_Cmd#'] = is_object($HoriVane) ? $HoriVane->getId() : '';

    $VertiVane_Value = $this->getCmd(null, 'VaneVerticalDirection_Value');
    $replace['#VertiVane_Value#'] = is_object($VertiVane_Value) ? $VertiVane_Value->execCmd() : '';
    $VertiVane = $this->getCmd(null, 'VaneVerticalDirection');
    $replace['#VertiVane_Cmd#'] = is_object($VertiVane) ? $VertiVane->getId() : '';

    $RoomTemperature = $this->getCmd(null, 'RoomTemperature');
    $replace['#RoomTemperature#'] = is_object($RoomTemperature) ? $RoomTemperature->execCmd() : '';

    $SetTemp = $this->getCmd(null, 'SetTemperature');
    $replace['#MinTemperature#'] = is_object($SetTemp) ? $SetTemp->getConfiguration('minValue') : '';
    $replace['#MaxTemperature#'] = is_object($SetTemp) ? $SetTemp->getConfiguration('maxValue') : '';
    $replace['#Temp_Cmd#'] = is_object($SetTemp) ? $SetTemp->getId() : '';
    $SetTemp_Value = $this->getCmd(null, 'SetTemperature_Value');
    $replace['#SetTemperature#'] = is_object($SetTemp_Value) ? $SetTemp_Value->execCmd() : '';

    for($i = 1; $i <=4; $i++) {
      $WeatherIcon[$i] = $this->getCmd(null, 'WeatherIcon'.$i);
      if(is_object($WeatherIcon[$i])) {
        $replace['#WeatherIcon_'.$i.'#'] = self::getWeatherSymbol($WeatherIcon[$i]->execCmd().'.png');
      }

      $WeatherDay[$i] = $this->getCmd(null, 'WeatherDay'.$i);
      $subject = is_object($WeatherDay[$i]) ? $WeatherDay[$i]->execCmd() : '';
      $search  = array('0', '1', '2', '3', '4', '5', '6');
      $substitute = array('Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam');
      $replace['#WeatherDay_'.$i.'#'] = str_replace($search, $substitute, $subject);

      $WeatherTemperature[$i] = $this->getCmd(null, 'WeatherTemperature'.$i);
      $replace['#WeatherTemperature_'.$i.'#'] = is_object($WeatherTemperature[$i]) ? $WeatherTemperature[$i]->execCmd().'°C' : '';

      $WeatherType[$i] = $this->getCmd(null, 'WeatherType'.$i);
      $replace['#WeatherType_'.$i.'#'] = is_object($WeatherType[$i]) ? $WeatherType[$i]->execCmd() : '';

      $WeatherCondition[$i] = $this->getCmd(null, 'WeatherCondition'.$i);
      $replace['#WeatherCondition_'.$i.'#'] = is_object($WeatherCondition[$i]) ? $WeatherCondition[$i]->execCmd() : '';
    }

    $refresh = $this->getCmd(null, 'refresh');
    $replace['#refresh#'] = is_object($refresh) ? $refresh->getId() : '';

    // Titles :
    if($this->getConfiguration('Scenarios') == 1) {}
    if($this->getConfiguration('Mode') == 1) {}
    if($this->getConfiguration('FanSpeed') == 1) {}
    if($this->getConfiguration('VaneHoriical') == 1) {}
    if($this->getConfiguration('VaneVertical') == 1) {}
    if($this->getConfiguration('Temperature') == 1) {}
    if($this->getConfiguration('Weather') == 1) {}
    $replace['#ON#'] = __('Marche', __FILE__);
    $replace['#OFF#'] = __('Arrêt', __FILE__);
    $replace['#MitsuMelcloudName#'] = $this->getName();
    $replace['#RefreshTitle#'] = __('Rafraîchir', __FILE__);
    $replace['#Scenario#'] = __('Scenarios', __FILE__);
    $replace['#ModeTitle#'] = __('Mode', __FILE__);
    $replace['#FanTitle#'] = __('Vitesse de Ventilation', __FILE__);
    $replace['#HoriTitle#'] = __('Volet de soufflage horizontale', __FILE__);
    $replace['#VertiTitle#'] = __('Ailettes verticales', __FILE__);
    $replace['#TempTitle#'] = __('Température', __FILE__);
    $replace['#ForecastTitle#'] = __('Météo', __FILE__);
    $replace['#Heat#'] = __('Mode chaud', __FILE__);
    $replace['#Cool#'] = __('Mode froid', __FILE__);
    $replace['#Dry#'] = __('Séchage', __FILE__);
    $replace['#Fan#'] = __('Ventilation', __FILE__);
    $replace['#RoomTempTitle#'] = __('Température de la pièce', __FILE__);
    $replace['#SetTempTitle#'] = __('Régler la température', __FILE__);

    return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, __CLASS__, __CLASS__)));
  }
}

class mitsubishimelcloudCmd extends cmd {
  // Exécution d'une commande  
  public function execute($_options = array()) {
    if('refresh' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', '<------------ Refresh requested by button ------------>');
      $mylogical = $this->getEqLogic();

      $Token = config::byKey('Token', 'mitsubishimelcloud');
      if($Token == '' || substr($Token, 0, 11) == 'Login ERROR') {
        message::add('mitsubishimelcloud', __('Merci de récupérer le token MELCloud avant de synchroniser des équipements.', __FILE__));
        log::add('mitsubishimelcloud', 'debug', __('Merci de récupérer le token MELCloud avant de synchroniser des équipements.', __FILE__));
      } else {
        $client = new MitsubishiMelcouldClient();
        $Device = $client->MelcloudDeviceInfo(
          $mylogical->getConfiguration('deviceid'),
          $mylogical->getConfiguration('buildid'),
          $Token);
      
        $Device['FanSpeed'] = $Device['SetFanSpeed'];
        $Device['VaneVerticalDirection'] = $Device['VaneVertical'];
        $Device['VaneHorizontalDirection'] = $Device['VaneHorizontal'];
        $Info['Device'] = $Device; 
        mitsubishimelcloud::SynchronizeAllCommands('Refresh', $Info, $mylogical);
      }
    }
    if('On' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'Switch ON requested');
      mitsubishimelcloud::SendDeviceUpdate('true', $this->getEqLogic(), 'Power', 1);
    }
    if('Off' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'Switch OFF requested');
      mitsubishimelcloud::SendDeviceUpdate('false', $this->getEqLogic(), 'Power', 1);
    }
    if('OperationMode' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'New mode requested, value : '.$_options['message']);
      mitsubishimelcloud::SendDeviceUpdate($_options['message'], $this->getEqLogic(), 'OperationMode', 6);
    }
    if('FanSpeed' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'New Fan speed requested, value : '.$_options['message']);
      mitsubishimelcloud::SendDeviceUpdate($_options['message'], $this->getEqLogic(), 'SetFanSpeed', 8);
    }
    if('SetTemperature' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'New Temperature set : '.floatval($_options['message']));
      mitsubishimelcloud::SendDeviceUpdate($_options['message'], $this->getEqLogic(), 'SetTemperature', 4);
    }
    if('VaneHorizontalDirection' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'New horizontal vane direction, value : '.intval($_options['message']));
      mitsubishimelcloud::SendDeviceUpdate($_options['message'], $this->getEqLogic(), 'VaneHorizontal', 256);
    }
    if('VaneVerticalDirection' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'New Vertical vane direction, value : '.intval($_options['message']));
      mitsubishimelcloud::SendDeviceUpdate($_options['message'], $this->getEqLogic(), 'VaneVertical', 16);
    }
  }
}

use GuzzleHttp\Client;

/** CLASS to connect and exchange with Mitsubishi servers */
class MitsubishiMelcouldClient {
  const URL = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/';
  const DEVICE = 'Device/Get';
  const LOGIN = 'Login/ClientLogin';
  const LISTDEVICES = 'User/ListDevices';
  const SETATA = 'Device/SetAta';
  private $clientHttp;

  /** Parameters for GuzzleHttp\Client */
  public function __construct($clientHttp = null) {
    if($clientHttp == null) {
      try {
        $this->clientHttp = new Client([
          'base_uri' => self::URL,
          'connect_timeout' => 5,
          'timeout' => 10,
          'synchronous' => true,
          'version' => 2
        ]);
      } catch(\GuzzleHttp\Exception\GuzzleException $e) {
        log::add('mitsubishimelcloud', 'debug', 'MELCloud servers are not responding.');
      }
    } else {
      $this->clientHttp = $clientHttp;
    }
  }

  /** Collect Token from MelCloud server */
  public function MelcloudToken($Email = '', $Password = '', $Language = '', $AppVersion = '') {
    $server_output = $this->clientHttp->post(self::LOGIN, [
      'form_params' => [
        'Email' => $Email,
        'Password' => $Password,
        'Language' => $Language,
        'AppVersion' => $AppVersion,
        'Persist' => 'true',
        'CaptchaChallenge' => '',
        'CaptchaResponse' => '',
      ],
    ]);

    $json = json_decode($server_output->getBody(), true);
    if($json['ErrorId'] == null) {
      log::add('mitsubishimelcloud', 'debug', 'Login OK.');
      return $json['LoginData']['ContextKey'];
    } elseif($json['ErrorId'] == 1) {
      log::add('mitsubishimelcloud', 'debug', 'Login ERROR : incorrect MELCloud login or password.');
      return __('incorrect MELCloud login or password', __FILE__);
    } else {
      log::add('mitsubishimelcloud', 'debug', 'Login ERROR : code n°' . $json['ErrorId']);
      return 'Login ERROR : code n°' . $json['ErrorId'];
    }
  }

  /** Collect All devices from MelCloud server */
  public function MelcloudAllDevices($Token) {
    $server_output = $this->clientHttp->get(self::LISTDEVICES, [
      'headers' => [
        'Accept' => 'application/json',
        'X-MitsContextKey' => $Token,
      ],
    ]);
    if($server_output->getStatusCode() == 401) {
        throw new Exception(__('Error while synchronizing devices', __FILE__));
        log::add('mitsubishimelcloud', 'error', 'Error while synchronizing devices');
    }

    return json_decode($server_output->getBody(), true);
  }

  /** Collect information of the device from Melclould server */
  public function MelcloudDeviceInfo($DeviceId = '', $BuildID = '', $Token) {
    $server_output = $this->clientHttp->get(self::DEVICE, [
      'headers' => [
        'Accept' => 'application/json',
        'X-MitsContextKey' => $Token,
      ],
      'query' => [
        'id' => $DeviceId,
        'buildingID' => $BuildID,
      ],
    ]);
    if($server_output->getStatusCode() == 401) {
        throw new Exception(__('Error while collecting device information', __FILE__));
        log::add('mitsubishimelcloud', 'error', 'Error while collecting device information');
    }

    return json_decode($server_output->getBody(), true);
  }

  /** Publish data of the device on MelCloud server */
  public function MelcloudDeviceUpdate($Device, $Token) {
    $server_output = $this->clientHttp->post(self::SETATA, [
      'headers' => [
        'X-MitsContextKey' => $Token,
      ],
      'json' => $Device,
    ]);
    if($server_output->getStatusCode() == 401) {
        throw new Exception(__('Error while collecting device information', __FILE__));
        log::add('mitsubishimelcloud', 'error', 'Error while collecting device information');
    }

    return json_decode($server_output->getBody(), true);
  }
}