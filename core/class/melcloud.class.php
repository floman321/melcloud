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
     
        log::add('melcloud', 'info', 'Modification'.$flag.' '.$idflag);

        $montoken = config::byKey('MyToken', 'melcloud', '');

        if ($montoken != '') {

            $devideid = $mylogical->getConfiguration('deviceid');
            $buildid = $mylogical->getConfiguration('buildid');

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
            curl_setopt($ch, CURLOPT_URL, "https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta");
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
                log::add('melcloud', 'info', 'setdevice ' . $device['Device']['DeviceID']);
                $mylogical->setConfiguration('deviceid', $device['Device']['DeviceID']);
                $mylogical->setConfiguration('buildid', $device['BuildingID']);
                $mylogical->save();
              
                
                foreach ($mylogical->getCmd() as $cmd) {
                    
                    switch ($cmd->getLogicalId()) {
                        
                        case 'FanSpeed':
                          $cmd->setConfiguration('lastCmdValue',$device['Device']['FanSpeed']);
                          $cmd->save(); 
                        break;
                      
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
                        
                        case 'Power':
                          $cmd->setConfiguration('lastCmdValue',$device['Device']['Power']);
                          $cmd->save(); 
	
                        break;
                        
                        case 'SetTemperature':
                          $cmd->setConfiguration('lastCmdValue',$device['Device']['SetTemperature']);
                          $cmd->save(); 
                        
                        break;
                        
                        case 'Rafraichir':
                            log::add('melcloud', 'debug', 'log ' . $cmd->getLogicalId() . ' .On ne traite pas cette commande');
                            break;
                        default:
                            log::add('melcloud', 'debug', 'log '.$cmd->getName().' ' . $cmd->getLogicalId() . ' ' . $device['Device'][$cmd->getLogicalId()]);
                            if ('LastTimeStamp' == $cmd->getLogicalId()) {
                                $cmd->event(str_replace('T', ' ', $device['Device'][$cmd->getLogicalId()]));
                            } else {
                                $cmd->setCollectDate('');
                                $cmd->event($device['Device'][$cmd->getLogicalId()]);
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



        $refresh = new melcloudCmd();
        $refresh->setLogicalId('refresh');
        $refresh->setIsVisible(1);
        $refresh->setName('Rafraichir');
        $refresh->setEqLogic_id($this->getId());
        $refresh->setType('action');
        $refresh->setSubType('other');
        $refresh->setOrder(11);
        $refresh->save();
      
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
        $ventile->save();
      
      
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
      
      
        $refresh = new melcloudCmd();
          $refresh->setLogicalId('lienmelcloud');
          $refresh->setIsVisible(1);
          $refresh->setName('Site Melcloud');
          $refresh->setEqLogic_id($this->getId());
          $refresh->setType('action');
          $refresh->setSubType('other');
          $refresh->setOrder(17);
          $refresh->setHtml('enable','1');
          $refresh->setHtml('dashboard','<br><br><i class="icon maison-home63"> </i><a href="https://app.melcloud.com" target="_blank">#name_display#</a>');
          $refresh->save();
      
      
       $refresh = new melcloudCmd();
          $refresh->setLogicalId('sechage');
          $refresh->setIsVisible(1);
          $refresh->setName('Mode Séchage');
          $refresh->setEqLogic_id($this->getId());
          $refresh->setType('action');
          $refresh->setSubType('other');
          $refresh->setOrder(16);
          $refresh->setDisplay('showIconAndNamedashboard','1');
          $refresh->setDisplay('icon','<i class="icon jeedom-ventilo"></i>');
          $refresh->save();
      
    }

    public function preSave()
    {
      
          
	    
	    
	    
    }

    public function postSave()
    {

      

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
      
        if ('On' == $this->getLogicalId()) {
        	melcloud::SetModif('true', $this->getEqLogic(),'Power',1);
        }
        if ('Off' == $this->getLogicalId()) {
        	melcloud::SetModif('false', $this->getEqLogic(),'Power',1);
        }

        if ('On/Off' == $this->name || 'On/Off' == $this->getLogicalId() || 'Power' == $this->getLogicalId() ) {

            if (isset($_options['slider']) && isset($_options['auto']) == false) {

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
                   melcloud::SetModif('false', $this->getEqLogic(),'Power',0);
                }
            }

        }

        if ('Ventilation' == $this->name || 'Ventilation' == $this->getLogicalId()) {
            if (isset($_options['slider']) && isset($_options['auto']) == false) {
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
            if (isset($_options['slider']) && isset($_options['auto']) == false) {
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
