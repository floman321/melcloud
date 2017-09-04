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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class melcloud extends eqLogic
{
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

public static function SetModif($option, $mylogical,$flag,$idflag){
     
        log::add('melcloud', 'info', 'Modification '.$flag.' '.$idflag);
  
        $montoken = config::byKey('MyToken', 'melcloud', '');
        if ($montoken != '') {
          
            $devideid = $mylogical->getConfiguration('deviceid');
            $buildid = $mylogical->getConfiguration('buildid');
            $typepac = $mylogical->getConfiguration('typepac');
          
            $request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id=' . $devideid . '&buildingID=' . $buildid);
            $request->setHeader(array('X-MitsContextKey: ' . $montoken));
            $json = $request->exec(30000, 2);
            $device = json_decode($json, true);
            $device[$flag] = $option;
            $device['EffectiveFlags'] = $idflag;
            $device['HasPendingCommand'] = 'true';
            
          
            switch ($flag){
                
              case 'OperationMode':
                
                 $cmd = cmd::byEqLogicIdAndLogicalId($mylogical->getId(), 'OperationMode');                
                 $cmd->setCollectDate('');
                        
                switch ($option){
                  case 7:
                    $cmd->event('Ventilation');
                    break;
                  case 1:
                    $cmd->event('Chauffage');
                    break;
                  case 2:
                    $cmd->event('Sechage');
                    break;
                  case 3:
                    $cmd->event('Froid');
                    break;
                  case 8:
                    $cmd->event('Automatique');
                    break;
                }
                
                 $cmd->save();
                 $mylogical->Refresh();
            	 $mylogical->toHtml('dashboard');
             	 $mylogical->refreshWidget();
                
              case 'Power':
                
                $cmd = cmd::byEqLogicIdAndLogicalId($mylogical->getId(), 'Power');
                if ($option == 'true'){
                	$cmd->setConfiguration('lastCmdValue',1);  
                }else{
                  	$cmd->setConfiguration('lastCmdValue',0);
                }
                
                $cmd->save();
                $mylogical->Refresh();
            	$mylogical->toHtml('dashboard');
             	$mylogical->refreshWidget();
                
                break;   
            }
          
          
            $ch = curl_init();
                    
            if ($typepac == 'air/eau'){
              	curl_setopt($ch, CURLOPT_URL, "https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAtw");
            }else{
              	curl_setopt($ch, CURLOPT_URL, "https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta");
            }
          
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'X-MitsContextKey: ' . $montoken,
                'content-type: application/json'
            ));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($device));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($ch);
            curl_close($ch);
            $json = json_decode($server_output, true);
            foreach ($mylogical->getCmd() as $cmd) {
                if ('NextCommunication' == $cmd->getName()) {
                    $cmd->setCollectDate('');
                    $time = strtotime($json['NextCommunication'] . " + 1 hours"); // Add 1 hour
                    $time = date('G:i:s', $time); // Back to string
                    $cmd->event($time);
                }
            }
        }
}
      

    public static function gettoken()
    {

        $myemail = config::byKey('MyEmail', 'melcloud');
        $monpass = config::byKey('MyPassword', 'melcloud');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://app.melcloud.com/Mitsubishi.Wifi.Client/Login/ClientLogin");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            "Email=" . $myemail . "&Password=" . $monpass . "&Language=7&AppVersion=1.10.1.0&Persist=true&CaptchaChallenge=null&CaptchaChallenge=null");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($server_output, true);

        if ($json['ErrorId'] == null) {
            log::add('melcloud', 'debug', 'Login ok ');
            config::save("MyToken", $json['LoginData']['ContextKey'], 'melcloud');
        } else {
            log::add('melcloud', 'debug', 'Login ou mot de passe Melcloud incorrecte.');
            config::save("MyToken", $json['ErrorId'], 'melcloud');
        }

    }


    public static function pull()
    {
        $montoken = config::byKey('MyToken', 'melcloud', '');
        if ($montoken != '') {
            log::add('melcloud', 'info', 'pull 5 minutes mytoken =' . $montoken);
            //$request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id='.$devideid.'&buildingID='.$buildid);
            $request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/User/ListDevices');
            $request->setHeader(array('X-MitsContextKey: ' . $montoken));
            $json = $request->exec(30000, 2);
          
            $values = json_decode($json, true);
            foreach ($values as $maison) {
                log::add('melcloud', 'debug', 'Maison ' . $maison['Name']);
                for ($i = 0; $i < count($maison['Structure']['Devices']); $i++) {
                    log::add('melcloud', 'debug', 'pull : device 1 ' . $i . ' ' . $device['DeviceName']);
                    $device = $maison['Structure']['Devices'][$i];
                    self::pullCommande($device);
                }
                // FLOORS
                for ($a = 0; $a < count($maison['Structure']['Floors']); $a++) {
                    log::add('melcloud', 'debug', 'FLOORS ' . $a);
                    // AREAS IN FLOORS
                    for ($i = 0; $i < count($maison['Structure']['Floors'][$a]['Areas']); $i++) {
                        for ($d = 0; $d < count($maison['Structure']['Floors'][$a]['Areas'][$i]['Devices']); $d++) {
                            $device = $maison['Structure']['Floors'][$a]['Areas'][$i]['Devices'][$d];
                            self::pullCommande($device);
                        }
                    }
                    // FLOORS
                    for ($i = 0; $i < count($maison['Structure']['Floors'][$a]['Devices']); $i++) {
                        $device = $maison['Structure']['Floors'][$a]['Devices'][$i];
                        self::pullCommande($device);
                    }
                }
                // AREAS
                for ($a = 0; $a < count($maison['Structure']['Areas']); $a++) {
                    log::add('melcloud', 'info', 'AREAS ' . $a);
                    for ($i = 0; $i < count($maison['Structure']['Areas'][$a]['Devices']); $i++) {
                        log::add('melcloud', 'info', 'machine AREAS ' . $i);
                        $device = $maison['Structure']['Areas'][$a]['Devices'][$i];
                        self::pullCommande($device);
                    }
                }
            }
        }
    }

    private static function pullCommande($device) {
        log::add('melcloud', 'debug', 'pull : ' . $device['DeviceName']);
        if ($device['DeviceID'] == '') return;
        log::add('melcloud', 'debug', $device['DeviceID'] . ' ' . $device['DeviceName']);
      
        foreach (eqLogic::byType('melcloud', true) as $mylogical) {
          
            if ($mylogical->getConfiguration('namemachine') == $device['DeviceName']) {
              
                log::add('melcloud', 'debug', 'setdevice ' . $device['Device']['DeviceID']);
                $mylogical->setConfiguration('deviceid', $device['Device']['DeviceID']);
                $mylogical->setConfiguration('buildid', $device['BuildingID']);
				
              	if ($device['Device']['DeviceType'] == '0'){
                  $mylogical->setConfiguration('typepac', 'air/air');
                }
                if ($device['Device']['DeviceType'] == '1'){
                  $mylogical->setConfiguration('typepac', 'air/eau');
                }
              
                $device['Device']['ListHistory24Formatters'] = '';
                
              
 	            if ($mylogical->getConfiguration('rubriques') == '' )
                {
                  $mylogical->setConfiguration('rubriques', print_r($device['Device'],true));
                }
              
                $mylogical->save();
                
                foreach ($mylogical->getCmd() as $cmd) {
                    
                    switch ($cmd->getLogicalId()) {
                        
                       case 'OperationMode':
                          $cmd->setCollectDate('');
                        
                        	switch ($device['Device'][$cmd->getLogicalId()]){
                              case 7:
                                $cmd->event('Ventilation');
                                break;
                              case 1:
                                $cmd->event('Chauffage');
                                break;
                              case 2:
                                $cmd->event('Sechage');
                                break;
                              case 3:
                                $cmd->event('Froid');
                                break;
                              case 8:
                                $cmd->event('Automatique');
                                break;
                            }
                        		
                        break;
                        
                        
                        case 'Rafraichir':
                            log::add('melcloud', 'debug', 'log ' . $cmd->getLogicalId() . ' .On ne traite pas cette commande');
                            break;
                        default:
                        
                            if ($cmd->getType() == 'action'){
                              
                                 log::add('melcloud', 'debug', 'log action '.$cmd->getName().' ' . $cmd->getLogicalId() . ' ' . $device['Device'][$cmd->getLogicalId()]);
	                             $cmd->setConfiguration('lastCmdValue',$device['Device'][$cmd->getLogicalId()]);
                              
                            }else{
                        
                              log::add('melcloud', 'debug', 'log info '.$cmd->getName().' ' . $cmd->getLogicalId() . ' ' . $device['Device'][$cmd->getLogicalId()]);
                              if ('LastTimeStamp' == $cmd->getLogicalId()) {
                                $cmd->event(str_replace('T', ' ', $device['Device'][$cmd->getLogicalId()]));
                              } else {
                                $cmd->setCollectDate('');
                                $cmd->event($device['Device'][$cmd->getLogicalId()]);
                              }
                            
                            }
                        
                        	$cmd->save();
                        
                            break;
                    }
                
                }
              
              
                $mylogical->Refresh();
                $mylogical->toHtml('dashboard');
                $mylogical->refreshWidget();
            }
        }
    }

    //Fonction exécutée automatiquement toutes les minutes par Jeedom
    public static function cron()
    {


    }

    //Fonction exécutée automatiquement toutes les heures par Jeedom
    public static function cronHourly()
    {


    }


    // Fonction exécutée automatiquement tous les jours par Jeedom
    public static function cronDayly()
    {

    }


    /*     * *********************Méthodes d'instance************************* */

    public function preInsert()
    {


    }

    public function postInsert()
    {

    
      
    }

    public function preSave()
    {
      
        
    }

    public function postSave()
    {
      
      	 if ($this->getConfiguration('deviceid') == ''){
           	self::pull();
            if ($this->getConfiguration('deviceid') == '') return;
         }
      
         $RefreshCmd = $this->getCmd(null, 'refresh');
      
         if ($this->getConfiguration('deviceid') != '' && !is_object($RefreshCmd)) {
           
             if ($this->getConfiguration('typepac') == 'air/air'){
          		
                $RoomTemperature = $this->getCmd(null, 'RoomTemperature');
        		if (!is_object($RoomTemperature)) {
                    $RoomTemperature = new melcloudCmd();
                    $RoomTemperature->setName('Temperature Sonde');
                    $RoomTemperature->setEqLogic_id($this->getId());
                    $RoomTemperature->setLogicalId('RoomTemperature');
                    $RoomTemperature->setType('info');
                    $RoomTemperature->setSubType('numeric');
                    $RoomTemperature->setIsHistorized(0);
                    $RoomTemperature->setIsVisible(1);
                    $RoomTemperature->setUnite('°C');
                    $RoomTemperature->setTemplate('dashboard', 'line');
                    $RoomTemperature->setOrder(1);
                    $RoomTemperature->setValue(0);
                    $RoomTemperature->event(0);
                    $RoomTemperature->save();
                }
               
               $maj = $this->getCmd(null, 'LastTimeStamp');
        		if (!is_object($maj)) {

                  $maj = new melcloudCmd();
                  $maj->setName('Dernière mise à jour');
                  $maj->setEqLogic_id($this->getId());
                  $maj->setLogicalId('LastTimeStamp');
                  $maj->setType('info');
                  $maj->setSubType('string');
                  $maj->setIsHistorized(0);
                  $maj->setIsVisible(1);
                  $maj->setUnite('°C');
                  $maj->setTemplate('dashboard', 'line');
                  $maj->setOrder(2);
                  $maj->setValue(0);
                  $maj->event(0);
                  $maj->save();
                  
                }

				$mode = $this->getCmd(null, 'Mode');
        		if (!is_object($mode)) {
                  $mode = new melcloudCmd();
                  $mode->setName('Mode');
                  $mode->setEqLogic_id($this->getId());
                  $mode->setLogicalId('OperationMode');
                  $mode->setType('info');
                  $mode->setSubType('string');
                  $mode->setIsHistorized(0);
                  $mode->setIsVisible(1);
                  $mode->setOrder(3);
                  $mode->setDisplay('forceReturnLineAfter','1');
                  $mode->save();
                }


               	$Consigne = $this->getCmd(null, 'Consigne');
        		if (!is_object($Consigne)) {
                  $Consigne = new melcloudCmd();
                  $Consigne->setName('Consigne');
                  $Consigne->setEqLogic_id($this->getId());
                  $Consigne->setLogicalId('SetTemperature');
                  $Consigne->setType('action');
                  $Consigne->setTemplate('dashboard', 'thermostat');
                  $Consigne->setSubType('slider');
                  $Consigne->setIsHistorized(0);
                  $Consigne->setUnite('°C');
                  $Consigne->setIsVisible(1);
                  $Consigne->setDisplay('slider_placeholder', 'Temperature en °c ex');
                  $Consigne->setConfiguration('maxValue', 30);
                  $Consigne->setConfiguration('minValue', 10);
                  $Consigne->setOrder(4);
                  $Consigne->save();
                }


               	$on = $this->getCmd(null, 'Allumer');
        		if (!is_object($on)) {
                  $on = new melcloudCmd();
                  $on->setName('Allumer');
                  $on->setEqLogic_id($this->getId());
                  $on->setLogicalId('On');
                  $on->setType('action');
                  $on->setSubType('other');
                  $on->setTemplate('dashboard', 'button');
                  $on->setIsHistorized(0);
                  $on->setIsVisible(0);
                  $on->setOrder(5);
                  $on->save();
                }

               $off = $this->getCmd(null, 'Eteindre');
               if (!is_object($off)) {
                   $off = new melcloudCmd();
                   $off->setName('Eteindre');
                   $off->setEqLogic_id($this->getId());
                   $off->setLogicalId('Off');
                   $off->setType('action');
                   $off->setSubType('other');
                   $off->setTemplate('dashboard', 'button');
                   $off->setIsHistorized(0);
                   $off->setIsVisible(0);
                   $off->setOrder(6);
                   $off->save();
                }


				$etatclim = $this->getCmd(null, 'Power');
                if (!is_object($etatclim)) {
                    $etatclim = new melcloudCmd();
                    $etatclim->setName('Etat Clim');
                    $etatclim->setEqLogic_id($this->getId());
                    $etatclim->setLogicalId('Power');
                    $etatclim->setType('action');
                    $etatclim->setSubType('other');
                    $etatclim->setTemplate('dashboard', 'prise');
                    $etatclim->setIsHistorized(0);
                    $etatclim->setIsVisible(1);
                    $etatclim->setOrder(7);
                    $etatclim->setDisplay('showNameOndashboard','0');
                    $etatclim->save();
                }


                $ventilation = $this->getCmd(null, 'FanSpeed');
                if (!is_object($ventilation)) {
                    $ventilation = new melcloudCmd();
                    $ventilation->setName('Ventilation');
                    $ventilation->setEqLogic_id($this->getId());
                    $ventilation->setLogicalId('FanSpeed');
                    $ventilation->setType('action');
                    $ventilation->setSubType('slider');
                    $ventilation->setIsHistorized(0);
                    $ventilation->setDisplay('slider_placeholder', '0 = automatique, 1 a 5 manuel');
                    $ventilation->setTemplate('dashboard', 'button');
                    $ventilation->setIsVisible(1);
                    $ventilation->setOrder(8);
                    $ventilation->setConfiguration('maxValue', 5);
                    $ventilation->setConfiguration('minValue', 0);
                    $ventilation->setDisplay('forceReturnLineAfter','1');
                    $ventilation->save();
                }


                $ActualFanSpeed = $this->getCmd(null, 'ActualFanSpeed');
                if (!is_object($ActualFanSpeed)) {
                    $ActualFanSpeed = new melcloudCmd();
                    $ActualFanSpeed->setName('Vitesse Ventilateur Auto');
                    $ActualFanSpeed->setEqLogic_id($this->getId());
                    $ActualFanSpeed->setLogicalId('ActualFanSpeed');
                    $ActualFanSpeed->setType('info');
                    $ActualFanSpeed->setSubType('numeric');
                    $ActualFanSpeed->setIsHistorized(0);
                    $ActualFanSpeed->setTemplate('dashboard', 'tile');
                    $ActualFanSpeed->setIsVisible(0);
                    $ActualFanSpeed->setValue(0);
                    $ActualFanSpeed->event(0);
                    $ActualFanSpeed->setOrder(10);
                    $ActualFanSpeed->save();
                }

				
                $refresh = $this->getCmd(null, 'refresh');
                if (!is_object($refresh)) {
                    $refresh = new melcloudCmd();
                    $refresh->setLogicalId('refresh');
                    $refresh->setIsVisible(1);
                    $refresh->setName('Rafraichir');
                    $refresh->setEqLogic_id($this->getId());
                    $refresh->setType('action');
                    $refresh->setSubType('other');
                    $refresh->setOrder(11);
                    $refresh->save();
                }

               	$Chauffage = $this->getCmd(null, 'Chauffage');
                if (!is_object($refresh)) {
                    $Chauffage = new melcloudCmd();
                    $Chauffage->setLogicalId('Chauffage');
                    $Chauffage->setIsVisible(1);
                    $Chauffage->setName('Mode Chauffage');
                    $Chauffage->setEqLogic_id($this->getId());
                    $Chauffage->setType('action');
                    $Chauffage->setSubType('other');
                    $Chauffage->setOrder(12);
                    $Chauffage->setDisplay('showIconAndNamedashboard','1');
                    $Chauffage->setDisplay('icon','<i class="icon meteo-soleil"></i>');
                    $Chauffage->save();
                }
               
                $Froid = $this->getCmd(null, 'Froid');
                if (!is_object($Froid)) {
                    $Froid = new melcloudCmd();
                    $Froid->setLogicalId('Froid');
                    $Froid->setIsVisible(1);
                    $Froid->setName('Mode Froid');
                    $Froid->setEqLogic_id($this->getId());
                    $Froid->setType('action');
                    $Froid->setSubType('other');
                    $Froid->setOrder(13);
                    $Froid->setDisplay('showIconAndNamedashboard','1');
                    $Froid->setDisplay('icon','<i class="icon nature-snowflake"></i>');
                    $Froid->save();
                }

               	$ventile = $this->getCmd(null, 'Ventile');
                if (!is_object($ventile)) {
                    $ventile = new melcloudCmd();
                    $ventile->setLogicalId('Ventile');
                    $ventile->setIsVisible(1);
                    $ventile->setName('Mode Ventilation');
                    $ventile->setEqLogic_id($this->getId());
                    $ventile->setType('action');
                    $ventile->setSubType('other');
                    $ventile->setOrder(14);
                    $ventile->setDisplay('showIconAndNamedashboard','1');
                    $ventile->setDisplay('icon','<i class="icon jeedom-ventilo"></i>');
                     $ventile->setDisplay('forceReturnLineAfter','1');
                    $ventile->save();
                }

				$modeauto = $this->getCmd(null, 'ModeAuto');
                if (!is_object($modeauto)) {
                    $modeauto = new melcloudCmd();
                    $modeauto->setLogicalId('ModeAuto');
                    $modeauto->setIsVisible(1);
                    $modeauto->setName('Mode Automatique');
                    $modeauto->setEqLogic_id($this->getId());
                    $modeauto->setType('action');
                    $modeauto->setSubType('other');
                    $modeauto->setOrder(15);
                    $modeauto->setDisplay('showIconAndNamedashboard','1');
                    $modeauto->setDisplay('icon','<i class="icon fa-refresh"></i>');
              		$modeauto->save();
                }

			   $lien = $this->getCmd(null, 'lienmelcloud');
               if (!is_object($lien)) {
                   $lien = new melcloudCmd();
                   $lien->setLogicalId('lienmelcloud');
                   $lien->setIsVisible(1);
                   $lien->setName('Site Melcloud');
                   $lien->setEqLogic_id($this->getId());
                   $lien->setType('action');
                   $lien->setSubType('other');
                   $lien->setOrder(99);
                   $lien->setHtml('enable','1');
                   $lien->setHtml('dashboard','<br><br><i class="icon maison-home63"> </i><a href="https://app.melcloud.com" target="_blank">#name_display#</a>');
                   $lien->save();
               }

 			   $modesechage = $this->getCmd(null, 'sechage');
               if (!is_object($modesechage)) {
                   $modesechage = new melcloudCmd();
                   $modesechage->setLogicalId('sechage');
                   $modesechage->setIsVisible(1);
                   $modesechage->setName('Mode Séchage');
                   $modesechage->setEqLogic_id($this->getId());
                   $modesechage->setType('action');
                   $modesechage->setSubType('other');
                   $modesechage->setOrder(16);
                   $modesechage->setDisplay('showIconAndNamedashboard','1');
                   $modesechage->setDisplay('icon','<i class="icon jeedom-ventilo"></i>');
                   $modesechage->save();
               }
               
             }else{
               
               
			   $maj = $this->getCmd(null, 'LastTimeStamp');
			   if (!is_object($maj)) {
				   $maj = new melcloudCmd();
				   $maj->setName('Dernière mise à jour');
				   $maj->setEqLogic_id($this->getId());
				   $maj->setLogicalId('LastTimeStamp');
				   $maj->setType('info');
				   $maj->setSubType('string');
				   $maj->setIsHistorized(0);
				   $maj->setIsVisible(1);
				   $maj->setUnite('°C');
				   $maj->setTemplate('dashboard', 'line');
				   $maj->setOrder(1);
				   $maj->setValue(0);
				   $maj->event(0);
				   $maj->save();
				}
               
			    $etatclim = $this->getCmd(null, 'Power');
			   if (!is_object($etatclim)) {
					$etatclim = new melcloudCmd();
					$etatclim->setName('Etat Clim');
					$etatclim->setEqLogic_id($this->getId());
					$etatclim->setLogicalId('Power');
					$etatclim->setType('action');
					$etatclim->setSubType('other');
					$etatclim->setTemplate('dashboard', 'prise');
					$etatclim->setIsHistorized(0);
					$etatclim->setIsVisible(1);
					$etatclim->setOrder(2);
					$etatclim->setDisplay('showNameOndashboard','0');
					$etatclim->save();
			   }
               
			   $OutdoorTemperature = $this->getCmd(null, 'OutdoorTemperature');
			   if (!is_object($OutdoorTemperature)) {
				   $OutdoorTemperature = new melcloudCmd();
				   $OutdoorTemperature->setName('Exterieur');
				   $OutdoorTemperature->setEqLogic_id($this->getId());
				   $OutdoorTemperature->setLogicalId('OutdoorTemperature');
				   $OutdoorTemperature->setType('info');
				   $OutdoorTemperature->setSubType('numeric');
				   $OutdoorTemperature->setIsHistorized(0);
				   $OutdoorTemperature->setIsVisible(1);
				   $OutdoorTemperature->setUnite('°C');
				   $OutdoorTemperature->setTemplate('dashboard', 'line');
				   $OutdoorTemperature->setOrder(3);
				   $OutdoorTemperature->setValue(0);
				   $OutdoorTemperature->event(0);
				   $OutdoorTemperature->save();
			   }
              
			   $RoomTemperature = $this->getCmd(null, 'RoomTemperatureZone1');
			   if (!is_object($RoomTemperature)) {
				   $RoomTemperature = new melcloudCmd();
				   $RoomTemperature->setName('Temp 1');
				   $RoomTemperature->setEqLogic_id($this->getId());
				   $RoomTemperature->setLogicalId('RoomTemperatureZone1');
				   $RoomTemperature->setType('info');
				   $RoomTemperature->setSubType('numeric');
				   $RoomTemperature->setIsHistorized(0);
				   $RoomTemperature->setIsVisible(1);
				   $RoomTemperature->setUnite('°C');
				   $RoomTemperature->setTemplate('dashboard', 'line');
				   $RoomTemperature->setOrder(4);
				   $RoomTemperature->setValue(0);
				   $RoomTemperature->event(0);
				   $RoomTemperature->save();
			   }
               
               $Consigne = $this->getCmd(null, 'SetTemperatureZone1');
			   if (!is_object($Consigne)) {
					$Consigne = new melcloudCmd();
					$Consigne->setName('Consigne 1');
					$Consigne->setEqLogic_id($this->getId());
					$Consigne->setLogicalId('SetTemperatureZone1');
					$Consigne->setType('action');
					$Consigne->setTemplate('dashboard', 'button');
					$Consigne->setSubType('slider');
					$Consigne->setIsHistorized(0);
					$Consigne->setUnite('°C');
					$Consigne->setIsVisible(1);
					$Consigne->setDisplay('slider_placeholder', 'Temperature en °c ex');
					$Consigne->setConfiguration('maxValue', 30);
					$Consigne->setConfiguration('minValue', 10);
					$Consigne->setOrder(5);
					$Consigne->save();
			   }
               
                $RoomTemperature2 = $this->getCmd(null, 'RoomTemperatureZone2');
			   if (!is_object($RoomTemperature2)) {
				   $RoomTemperature2 = new melcloudCmd();
				   $RoomTemperature2->setName('Temp 2');
				   $RoomTemperature2->setEqLogic_id($this->getId());
				   $RoomTemperature2->setLogicalId('RoomTemperatureZone2');
				   $RoomTemperature2->setType('info');
				   $RoomTemperature2->setSubType('numeric');
				   $RoomTemperature2->setIsHistorized(0);
				   $RoomTemperature2->setIsVisible(1);
				   $RoomTemperature2->setUnite('°C');
				   $RoomTemperature2->setTemplate('dashboard', 'line');
				   $RoomTemperature2->setOrder(6);
				   $RoomTemperature2->setValue(0);
				   $RoomTemperature2->event(0);
				   $RoomTemperature2->save();
			   }
               
			    $Consigne2 = $this->getCmd(null, 'SetTemperatureZone2');
			   if (!is_object($Consigne2)) {
					$Consigne2 = new melcloudCmd();
					$Consigne2->setName('Consigne 2');
					$Consigne2->setEqLogic_id($this->getId());
					$Consigne2->setLogicalId('SetTemperatureZone2');
					$Consigne2->setType('action');
					$Consigne2->setTemplate('dashboard', 'button');
					$Consigne2->setSubType('slider');
					$Consigne2->setIsHistorized(0);
					$Consigne2->setUnite('°C');
					$Consigne2->setIsVisible(1);
					$Consigne2->setDisplay('slider_placeholder', 'Temperature en °c ex');
					$Consigne2->setConfiguration('maxValue', 30);
					$Consigne2->setConfiguration('minValue', 10);
					$Consigne2->setOrder(7);
					$Consigne2->save();
			   }

              
			   $ForcedHotWaterMode = $this->getCmd(null, 'ForcedHotWaterMode');
			   if (!is_object($ForcedHotWaterMode)) {
				   $ForcedHotWaterMode = new melcloudCmd();
				   $ForcedHotWaterMode->setName('Eau Chaude Force');
				   $ForcedHotWaterMode->setEqLogic_id($this->getId());
				   $ForcedHotWaterMode->setLogicalId('ForcedHotWaterMode');
				   $ForcedHotWaterMode->setType('action');
				   $ForcedHotWaterMode->setSubType('other');
				   $ForcedHotWaterMode->setTemplate('dashboard', 'prise');
				   $ForcedHotWaterMode->setIsHistorized(0);
				   $ForcedHotWaterMode->setIsVisible(1);
				   $ForcedHotWaterMode->setOrder(8);
				   $ForcedHotWaterMode->setDisplay('showNameOndashboard','1');
				   $ForcedHotWaterMode->save();
			   }
               
               
			   $temphotwater = $this->getCmd(null, 'TankWaterTemperature');
			   if (!is_object($temphotwater)) {
				   $temphotwater = new melcloudCmd();
				   $temphotwater->setName('T° eau chaude');
				   $temphotwater->setEqLogic_id($this->getId());
				   $temphotwater->setLogicalId('TankWaterTemperature');
				   $temphotwater->setType('info');
				   $temphotwater->setSubType('numeric');
				   $temphotwater->setIsHistorized(0);
				   $temphotwater->setIsVisible(1);
				   $temphotwater->setUnite('°C');
				   $temphotwater->setOrder(9);
				   $temphotwater->setValue(0);
				   $temphotwater->event(0);
				   $temphotwater->save();
			   }
             
				$refresh = $this->getCmd(null, 'refresh');
			   if (!is_object($refresh)) {
				   $refresh = new melcloudCmd();
				   $refresh->setLogicalId('refresh');
				   $refresh->setIsVisible(1);
				   $refresh->setName('Rafraichir');
				   $refresh->setEqLogic_id($this->getId());
				   $refresh->setType('action');
				   $refresh->setSubType('other');
				   $refresh->setOrder(10);
				   $refresh->save();
			   }

			   
			   $lien = $this->getCmd(null, 'lienmelcloud');
			   if (!is_object($lien)) {
				   $lien = new melcloudCmd();
				   $lien->setLogicalId('lienmelcloud');
				   $lien->setIsVisible(1);
				   $lien->setName('Site Melcloud');
				   $lien->setEqLogic_id($this->getId());
				   $lien->setType('action');
				   $lien->setSubType('other');
				   $lien->setOrder(99);
				   $lien->setHtml('enable','1');
				   $lien->setHtml('dashboard','<br><br><i class="icon maison-home63"> </i><a href="https://app.melcloud.com" target="_blank">#name_display#</a>');
				   $lien->save();
			   }


             }
         }

    }

    public function preUpdate()
    {

    }

    public function postUpdate()
    {

    }

    public function preRemove()
    {

    }

    public function postRemove()
    {

    }


    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class melcloudCmd extends cmd
{
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array())
    {
      
      
        if ('Consigne' ==  $this->name || 'Consigne' == $this->getLogicalId()) {

            if (isset($_options['slider']) && isset($_options['auto']) == false) {
                melcloud::SetModif($_options['slider'], $this->getEqLogic(),'SetTemperature',4);
            }
        }
        
        if ('ForcedHotWaterMode' == $this->getLogicalId()) {
          
          if ($this->getLastValue() == 0){
            melcloud::SetModif('true', $this->getEqLogic(),'ForcedHotWaterMode',1);
          }else{
            melcloud::SetModif('false', $this->getEqLogic(),'ForcedHotWaterMode',0);
          }
          
        }
      
        if ('SetTemperatureZone1' == $this->getLogicalId()) {
            if (isset($_options['slider'])) {
                melcloud::SetModif($_options['slider'], $this->getEqLogic(),'SetTemperatureZone1',8589934592);
            }
        }
      
      	if ('SetTemperatureZone2' == $this->getLogicalId()) {
            if (isset($_options['slider'])) {
                melcloud::SetModif($_options['slider'], $this->getEqLogic(),'SetTemperatureZone2',34359738880);
            }
        }
      
        if ('On' == $this->getLogicalId()) {
        	melcloud::SetModif('true', $this->getEqLogic(),'Power',1);
        }
        if ('Off' == $this->getLogicalId()) {
        	melcloud::SetModif('false', $this->getEqLogic(),'Power',1);
        }

        if ('On/Off' == $this->name || 'On/Off' == $this->getLogicalId() || 'Power' == $this->getLogicalId() ) {

            if (isset($_options['slider'])) {

                $newPower = $_options['slider'];
                if (0 == $_options['slider']) {
                    melcloud::SetModif('false', $this->getEqLogic(),'Power',1);
                } else {
                    melcloud::SetModif('true', $this->getEqLogic(),'Power',1);
                }
              
            }else{
              
              	if ($this->getLastValue() == 0){
                   melcloud::SetModif('true', $this->getEqLogic(),'Power',1);
                }else{
                   melcloud::SetModif('false', $this->getEqLogic(),'Power',1);
                }
            }

        }

        if ('Ventilation' == $this->name || 'Ventilation' == $this->getLogicalId()) {
            if (isset($_options['slider'])) {
                $newFanSpeed = $_options['slider'];
                melcloud::SetModif($newFanSpeed, $this->getEqLogic(),'SetFanSpeed',8);
            }
        }
      
        if ('ModeAuto' == $this->getLogicalId()) {
        	melcloud::SetModif(8, $this->getEqLogic(),'OperationMode',6);
        }
        if ('Ventile' == $this->getLogicalId()) {
        	melcloud::SetModif(7, $this->getEqLogic(),'OperationMode',6);
        }
      	if ('Froid' == $this->getLogicalId()) {
        	melcloud::SetModif(3, $this->getEqLogic(),'OperationMode',6);
        }
      	if ('Chauffage' == $this->getLogicalId()) {
        	melcloud::SetModif(1, $this->getEqLogic(),'OperationMode',6);
        }
        if ('sechage' == $this->getLogicalId()) {
        	melcloud::SetModif(2, $this->getEqLogic(),'OperationMode',6);
        }
        if ('Mode' == $this->name || 'Mode' == $this->getLogicalId()) {
            if (isset($_options['slider'])) {
                melcloud::SetModif($_options['slider'], $this->getEqLogic(),'OperationMode',6);
            }
        }

        if ('Rafraichir' == $this->name || 'Rafraichir' == $this->getLogicalId() ||'refresh' == $this->getLogicalId()) {
            melcloud::pull();
        }
      
      
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
