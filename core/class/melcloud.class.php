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

    public static function SetPower($option, $mylogical)
    {

        log::add('melcloud', 'info', 'SetPower');

        $montoken = config::byKey('MyToken', 'melcloud', '');

        if ($montoken != '') {

            $devideid = $mylogical->getConfiguration('deviceid');
            $buildid = $mylogical->getConfiguration('buildid');

            $request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id=' . $devideid . '&buildingID=' . $buildid);
            $request->setHeader(array('X-MitsContextKey: ' . $montoken));
            $json = $request->exec(30000, 2);
            $device = json_decode($json, true);

            $device['Power'] = $option;
            $device['EffectiveFlags'] = '1';
            $device['HasPendingCommand'] = 'true';

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
                if ('NextCommunication' == $cmd->getLogicalId()) {
                    $cmd->setCollectDate('');
                    $time = strtotime($json['NextCommunication'] . " + 1 hours"); // Add 1 hour
                    $time = date('G:i:s', $time); // Back to string
                    $cmd->event($time);
                }
            }
        }

    }

    public static function SetFan($option, $mylogical)
    {

        log::add('melcloud', 'info', 'SetFan');

        $montoken = config::byKey('MyToken', 'melcloud', '');

        if ($montoken != '') {

            $montoken = config::byKey('MyToken', 'melcloud', '');

            $devideid = $mylogical->getConfiguration('deviceid');
            $buildid = $mylogical->getConfiguration('buildid');

            $request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id=' . $devideid . '&buildingID=' . $buildid);
            $request->setHeader(array('X-MitsContextKey: ' . $montoken));
            $json = $request->exec(30000, 2);
            $device = json_decode($json, true);

            $device['SetFanSpeed'] = $option;
            $device['EffectiveFlags'] = '8';
            $device['HasPendingCommand'] = 'true';

            if ($option == '0') {
                cmd::byEqLogicIdCmdName($mylogical->getId(), 'ActualFanSpeed')->setDisplay('showOndashboard', '1');
            } else {
                cmd::byEqLogicIdCmdName($mylogical->getId(), 'ActualFanSpeed')->setDisplay('showOndashboard', '0');
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
                if ('NextCommunication' == $cmd->getLogicalId()) {
                    $cmd->setCollectDate('');
                    $time = strtotime($json['NextCommunication'] . " + 1 hours"); // Add 1 hour
                    $time = date('G:i:s', $time); // Back to string
                    $cmd->event($time);
                }
            }

        }
    }

    public static function SetTemp($newtemp, $mylogical)
    {

        log::add('melcloud', 'info', 'SetTemp' . $newtemp);

        $montoken = config::byKey('MyToken', 'melcloud', '');

        if ($montoken != '') {

            $devideid = $mylogical->getConfiguration('deviceid');
            $buildid = $mylogical->getConfiguration('buildid');

            $request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id=' . $devideid . '&buildingID=' . $buildid);
            $request->setHeader(array('X-MitsContextKey: ' . $montoken));
            $json = $request->exec(30000, 2);
            $device = json_decode($json, true);

            $device['SetTemperature'] = $newtemp;
            $device['EffectiveFlags'] = '4';
            $device['HasPendingCommand'] = 'true';

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
                if ('NextCommunication' == $cmd->getLogicalId()) {
                    $cmd->setCollectDate('');
                    $time = strtotime($json['NextCommunication'] . " + 1 hours"); // Add 1 hour
                    $time = date('G:i:s', $time); // Back to string
                    $cmd->event($time);
                }
            }

        }
    }


    public static function SetMode($newmode, $mylogical)
    {

        log::add('melcloud', 'info', 'SetMode' . $newmode);

        $montoken = config::byKey('MyToken', 'melcloud', '');

        if ($montoken != '') {

            $devideid = $mylogical->getConfiguration('deviceid');
            $buildid = $mylogical->getConfiguration('buildid');

            $request = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id=' . $devideid . '&buildingID=' . $buildid);
            $request->setHeader(array('X-MitsContextKey: ' . $montoken));
            $json = $request->exec(30000, 2);
            $device = json_decode($json, true);

            // Mode value : 1 warm, 2 dry, 3 cool, 7 vent, 8 auto
            $device['OperationMode'] = $newmode;
            $device['EffectiveFlags'] = '6';
            $device['HasPendingCommand'] = 'true';

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
                if ('NextCommunication' == $cmd->getLogicalId()) {
                    $cmd->setCollectDate('');
                    $cmd->event($json['NextCommunication']);
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
                cmd::byEqLogicIdCmdName($mylogical->getId(), 'Ventilation')->execCmd($options = array('auto' => 'vrai', 'slider' => $device['Device']['FanSpeed']), $cache = 0);
                cmd::byEqLogicIdCmdName($mylogical->getId(), 'On/Off')->execCmd($options = array('auto' => 'vrai', 'slider' => intval($device['Device']['Power'])), $cache = 0);
                cmd::byEqLogicIdCmdName($mylogical->getId(), 'Consigne')->execCmd($options = array('auto' => 'vrai', 'slider' => $device['Device']['SetTemperature']), $cache = 0);
                cmd::byEqLogicIdCmdName($mylogical->getId(), 'Mode')->execCmd($options = array('auto' => 'vrai', 'slider' => $device['Device']['OperationMode']), $cache = 0);
                foreach ($mylogical->getCmd() as $cmd) {
                    //il faut exclure le on/off
                    switch ($cmd->getLogicalId()) {
                        case 'OnOff':
                            log::add('melcloud', 'debug', 'log ' . $cmd->getLogicalId() . ' ' . $device['Device']['Power']);
                            $cmd->setCollectDate('');
                            $cmd->event($device['Device']['Power']);
                            break;
                        case 'Mode':
                            log::add('melcloud', 'debug', 'log ' . $cmd->getLogicalId() . ' ' . $device['Device']['OperationMode']);
                            $cmd->setCollectDate('');
                            $cmd->event($device['Device']['OperationMode']);
                            break;
                        case 'Ventilation':
                            log::add('melcloud', 'debug', 'log ' . $cmd->getLogicalId() . ' ' . $device['Device']['FanSpeed']);
                            $cmd->setCollectDate('');
                            $cmd->event($device['Device']['FanSpeed']);
                            break;
                        case 'Consigne':
                            log::add('melcloud', 'debug', 'log ' . $cmd->getLogicalId() . ' ' . $device['Device']['SetTemperature']);
                            $cmd->setCollectDate('');
                            $cmd->event($device['Device']['SetTemperature']);
                            break;
                        case 'refresh':
                        case 'CurrentWeather':
                            log::add('melcloud', 'debug', 'log ' . $cmd->getLogicalId() . ' .On ne traite pas cette commande');
                            break;
                        default:
                            log::add('melcloud', 'debug', 'log ' . $cmd->getLogicalId() . ' ' . $device['Device'][$cmd->getLogicalId()]);
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
                self::obtenirInfo($mylogical);
                $mylogical->Refresh();
                $mylogical->toHtml('dashboard');
                $mylogical->refreshWidget();
            }
        }
    }

    public static function obtenirInfo($mylogical)
    {
        log::add('melcloud', 'debug', 'Obtenir Info pour la machine:  ' . $mylogical->getConfiguration('namemachine'));
        $montoken = config::byKey('MyToken', 'melcloud', '');
        if ($montoken != '') {
            $devideid = $mylogical->getConfiguration('deviceid');
            $buildid = $mylogical->getConfiguration('buildid');
            $req = new com_http('https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id=' . $devideid . '&buildingID=' . $buildid);
            $req->setHeader(array('X-MitsContextKey: ' . $montoken));
            $json = $req->exec(30, 2);
            $device = json_decode($json, true);
            log::add('melcloud', 'debug', 'Retour des informations de obtenirInfo ' . print_r($device,true));
            if(isset($device['WeatherObservations'])) {
                $cmdCurrent = $mylogical->getCmd(null, 'CurrentWeather');
                if(is_object($cmdCurrent)) {
                    log::add('melcloud', 'debug', 'WeatherObservations all = ' . json_encode($device['WeatherObservations'][0]));
                    $cmdCurrent->event(json_encode($device['WeatherObservations'][0]));
                }
            }
        }
        $mylogical->Refresh();
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
        $RoomTemperature->setName('RoomTemperature');
        $RoomTemperature->setEqLogic_id($this->getId());
        $RoomTemperature->setLogicalId('RoomTemperature');
        $RoomTemperature->setType('info');
        $RoomTemperature->setSubType('numeric');
        $RoomTemperature->setIsHistorized(0);
        $RoomTemperature->setIsVisible(1);
        $RoomTemperature->setUnite('°C');
        $RoomTemperature->setTemplate('dashboard', 'tile');
        $RoomTemperature->save();
        $RoomTemperature->setValue(0);
        $RoomTemperature->event(0);

        $SetTemperature = new melcloudCmd();
        $SetTemperature->setName('SetTemperature');
        $SetTemperature->setEqLogic_id($this->getId());
        $SetTemperature->setLogicalId('SetTemperature');
        $SetTemperature->setType('info');
        $SetTemperature->setSubType('numeric');
        $SetTemperature->setIsHistorized(0);
        $SetTemperature->setUnite('°C');
        $SetTemperature->setTemplate('dashboard', 'tile');
        $SetTemperature->setIsVisible(1);
        $SetTemperature->save();
        $SetTemperature->setValue(0);
        $SetTemperature->event(0);


        $Consigne = new melcloudCmd();
        $Consigne->setName('Consigne');
        $Consigne->setEqLogic_id($this->getId());
        $Consigne->setLogicalId('Consigne');
        $Consigne->setType('action');
        $Consigne->setTemplate('dashboard', 'thermostat');
        $Consigne->setSubType('slider');
        $Consigne->setIsHistorized(0);
        $Consigne->setUnite('°C');
        $Consigne->setIsVisible(1);
        $Consigne->setDisplay('slider_placeholder', 'Temperature en °c ex');
        $Consigne->setConfiguration('maxValue', 30);
        $Consigne->setConfiguration('minValue', 10);
        $Consigne->save();


        $onoff = new melcloudCmd();
        $onoff->setName('On/Off');
        $onoff->setEqLogic_id($this->getId());
        $onoff->setLogicalId('OnOff');
        $onoff->setType('action');
        $onoff->setSubType('slider');
        $onoff->setDisplay('slider_placeholder', '1 = Allumer, 0 = Eteindre');
        $onoff->setIsHistorized(0);
        $onoff->setIsVisible(0);
        $onoff->save();


        $ventilation = new melcloudCmd();
        $ventilation->setName('Ventilation');
        $ventilation->setEqLogic_id($this->getId());
        $ventilation->setLogicalId('Ventilation');
        $ventilation->setType('action');
        $ventilation->setSubType('slider');
        $ventilation->setIsHistorized(0);
        //$ventilation->setConfiguration('listValue','0|Automatique;1|Vitesse 1;2|Vitesse 2;3|Vitesse 3;4|Vitesse 4;5|Vitesse 5');
        $ventilation->setDisplay('slider_placeholder', '0 = automatique, 1 a 5 manuel');
        $ventilation->setTemplate('dashboard', 'thermostat');
        $ventilation->setIsVisible(0);
        $ventilation->save();

        $mode = new melcloudCmd();
        $mode->setName('Mode');
        $mode->setEqLogic_id($this->getId());
        $mode->setLogicalId('Mode');
        $mode->setType('action');
        $mode->setSubType('slider');
        //$mode->setConfiguration('listValue','1|Chaud;2|Seche;3|Rafraichir;7|Ventilation;8|Auto');
        $mode->setDisplay('slider_placeholder', 'Chaud : 1 Seche : 2 Rafraichir : 3 Ventilation : 7 Auto :');
        $mode->setIsHistorized(0);
        $mode->setIsVisible(0);
        $mode->save();


        $ActualFanSpeed = new melcloudCmd();
        $ActualFanSpeed->setName('ActualFanSpeed');
        $ActualFanSpeed->setEqLogic_id($this->getId());
        $ActualFanSpeed->setLogicalId('ActualFanSpeed');
        $ActualFanSpeed->setType('info');
        $ActualFanSpeed->setSubType('numeric');
        $ActualFanSpeed->setIsHistorized(0);
        $ActualFanSpeed->setTemplate('dashboard', 'tile');
        $ActualFanSpeed->setIsVisible(1);
        $ActualFanSpeed->save();
        $ActualFanSpeed->setValue(0);
        $ActualFanSpeed->event(0);


        $FanSpeed = new melcloudCmd();
        $FanSpeed->setName('FanSpeed');
        $FanSpeed->setEqLogic_id($this->getId());
        $FanSpeed->setLogicalId('FanSpeed');
        $FanSpeed->setType('info');
        $FanSpeed->setSubType('numeric');
        $FanSpeed->setIsHistorized(0);
        $FanSpeed->setTemplate('dashboard', 'tile');
        $FanSpeed->setIsVisible(1);
        $FanSpeed->save();
        $FanSpeed->setValue(0);
        $FanSpeed->event(0);

        $refresh = new melcloudCmd();
        $refresh->setLogicalId('refresh');
        $refresh->setIsVisible(1);
        $refresh->setName('Rafraichir');
        $refresh->setEqLogic_id($this->getId());
        $refresh->setType('action');
        $refresh->setSubType('other');
        $refresh->save();

        $currentWeather = new melcloudCmd();
        $currentWeather->setName(__('Temps actuel', __FILE__));
        $currentWeather->setEqLogic_id($this->getId());
        $currentWeather->setLogicalId('CurrentWeather');
        $currentWeather->setType('info');
        $currentWeather->setSubType('string');
        $currentWeather->setConfiguration('category','actual');
        $currentWeather->setIsHistorized(0);
        $currentWeather->setDisplay('generic_type', 'WEATHER_TYPE');
        $currentWeather->setIsVisible(1);
        $currentWeather->setValue(0);
        $currentWeather->setTemplate('dashboard','CurrentWeather');
        $currentWeather->save();


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

        if ('Consigne' ==  $this->logicalId) {

            if (isset($_options['slider']) && isset($_options['auto']) == false) {
                melcloud::SetTemp($_options['slider'], $this->getEqLogic());
            }
        }

        if ('OnOff' == $this->logicalId) {

            if (isset($_options['slider']) && isset($_options['auto']) == false) {

                $newPower = $_options['slider'];
                if (0 == $_options['slider']) {
                    melcloud::SetPower('false', $this->getEqLogic());
                } else {
                    melcloud::SetPower('true', $this->getEqLogic());
                }
            }

        }

        if ('Ventilation' == $this->logicalId) {
            if (isset($_options['slider']) && isset($_options['auto']) == false) {

                $newFanSpeed = $_options['slider'];
                melcloud::SetFan($newFanSpeed, $this->getEqLogic());

            }
        }

        if ('Mode' == $this->logicalId) {
            if (isset($_options['slider']) && isset($_options['auto']) == false) {
                melcloud::SetMode($_options['slider'], $this->getEqLogic());
            }
        }

        if ('refresh' == $this->logicalId) {
            melcloud::pull();
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
