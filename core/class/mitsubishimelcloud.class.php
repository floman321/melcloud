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
  const DEFAULT_SYNC_CRON = '38 23 * * *'; //default cron for synchronisation
  const DEFAULT_SPLIT_CRON = '*/5 * * * *'; //default cron to update splits data

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */
  public static $_widgetPossibility = array('custom' => true, 'custom::layout' => true);

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

    // Manage empty weather
    for($i = 0; $i <= 3; $i++) {
      if(!isset($device['Device']['WeatherObservations'][$i])) $device['Device']['WeatherObservations'][$i]['ConditionName'] = 'EMPTY';
    }
    
    // Register on each logical item the value from MELCloud servers
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
          // For : Power, RoomTemperature, WarningText
          log::add(__CLASS__, 'debug','log for weneral case : '.$cmd->getLogicalId().', value : '.$device['Device'][$cmd->getLogicalId()]);
          $cmd->event($device['Device'][$cmd->getLogicalId()]);
          $cmd->save();
          break;
      }
    }

    // Update templates
    $mylogical->emptyCacheWidget();
    $mc = cache::byKey('mitsubishimelcloudWidgetmobile' . $mylogical->getId());
    $mc->remove();
    $mc = cache::byKey('mitsubishimelcloudWidgetdashboard' . $mylogical->getId());
    $mc->remove();
    $mylogical->toHtml('mobile');
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
      if(count($NewValue) == 1) {
        log::add(__CLASS__, 'info', 'Send new value '.$NewValue[0].' for '.$Command[0].' to MELCloud');
      } else {
        log::add(__CLASS__, 'info', 'Send scenario request to MELCloud');
      }
      
      $client = new MitsubishiMelcouldClient();
      $Device = $client->MelcloudDeviceInfo(
        $DeviceLogicalId->getConfiguration('deviceid'),
        $DeviceLogicalId->getConfiguration('buildid'),
        $Token);
      
      for($i = 0; $i < count($NewValue); $i++) {
        $Device[$Command[$i]] = $NewValue[$i];
      }
      $Device['EffectiveFlags'] = $Flag;
      $Device['HasPendingCommand'] = 'true';

      //Send the data to MELCloud server
      log::add(__CLASS__, 'debug', 'Envoie de la nouvelle commande : '.print_r($Device, true));
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
        ->setGeneric_type('AC_STATE')
        ->setEqLogic_id($this->getId());
        $PowerState->save();
      } else {
        $PowerState->setGeneric_type('AC_STATE');
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
        ->setDisplay('generic_type', 'AC_ON')
        ->setConfiguration('updateCmdId', $PowerState->getEqLogic_id())
        ->setConfiguration('updateCmdToValue', 1)
        ->setEqLogic_id($this->getId());
        $On->save();
      } else {
        $On->setGeneric_type('AC_ON');
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
        ->setDisplay('generic_type', 'AC_OFF')
        ->setConfiguration('updateCmdId', $PowerState->getEqLogic_id())
        ->setConfiguration('updateCmdToValue', 0)
        ->setEqLogic_id($this->getId());
        $Off->save();
      } else {
        $Off->setGeneric_type('AC_OFF');
        $Off->save();
      }

      $i++;
      $WarningText = $this->getCmd(null, 'WarningText');
      if(!is_object($WarningText)) {
        $WarningText = (new mitsubishimelcloudCmd)
        ->setName(__('Error message', __FILE__))
        ->setLogicalId('WarningText')
        ->setOrder($i)
        ->setIsVisible(1)
        ->setIsHistorized(0)
        ->setType('info')
        ->setSubType('string')
        ->setEqLogic_id($this->getId());
        $WarningText->save();
      }

      for($j = 1; $j <=4; $j++) {
        $i++;
        $Scenario[$j] = $this->getCmd(null, 'Scenario_'.$j);
        if(!is_object($Scenario[$j])) {
          $Scenario[$j] = (new mitsubishimelcloudCmd)
          ->setName(__('Scenario n°'.$j, __FILE__))
          ->setLogicalId('Scenario_'.$j)
          ->setOrder($i)
          ->setIsVisible(1)
          ->setIsHistorized(0)
          ->setType('action')
          ->setSubType('other')
          ->setEqLogic_id($this->getId());
          $Scenario[$j]->save();
        }
      }

      // Create command specific of each style :
      $i++;
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
          ->setDisplay('generic_type', 'AC_INDOOR_TEMPERATURE')
          ->setEqLogic_id($this->getId());
          $RoomTemperature->save();
        } else {
          $RoomTemperature->setGeneric_type('AC_INDOOR_TEMPERATURE');
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
          ->setDisplay('generic_type', 'AC_TEMPERATURE')
          ->setEqLogic_id($this->getId());
          $SetTemperature_Value->save();
        } else {
          $SetTemperature_Value->setGeneric_type('AC_TEMPERATURE');
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
          ->setDisplay('generic_type', 'AC_SET_TEMPERATURE')
          ->setConfiguration('updateCmdId', $SetTemperature_Value->getEqLogic_id())
          ->setValue($SetTemperature_Value->getId())
          ->setEqLogic_id($this->getId());
          $SetTemperature->save();
        } else {
          $SetTemperature->setGeneric_type('AC_SET_TEMPERATURE');
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
          ->setDisplay('generic_type', 'AC_MODE')
          ->setEqLogic_id($this->getId());
          $OperationMode_Value->save();
        } else {
          $OperationMode_Value->setGeneric_type('AC_MODE');
          $OperationMode_Value->save();
        }
        
        $OperationModeList = [1, 2, 3, 7, 8];
        $OperationModeName = [__('Mode chaud', __FILE__), __('Séchage', __FILE__), __('Mode froid', __FILE__), __('Ventilation', __FILE__), __('Auto', __FILE__)];
        for($j = 0; $j < count($OperationModeList); $j++) {
          $i++;
          $OperationMode[$OperationModeList[$j]] = $this->getCmd(null, 'OperationMode_'.$OperationModeList[$j]);
          if(!is_object($OperationMode[$OperationModeList[$j]])) {
            $OperationMode[$OperationModeList[$j]] = (new mitsubishimelcloudCmd)
            ->setName($OperationModeName[$j])
            ->setLogicalId('OperationMode_'.$OperationModeList[$j])
            ->setOrder($i)
            ->setIsVisible(1)
            ->setIsHistorized(0)
            ->setType('action')
            ->setSubType('slider')
            ->setConfiguration('minValue', 1)
            ->setConfiguration('maxValue', 8)
            ->setTemplate('dashboard', 'ModeMitsubishi')
            ->setTemplate('mobile', 'ModeMitsubishi')
            ->setDisplay('generic_type', 'AC_SET_MODE')
            ->setConfiguration('updateCmdId', $OperationMode_Value->getEqLogic_id())
            ->setValue($OperationMode_Value->getId())
            ->setEqLogic_id($this->getId());
            $OperationMode[$OperationModeList[$j]]->save();
          } else {
            $OperationMode[$OperationModeList[$j]]->setGeneric_type('AC_SET_MODE');
            $OperationMode[$OperationModeList[$j]]->save();
          }
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
          ->setDisplay('generic_type', 'AC_FAN_MODE')
          ->setEqLogic_id($this->getId());
          $FanSpeed_Value->save();
        } else {
          $FanSpeed_Value->setGeneric_type('AC_FAN_MODE');
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
          ->setDisplay('generic_type', 'AC_SET_FAN_MODE')
          ->setConfiguration('updateCmdId', $FanSpeed_Value->getEqLogic_id())
          ->setValue($FanSpeed_Value->getId())
          ->setEqLogic_id($this->getId());
          $FanSpeed->save();
        } else {
          $FanSpeed->setGeneric_type('AC_SET_FAN_MODE');
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
            ->setIsVisible(1)
            ->setIsHistorized(0)
            ->setType('info')
            ->setSubType('string')
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
            ->setIsVisible(1)
            ->setIsHistorized(0)
            ->setType('info')
            ->setSubType('string')
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
            ->setIsVisible(1)
            ->setIsHistorized(0)
            ->setType('info')
            ->setSubType('string')
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
            ->setIsVisible(1)
            ->setIsHistorized(0)
            ->setType('info')
            ->setSubType('string')
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
            ->setIsVisible(1)
            ->setIsHistorized(0)
            ->setType('info')
            ->setSubType('string')
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

    if($version == 'dashboard') {
      $replace['#width#'] = '804px';
      $replace['#height#'] = '640px';
    } else {
      $replace['#width#'] = '350px';
      $replace['#height#'] = '1524px';
    }
    
    $Power = $this->getCmd(null, 'Power');
    $Power_Value = is_object($Power) ? $Power->execCmd() : '';
    $replace['#Power#'] = ($Power_Value == 1) ? 'checked' : '';
    $replace['#PowerOnMobile#'] = ($Power_Value == 1) ? '' : 'none';
    $replace['#PowerOffMobile#'] = ($Power_Value == 1) ? 'none' : '';
    $PowerOn = $this->getCmd(null, 'On');
    $replace['#On_Cmd#'] = is_object($PowerOn) ? $PowerOn->getId() : '';
    $PowerOff = $this->getCmd(null, 'Off');
    $replace['#Off_Cmd#'] = is_object($PowerOff) ? $PowerOff->getId() : '';
    
    for($i = 1; $i <= 4; $i++){
      $Scenario[$i] = $this->getCmd(null, 'Scenario_'.$i);
      $replace['#Scenario'. $i .'_Cmd#'] = is_object($Scenario[$i]) ? $Scenario[$i]->getId() : '';
    }

    $OperationMode_Value = $this->getCmd(null, 'OperationMode_Value');
    $replace['#OperationMode_Value#'] = is_object($OperationMode_Value) ? $OperationMode_Value->execCmd() : '';
    $OperationModeList = [1, 2, 3, 7, 8];
    for($i = 0; $i < count($OperationModeList); $i++) {
      $OperationMode[$i] = $this->getCmd(null, 'OperationMode_'.$OperationModeList[$i]);
      $replace['#OperationMode'.$OperationModeList[$i].'_Cmd#'] = is_object($OperationMode[$i]) ? $OperationMode[$i]->getId() : '';
    }

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

    $WarningText = $this->getCmd(null, 'WarningText');
    if(is_object($WarningText)) {
      $replace['#WarningText#'] = is_numeric($WarningText->execCmd()) ? '' : $WarningText->execCmd();
    } else {
      $replace['#WarningText#'] = '';
    }

    $SetTemp = $this->getCmd(null, 'SetTemperature');
    $replace['#MinTemperature#'] = is_object($SetTemp) ? $SetTemp->getConfiguration('minValue') : '';
    $replace['#MaxTemperature#'] = is_object($SetTemp) ? $SetTemp->getConfiguration('maxValue') : '';
    $replace['#Temp_Cmd#'] = is_object($SetTemp) ? $SetTemp->getId() : '';
    $SetTemp_Value = $this->getCmd(null, 'SetTemperature_Value');
    $replace['#SetTemperature#'] = is_object($SetTemp_Value) ? $SetTemp_Value->execCmd() : '';

    for($i = 1; $i <=4; $i++) {
      $WeatherCondition[$i] = $this->getCmd(null, 'WeatherCondition'.$i);
      $WeatherCondition_[$i] = is_object($WeatherCondition[$i]) ? $WeatherCondition[$i]->execCmd() : '';

      if($WeatherCondition_[$i] == 'EMPTY' || $WeatherCondition_[$i] == '') {
        // No available weather for this column
        $replace['#WeatherCondition_'.$i.'#'] = '';
        $replace['#WeatherIcon_'.$i.'#'] = '';
        $replace['#WeatherDay_'.$i.'#'] = 'Pas de';
        $replace['#WeatherTemperature_'.$i.'#'] = '';
        $replace['#WeatherType_'.$i.'#'] = 'donnée';
      } else {
        $replace['#WeatherCondition_'.$i.'#'] = $WeatherCondition_[$i];

        $WeatherIcon[$i] = $this->getCmd(null, 'WeatherIcon'.$i);
        if(is_object($WeatherIcon[$i])) {
          $WeatherIcon_[$i] = self::getWeatherSymbol($WeatherIcon[$i]->execCmd().'.png');
          $replace['#WeatherIcon_'.$i.'#'] = '<img src="'.$WeatherIcon_[$i].'" style="width: 48px; margin-top: 10px; border-radius: 6px" />';
        }

        $WeatherDay[$i] = $this->getCmd(null, 'WeatherDay'.$i);
        $subject = is_object($WeatherDay[$i]) ? $WeatherDay[$i]->execCmd() : '';
        $search  = array('0', '1', '2', '3', '4', '5', '6');
        $substitute = array('Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam');
        $replace['#WeatherDay_'.$i.'#'] = str_replace($search, $substitute, $subject);

        $WeatherTemperature[$i] = $this->getCmd(null, 'WeatherTemperature'.$i);
        $replace['#WeatherTemperature_'.$i.'#'] = is_object($WeatherTemperature[$i]) ? $WeatherTemperature[$i]->execCmd().'°C' : '';

        $WeatherType[$i] = $this->getCmd(null, 'WeatherType'.$i);
        $subject = is_object($WeatherType[$i]) ? $WeatherType[$i]->execCmd() : '';
        $search  = array('0', '1', '2');
        $substitute = array('Act.', 'Jour', 'Nuit');
        $replace['#WeatherType_'.$i.'#'] = str_replace($search, $substitute, $subject);
      }
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
	/* * ************************* Attributes ****************************** */
	public static $_widgetPossibility = array('custom' => false);

	/* * ********************* Instance method ************************* */
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
      $NewValue[0] = 'true';
      $Command[0] = 'Power';
      mitsubishimelcloud::SendDeviceUpdate($NewValue, $this->getEqLogic(), $Command, 1);
    }
    if('Off' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'Switch OFF requested');
      $NewValue[0] = 'false';
      $Command[0] = 'Power';
      mitsubishimelcloud::SendDeviceUpdate($NewValue, $this->getEqLogic(), $Command, 1);
    }
    if('OperationMode_' == substr($this->logicalId, 0, 14)) {
      log::add('mitsubishimelcloud', 'debug', 'New mode requested, value : '.substr($this->logicalId, -1));
      $NewValue[0] = substr($this->logicalId, -1);
      $Command[0] = 'OperationMode';
      mitsubishimelcloud::SendDeviceUpdate($NewValue, $this->getEqLogic(), $Command, 6);
    }
    if('FanSpeed' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'New Fan speed requested, value : '.$_options['slider']);
      $NewValue[0] = $_options['slider'];
      $Command[0] = 'SetFanSpeed';
      mitsubishimelcloud::SendDeviceUpdate($NewValue, $this->getEqLogic(), $Command, 8);
    }
    if('SetTemperature' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'New Temperature set : '.floatval($_options['slider']));
      $NewValue[0] = $_options['slider'];
      $Command[0] = 'SetTemperature';
      mitsubishimelcloud::SendDeviceUpdate($NewValue, $this->getEqLogic(), $Command, 4);
    }
    if('VaneHorizontalDirection' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'New horizontal vane direction, value : '.intval($_options['slider']));
      $NewValue[0] = $_options['slider'];
      $Command[0] = 'VaneHorizontal';
      mitsubishimelcloud::SendDeviceUpdate($NewValue, $this->getEqLogic(), $Command, 256);
    }
    if('VaneVerticalDirection' == $this->logicalId) {
      log::add('mitsubishimelcloud', 'debug', 'New Vertical vane direction, value : '.intval($_options['slider']));
      $NewValue[0] = $_options['slider'];
      $Command[0] = 'VaneVertical';
      mitsubishimelcloud::SendDeviceUpdate($NewValue, $this->getEqLogic(), $Command, 16);
    }
    if('Scenario_' == substr($this->logicalId, 0, 9)) {
      if($this->getEqLogic()->getConfiguration('Scenario_'.substr($this->logicalId, -1)) == 1) {
        log::add('mitsubishimelcloud', 'debug', 'Launch scenario n°'.substr($this->logicalId, -1));
        $NewValue[0] = $this->getEqLogic()->getConfiguration('Mode_'.substr($this->logicalId, -1));
        $Command[0] = 'OperationMode';
        $NewValue[1] = $this->getEqLogic()->getConfiguration('FanSpeed_'.substr($this->logicalId, -1));
        $Command[1] = 'SetFanSpeed';
        $NewValue[2] = $this->getEqLogic()->getConfiguration('HoriVane_'.substr($this->logicalId, -1));
        $Command[2] = 'VaneHorizontal';
        $NewValue[3] = $this->getEqLogic()->getConfiguration('VertiVane_'.substr($this->logicalId, -1));
        $Command[3] = 'VaneVertical';
        $NewValue[4] = $this->getEqLogic()->getConfiguration('Temp_'.substr($this->logicalId, -1)) / 2;
        $Command[4] = 'SetTemperature';

        for($i = 0; $i < count($NewValue); $i++) {
          log::add('mitsubishimelcloud', 'debug', '   Command '.$Command[$i].' with value '.$NewValue[$i]);
        }
        mitsubishimelcloud::SendDeviceUpdate($NewValue, $this->getEqLogic(), $Command, 287);
      } else {
        log::add('mitsubishimelcloud', 'debug', "Scenario not activated, can't be launched");
      }
    }
  }
}

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;

/** CLASS to connect and exchange with Mitsubishi servers */
class MitsubishiMelcouldClient {
  const URL = 'https://app.melcloud.com/Mitsubishi.Wifi.Client/';
  const DEVICE = 'Device/Get';
  const LOGIN = 'Login/ClientLogin';
  const LISTDEVICES = 'User/ListDevices';
  const SETATA = 'Device/SetAta';
  const SETPOWER = 'Device/SetPower';
  private $clientHttp;

  /** Parameters for GuzzleHttp\Client */
  public function __construct($clientHttp = null) {
    if($clientHttp == null) {
      $this->clientHttp = new Client([
          'base_uri' => self::URL,
          'synchronous' => true,
          'version' => 2
        ]);
    } else {
      $this->clientHttp = $clientHttp;
    }
  }

  /** Collect Token from MelCloud server */
  public function MelcloudToken($Email = '', $Password = '', $Language = '', $AppVersion = '') {
    $json = self::MelcloudContact('POST', self::LOGIN, [
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
    return self::MelcloudContact('GET', self::LISTDEVICES, [
          'headers' => [
            'Accept' => 'application/json',
            'X-MitsContextKey' => $Token,
          ],
        ]);
  }

  /** Collect information of the device from Melclould server */
  public function MelcloudDeviceInfo($DeviceId = '', $BuildID = '', $Token) {
    return self::MelcloudContact('GET', self::DEVICE, [
          'headers' => [
            'Accept' => 'application/json',
            'X-MitsContextKey' => $Token,
          ],
          'query' => [
            'id' => $DeviceId,
            'buildingID' => $BuildID,
          ],
        ]);
  }

  /** Publish data of the device on MelCloud server */
  public function MelcloudDeviceUpdate($Device, $Token) {
    return self::MelcloudContact('POST', self::SETATA, [
          'headers' => [
            'X-MitsContextKey' => $Token,
          ],
          'json' => $Device,
        ]);
  }

  /** Function to exchange with MELCloud server */
  public function MelcloudContact($Type, $Link, $Info) {
    try {
      if($Type == 'POST') {
        $server_output = $this->clientHttp->post($Link, $Info);
      } elseif ($Type == 'GET') {
        $server_output = $this->clientHttp->get($Link, $Info);
      }
      return json_decode($server_output->getBody(), true);
    } catch (ConnectException $e) {
      log::add('mitsubishimelcloud', 'info', 'MELCloud servers error : ConnectException = network error');
      $Device['WarningText'] = 'MELCloud servers error : ConnectException = network error';
    } catch (ClientException $e) {
      log::add('mitsubishimelcloud', 'info', 'MELCloud servers error : ClientException = HTTP 400 errors');
      $Device['WarningText'] = 'MELCloud servers error : ClientException = HTTP 400 errors';
    } catch (RequestException $e) {
      log::add('mitsubishimelcloud', 'info', 'MELCloud servers error : RequestException');
      $Device['WarningText'] = 'MELCloud servers error : RequestException';
    } catch (ServerException $e) {
      log::add('mitsubishimelcloud', 'info', 'MELCloud servers error : ServerException = HTTP 500 erros');
      $Device['WarningText'] = 'MELCloud servers error : ServerException = HTTP 500 erros';
    } catch (\Exception $e) {
      log::add('mitsubishimelcloud', 'info', 'MELCloud servers error : Exception');
      $Device['WarningText'] = 'MELCloud servers error : Exception';
    }

    // Set values for error display
    $Device['SetFanSpeed'] = 6;
    $Device['OperationMode'] = 6;
    $Device['VaneHorizontal'] = 6;
    $Device['VaneVertical'] = 6;
    $Device['SetTemperature'] = "N/A";
    $Device['RoomTemperature'] = "N/A";
    return $Device;
  }
}