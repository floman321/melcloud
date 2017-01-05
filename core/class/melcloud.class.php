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

class melcloud extends eqLogic {
    /*     * *************************Attributs****************************** */



    /*     * ***********************Methode static*************************** */
  
    public static function SetPower($option,$mylogical) {
        
      log::add('melcloud', 'info', 'SetPower');
      
        $montoken = config::byKey('MyToken', 'melcloud','');
        
        if ($montoken != ''){
                    
            $devideid = $mylogical->getConfiguration('deviceid');
            $buildid = $mylogical->getConfiguration('buildid');
          
            $request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id='.$devideid.'&buildingID='.$buildid);
            $request->setHeader(array('X-MitsContextKey: '.$montoken));
            $json = $request->exec(30000, 2);
            $device = json_decode($json,true);  
          
            $device['Power'] = $option;
            $device['EffectiveFlags'] = '1';
            $device['HasPendingCommand'] = 'true';
          
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,"https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta");
            curl_setopt($ch, CURLOPT_POST, 1);
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-MitsContextKey: '.$montoken,
            'content-type: application/json'
            ));
          
            curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($device));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec ($ch);
            curl_close ($ch);
            $json = json_decode($server_output, true);
          
            foreach ($mylogical->getCmd() as $cmd) {
                $v = $cmd->getName();
                if ($v == 'NextCommunication'){
                    $cmd->setCollectDate('');
                    $time = strtotime($json['NextCommunication']." + 1 hours"); // Add 1 hour
                    $time = date('G:i:s', $time); // Back to string
                    $cmd->event($time); 
                }
            }
      }
      
    }
  
    public static function SetFan($option,$mylogical) {
        
      log::add('melcloud', 'info', 'SetFan');
      
       $montoken = config::byKey('MyToken', 'melcloud','');
      
        if ($montoken != ''){
            
            $montoken = config::byKey('MyToken', 'melcloud','');
                    
            $devideid = $mylogical->getConfiguration('deviceid');
            $buildid = $mylogical->getConfiguration('buildid');
          
            $request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id='.$devideid.'&buildingID='.$buildid);
            $request->setHeader(array('X-MitsContextKey: '.$montoken));
            $json = $request->exec(30000, 2);
            $device = json_decode($json,true);  
          
            $device['SetFanSpeed'] = $option;
            $device['EffectiveFlags'] = '8';
            $device['HasPendingCommand'] = 'true';
          
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,"https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta");
            curl_setopt($ch, CURLOPT_POST, 1);
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-MitsContextKey: '.$montoken,
            'content-type: application/json'
            ));
          
            curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($device));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec ($ch);
            curl_close ($ch);
            $json = json_decode($server_output, true);
          
            foreach ($mylogical->getCmd() as $cmd) {
                $v = $cmd->getName();
                if ($v == 'NextCommunication'){
                    $cmd->setCollectDate('');
                    $time = strtotime($json['NextCommunication']." + 1 hours"); // Add 1 hour
                    $time = date('G:i:s', $time); // Back to string
                    $cmd->event($time); 
                }
            }
              
      }
      
      return 'oups';
    }
  
    public static function SetTemp($newtemp,$mylogical) {
        
      log::add('melcloud', 'info', 'SetTemp'.$newtemp);
      
        $montoken = config::byKey('MyToken', 'melcloud','');
      
        if ($montoken != ''){
                    
            $devideid = $mylogical->getConfiguration('deviceid');
            $buildid = $mylogical->getConfiguration('buildid');
          
            $request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id='.$devideid.'&buildingID='.$buildid);
            $request->setHeader(array('X-MitsContextKey: '.$montoken));
            $json = $request->exec(30000, 2);
            $device = json_decode($json,true);  
          
            $device['SetTemperature'] = $newtemp;
            $device['EffectiveFlags'] = '4';
            $device['HasPendingCommand'] = 'true';
          
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,"https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta");
            curl_setopt($ch, CURLOPT_POST, 1);
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-MitsContextKey: '.$montoken,
            'content-type: application/json'
            ));
          
            curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($device));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec ($ch);
          
            curl_close ($ch);
            $json = json_decode($server_output, true);
          
            foreach ($mylogical->getCmd() as $cmd) {
                $v = $cmd->getName();
                if ($v == 'NextCommunication'){
                    $cmd->setCollectDate('');
									
                  $time = strtotime($json['NextCommunication']." + 1 hours"); // Add 1 hour
                  $time = date('G:i:s', $time); // Back to string
                  
                    $cmd->event($time); 
                }
            }
              
      }
      
      return 'oups';
    }
    
    
    
    public static function SetMode($newmode,$mylogical) {
        
        log::add('melcloud', 'info', 'SetMode'.$newmode);
        
        $montoken = config::byKey('MyToken', 'melcloud','');
      
        if ($montoken != ''){
            
            $montoken = config::byKey('MyToken', 'melcloud','');
            
            $devideid = $mylogical->getConfiguration('deviceid');
            $buildid = $mylogical->getConfiguration('buildid');
            
            $request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id='.$devideid.'&buildingID='.$buildid);
            $request->setHeader(array('X-MitsContextKey: '.$montoken));
            $json = $request->exec(30000, 2);
            $device = json_decode($json,true);
            
            // Mode value : 1 warm, 2 dry, 3 cool, 7 vent, 8 auto
            $device['OperationMode'] = $newmode;
            $device['EffectiveFlags'] = '6';
            $device['HasPendingCommand'] = 'true';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,"https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta");
            curl_setopt($ch, CURLOPT_POST, 1);
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                                       'X-MitsContextKey: '.$montoken,
                                                       'content-type: application/json'
                                                       ));
            
            curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($device));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec ($ch);
            curl_close ($ch);
            $json = json_decode($server_output, true);
            
            foreach ($mylogical->getCmd() as $cmd) {
                $v = $cmd->getName();
                if ($v == 'NextCommunication'){
                    $cmd->setCollectDate('');
                    $cmd->event($json['NextCommunication']);
                }
            }
            
        }
        
        return 'oups';
    }
  
  
   public static function gettoken() {
     
      $myemail = config::byKey('MyEmail', 'melcloud');
              $monpass = config::byKey('MyPassword', 'melcloud');
              
              $ch = curl_init();
              curl_setopt($ch, CURLOPT_URL,"https://app.melcloud.com/Mitsubishi.Wifi.Client/Login/ClientLogin");
              curl_setopt($ch, CURLOPT_POST, 1);
              curl_setopt($ch, CURLOPT_POSTFIELDS,
                          "Email=".$myemail."&Password=".$monpass."&Language=7&AppVersion=1.10.1.0&Persist=true&CaptchaChallenge=null&CaptchaChallenge=null");
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
              $server_output = curl_exec ($ch);
              curl_close ($ch);
              
              $json = json_decode($server_output, true);
              
              if ($json['ErrorId'] == null){
                  log::add('melcloud', 'info', 'Login ok ');
                  config::save("MyToken", $json['LoginData']['ContextKey'], 'melcloud');
              }else{
                  log::add('melcloud', 'info', 'Login ou mot de passe Melcloud incorrecte.');
                  config::save("MyToken", $json['ErrorId'], 'melcloud');
              }  
     
   }
    
    
    public static function pull() {
      
        $montoken = config::byKey('MyToken', 'melcloud','');
      
        if ($montoken != ''){
			
			log::add('melcloud', 'info', 'pull 5 minutes mytoken ='.$montoken);
            
            //$request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id='.$devideid.'&buildingID='.$buildid);
            
            $request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/User/ListDevices');
            $request->setHeader(array('X-MitsContextKey: '.$montoken));
            $json = $request->exec(30000, 2);
            $values = json_decode($json,true);
            
            foreach ($values as $maison){
                
                    log::add('melcloud', 'info', 'Maison '.$maison['Name']);
                    
                    for ($i=0; $i < count($maison['Structure']['Devices']) ; $i++) {
                      
                      log::add('melcloud', 'info', 'pull : device 1 '.$i.' '.$device['DeviceName']);
                        
                        $device = $maison['Structure']['Devices'][$i];
                        
                        if ($device['DeviceID'] == '') continue;
                        
                        log::add('melcloud', 'info', $i.' =>'.$device['DeviceID'].' '.$device['DeviceName']);
                        
                        foreach (eqLogic::byType('melcloud',true) as $mylogical){
                          
                            if ($mylogical->getConfiguration('namemachine') != $device['DeviceName'] ) continue;
                          	
                                 log::add('melcloud', 'info', 'setdevice '.$device['Device']['DeviceID']);
                                  
                                  $mylogical->setConfiguration('deviceid',$device['Device']['DeviceID']);
                                  $mylogical->setConfiguration('buildid',$device['BuildingID']);
                                  $mylogical->save();
                            
                                    foreach ($mylogical->getCmd() as $cmd) {
                                        
                                        $v = $cmd->getName();
                                        
                                        $cmd->setCollectDate('');
                                        $cmd->event($device['Device'][$v]);
                                      
                                     // log::add('melcloud', 'info', '0 -'.$v);
                                      
                                        if ($v == "SetTemperature"){
                                          
                                            log::add('melcloud', 'info', '1');
                                        	cmd::byEqLogicIdCmdName($mylogical->getId(),'Consigne')->execCmd($options=array('auto'=>'vrai', 'slider'=>$device['Device'][$v] ), $cache=0);

                                            log::add('melcloud', 'info', '2 **'.$device['Device'][$v]);
                                          
                                        }
                                        
                                    }
                          
                          $mylogical->Refresh();
                        }
                      
                      
                    }
              
              
              
              
              			// FLOORS
						 for ($a=0; $a < count($maison['Structure']['Floors']) ; $a++) {
							 
							 log::add('melcloud', 'info', 'FLOORS '.$a);
							 
							 // AREAS IN FLOORS
 							 for ($i=0; $i < count($maison['Structure']['Floors'][$a]['Areas']) ; $i++) {
								
								for ($d=0; $d < count($maison['Structure']['Floors'][$a]['Areas'][$i]['Devices']) ; $d++) {

									$device = $maison['Structure']['Floors'][$a]['Areas'][$i]['Devices'][$d];
								
									foreach (eqLogic::byType('melcloud',true) as $mylogical){

										if ($mylogical->getConfiguration('namemachine') != $device['DeviceName'] ) continue;

											 log::add('melcloud', 'info', 'setdevice '.$device['Device']['DeviceID']);

											  $mylogical->setConfiguration('deviceid',$device['Device']['DeviceID']);
											  $mylogical->setConfiguration('buildid',$device['BuildingID']);
											  $mylogical->save();

												foreach ($mylogical->getCmd() as $cmd) {

													$v = $cmd->getName();

													$cmd->setCollectDate('');
													$cmd->event($device['Device'][$v]);

												}
                                      
                                      $mylogical->Refresh();
									}
									
								}
							 }
						
							// FLOORS
							 for ($i=0; $i < count($maison['Structure']['Floors'][$a]['Devices']) ; $i++) {
							
									$device = $maison['Structure']['Floors'][$a]['Devices'][$i];

									if ($device['DeviceID'] == '') continue;

									log::add('melcloud', 'info', $i.' =>'.$device['DeviceID'].' '.$device['DeviceName']);

									foreach (eqLogic::byType('melcloud',true) as $mylogical){

										if ($mylogical->getConfiguration('namemachine') != $device['DeviceName'] ) continue;

											 log::add('melcloud', 'info', 'setdevice '.$device['Device']['DeviceID']);

											  $mylogical->setConfiguration('deviceid',$device['Device']['DeviceID']);
											  $mylogical->setConfiguration('buildid',$device['BuildingID']);
											  $mylogical->save();

												foreach ($mylogical->getCmd() as $cmd) {

													$v = $cmd->getName();

													$cmd->setCollectDate('');
													$cmd->event($device['Device'][$v]);

												}
                                      
                                      $mylogical->Refresh();
									}
							   
								}
							 
						 }
              
              
              
            			  // AREAS
              				for ($a=0; $a < count($maison['Structure']['Areas']) ; $a++) {
                               
                               log::add('melcloud', 'info', 'AREAS '.$a);
              
                                 for ($i=0; $i < count($maison['Structure']['Areas'][$a]['Devices']) ; $i++) {

                                   log::add('melcloud', 'info', 'machine AREAS '.$i);

                                    $device = $maison['Structure']['Areas'][$a]['Devices'][$i];

                                    if ($device['DeviceID'] == '') continue;

                                    log::add('melcloud', 'info', $i.' =>'.$device['DeviceID'].' '.$device['DeviceName']);

                                    foreach (eqLogic::byType('melcloud',true) as $mylogical){

                                        if ($mylogical->getConfiguration('namemachine') != $device['DeviceName'] ) continue;

                                             log::add('melcloud', 'info', 'setdevice '.$device['Device']['DeviceID']);

                                              $mylogical->setConfiguration('deviceid',$device['Device']['DeviceID']);
                                              $mylogical->setConfiguration('buildid',$device['BuildingID']);
                                              $mylogical->save();

                                                foreach ($mylogical->getCmd() as $cmd) {

                                                    $v = $cmd->getName();

                                                    $cmd->setCollectDate('');
                                                    $cmd->event($device['Device'][$v]);

                                                }
                                      
                                      $mylogical->Refresh();
                                       }

                                 }
                            }
            }
            
            
        }
        
         
    }
    
  
    
     //Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {
          
          
        
      }
    
     //Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {
       
       
      }
     

    
     // Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDayly() {
 
      }
     
  
        
  



    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {
        
		
		
    }

    public function postInsert() {
        
        
        $RoomTemperature = null;
        $RoomTemperature = new melcloudCmd();
        $RoomTemperature->setName('RoomTemperature');
        $RoomTemperature->setEqLogic_id($this->getId());
        $RoomTemperature->setLogicalId('temperature');
        $RoomTemperature->setType('info');
        $RoomTemperature->setSubType('numeric');
        $RoomTemperature->setIsHistorized(0);
        $RoomTemperature->setIsVisible(1);
		$RoomTemperature->setUnite('°C');
        $RoomTemperature->setTemplate('dashboard','tile');
        $RoomTemperature->save();
        $RoomTemperature->setValue(0);
        $RoomTemperature->event(0);
		
		$SetTemperature = null;
        $SetTemperature = new melcloudCmd();
        $SetTemperature->setName('SetTemperature');
        $SetTemperature->setEqLogic_id($this->getId());
        $SetTemperature->setLogicalId('temperature');
        $SetTemperature->setType('info');
        $SetTemperature->setSubType('numeric');
        $SetTemperature->setIsHistorized(0);
		$SetTemperature->setUnite('°C');
        $SetTemperature->setTemplate('dashboard','tile');
        $SetTemperature->setIsVisible(1);
        $SetTemperature->save();
        $SetTemperature->setValue(0);
        $SetTemperature->event(0);
		
		
		
		$Consigne = null;
        $Consigne = new melcloudCmd();
        $Consigne->setName('Consigne');
        $Consigne->setEqLogic_id($this->getId());
        $Consigne->setLogicalId('temperature');
        $Consigne->setType('action');
        $Consigne->setTemplate('dashboard','thermostat');
        $Consigne->setSubType('slider');
        $Consigne->setIsHistorized(0);
	$Consigne->setUnite('°C');
	$Consigne->setConfiguration('maxValue', 30);
	$Consigne->setConfiguration('minValue', 10);
        $Consigne->setIsVisible(1);
        $Consigne->save();
		
		
		$onoff = null;
        $onoff = new melcloudCmd();
        $onoff->setName('On/Off');
        $onoff->setEqLogic_id($this->getId());
        $onoff->setType('action');
        $onoff->setSubType('slider');
        $onoff->setIsHistorized(0);
        $onoff->setIsVisible(0);
        $onoff->save();
      
      
      	$ventilation = null;
        $ventilation = new melcloudCmd();
        $ventilation->setName('Ventilation');
        $ventilation->setEqLogic_id($this->getId());
        $ventilation->setType('action');
        $ventilation->setSubType('slider');
        $ventilation->setIsHistorized(0);
        $ventilation->setTemplate('dashboard','thermostat');
        $ventilation->setIsVisible(0);
        $ventilation->save();
        
        $mode = null;
        $mode = new melcloudCmd();
        $mode->setName('Mode');
        $mode->setEqLogic_id($this->getId());
        $mode->setType('action');
        $mode->setSubType('slider');
        $mode->setIsHistorized(0);
        $mode->setIsVisible(0);
        $mode->save();
      
      
        $ActualFanSpeed = null;
        $ActualFanSpeed = new melcloudCmd();
        $ActualFanSpeed->setName('ActualFanSpeed');
        $ActualFanSpeed->setEqLogic_id($this->getId());
        $ActualFanSpeed->setLogicalId('ActualFanSpeed');
        $ActualFanSpeed->setType('info');
        $ActualFanSpeed->setSubType('numeric');
        $ActualFanSpeed->setIsHistorized(0);
        $ActualFanSpeed->setTemplate('dashboard','tile');
        $ActualFanSpeed->setIsVisible(1);
        $ActualFanSpeed->save();
        $ActualFanSpeed->setValue(0);
        $ActualFanSpeed->event(0);
      
        $FanSpeed = null;
        $FanSpeed = new melcloudCmd();
        $FanSpeed->setName('FanSpeed');
        $FanSpeed->setEqLogic_id($this->getId());
        $FanSpeed->setLogicalId('FanSpeed');
        $FanSpeed->setType('info');
        $FanSpeed->setSubType('numeric');
        $FanSpeed->setIsHistorized(0);
        $FanSpeed->setTemplate('dashboard','tile');
        $FanSpeed->setIsVisible(1);
        $FanSpeed->save();
        $FanSpeed->setValue(0);
        $FanSpeed->event(0);
      
		
    }

    public function preSave() {
        
    }

    public function postSave() {
     
      
      
    }

    public function preUpdate() {
        
    }

    public function postUpdate() {
        
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }


    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class melcloudCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array()) {
      
      $myname = $this->name;
      
      $myeqLogic = $this->getEqLogic();
      
      if ( $myname == 'Consigne' ){
        
        if (isset($_options['slider']) && isset($_options['auto']) == false ) {
            
        	$newTemp = $_options['slider'];
            melcloud::SetTemp($newTemp,$myeqLogic);
            
      	}
      }
      
      if ( $myname == 'On/Off' ){
       
        if (isset($_options['slider'])){
            
        	$newPower = $_options['slider'];
          
            if ($newPower == 0){
                melcloud::SetPower('false',$myeqLogic);
            }
            if ($newPower == 1){
                melcloud::SetPower('true',$myeqLogic);
            }
            
      	}
          
      }
      
        if ( $myname == 'Ventilation' ){
           if (isset($_options['slider'])){
          
            $newFanSpeed = $_options['slider'];
            melcloud::SetFan($newFanSpeed,$myeqLogic);
                         
      	   }
        }
        
        if ( $myname == 'Mode' ){
            if (isset($_options['slider'])){
                
                $newMode = $_options['slider'];
                melcloud::SetMode($newMode,$myeqLogic);
            }
        }
      
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
