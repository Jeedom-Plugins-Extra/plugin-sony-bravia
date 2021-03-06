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

require_once __DIR__ . '/../../../../core/php/core.inc.php';

class sonybravia extends eqLogic
{

    public static function dependancy_info()
    {
        $return                  = array();
        $return['log']           = 'sonybravia_update';
        $return['progress_file'] = jeedom::getTmpFolder('sonybravia') . '/dependance';
        if (strpos(exec('python3 --version'), 'Python 3') !== false) {
            $return['state'] = 'ok';
        } else {
            $return['state'] = 'nok';
        }
        return $return;
    }

    /**
     *
     * @return type
     */
    public static function dependancy_install()
    {
        if (file_exists(jeedom::getTmpFolder('sonybravia') . '/dependance')) {
            return;
        }
        self::dependancy_force();
        log::remove(__CLASS__ . '_update');
        return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('sonybravia') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
    }

    /**
     *
     * @return array
     */
    public static function dependancy_force()
    {
        log::add('sonybravia', 'info', 'Dependancy manual install');
        return array('script' => dirname(__FILE__) . '/../../resources/install_dependancy.sh ' . jeedom::getTmpFolder('sonybravia') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
    }

    /**
     *
     * @return string
     */
    public static function deamon_info()
    {
        $return               = array();
        $return['log']        = 'sonybravia';
        $return['launchable'] = 'ok';
        $retour               = true;
        foreach (eqLogic::byType('sonybravia', true) as $eqLogic) {
            $_retour = sonybravia::tv_deamon_info($eqLogic->getLogicalId());
            if (!$_retour) {
                $retour = false;
            }
            if ($eqLogic->getConfiguration('psk') == "1234") {
                //$return['launchable'] = 'nok';
            }
        }
        if ($retour) {
            $return['state'] = 'ok';
            //$return['launchable'] = 'ok';
        } else {
            $return['state'] = 'nok';
            //$return['launchable'] = 'ok';
        }
        return $return;
    }

    /**
     *
     */
    public static function deamon_stop()
    {
        foreach (eqLogic::byType('sonybravia', true) as $eqLogic) {
            $pidmac = str_replace(":", "", $eqLogic->getLogicalId());
            self::tv_deamon_stop($pidmac);
        }
    }

    /**
     *
     * @param type $mac
     */
    public static function tv_deamon_stop($mac)
    {
        log::add('sonybravia', 'info', 'Arrêt démon sonybravia : ' . $mac);
        $pid_file = jeedom::getTmpFolder('sonybravia') . '/sonybravia_' . $mac . '.pid';
        if (file_exists($pid_file)) {
            $pid = intval(trim(file_get_contents($pid_file)));
            system::kill($pid);
        }
        system::kill('sonybravia.py');
        sleep(1);
    }

    /**
     *
     * @param type $mac
     * @return boolean
     */
    public static function tv_deamon_info($mac)
    {
        $return   = false;
        $pidmac   = str_replace(":", "", $mac);
        $pid_file = jeedom::getTmpFolder('sonybravia') . '/sonybravia_' . $pidmac . '.pid';
        if (file_exists($pid_file)) {
            if (posix_getsid(trim(file_get_contents($pid_file)))) {
                $return = true;
            } else {
                shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
            }
        }
        return $return;
    }

    /**
     *
     * @param type $_ip
     * @param type $_mac
     * @param type $_psk
     * @param type $_cookie
     * @return boolean
     * @throws \Exception
     */
    public static function tv_deamon_pin($_ip, $_mac, $_psk, $_cookie = false)
    {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
            self::deamon_stop();
        }
        /*if ($deamon_info['launchable'] != 'ok') {
            throw new \Exception(__('Veuillez vérifier la configuration', __FILE__));
        }*/
        $sonybravia_path = realpath(dirname(__FILE__) . '/../../resources');
        if ($_cookie === 'true') {
            // $cmd    = '/usr/bin/python3 ' . $sonybravia_path . '/sonybravia_cookie.py';
            // $cmd    .= ' --tvip ' . $_ip;
            // $cmd    .= ' --mac ' . $_mac;
            // $cmd    .= ' --psk ' . $_psk;
            // $cmd    .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/sonybravia/core/php/jeesonybravia.php';
            // $cmd    .= ' --apikey ' . jeedom::getApiKey('sonybravia');
            // log::add('sonybravia', 'info', 'Récupération du pin : ' . $cmd);
            // $result = exec($cmd . ' >> ' . log::getPathToLog('sonybravia_local') . ' 2>&1 &');

            $cmd = '/usr/bin/python3 ' . $sonybravia_path . '/sonybravia.py';
            $cmd    .= ' --tvip ' . $_ip;
            $cmd    .= ' --mac ' . $_mac;
            $cmd    .= ' --psk ' . $_psk;
            $cmd    .= ' --socketport ' . config::byKey('socketport', 'sonybravia', '55052');
            $cmd    .= ' --cycle ' . config::byKey('cycle', 'sonybravia','0.3');
            $cmd    .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/sonybravia/core/php/jeesonybravia.php';
            $cmd    .= ' --apikey ' . jeedom::getApiKey('sonybravia');
            $cmd    .= ' --cookie ' . $_cookie;
            $cmd    .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('sonybravia'));
            log::add('sonybravia', 'info', 'Récupération du pin : ' . $cmd);
            $result = exec($cmd . ' >> ' . log::getPathToLog('sonybravia_local') . ' 2>&1 &'); //variable inutilisé

            message::removeAll('sonybravia', 'unableStartDeamon');
            return true;
        }
        log::add('sonybravia', 'error', __('Veuillez sélectionner le mode pin'), 'unableStartDeamon');
        return false;
    }

    /**
     *
     * @param type $ip
     * @param type $mac
     * @param type $psk
     * @param type $cookie
     * @return boolean
     * @throws \Exception
     */
    public static function tv_deamon_start($ip, $mac, $psk, $cookie = false)
    {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
            self::deamon_stop();
        }
        if ($deamon_info['launchable'] != 'ok') {
            throw new \Exception(__('Veuillez vérifier la configuration', __FILE__));
        }
        $sonybravia_path = realpath(__DIR__ . '/../../resources');

        $cmd = '/usr/bin/python3 ' . $sonybravia_path . '/sonybravia.py';
        $cmd    .= ' --tvip ' . $ip;
        $cmd    .= ' --mac ' . $mac;
        $cmd    .= ' --psk ' . $psk;
        $cmd    .= ' --socketport ' . config::byKey('socketport', 'sonybravia', '55052');
        $cmd    .= ' --cycle ' . config::byKey('cycle', 'sonybravia','0.3');
        $cmd    .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/sonybravia/core/php/jeesonybravia.php';
        $cmd    .= ' --apikey ' . jeedom::getApiKey('sonybravia');
        $cmd    .= ' --cookie ' . $cookie;
        $cmd    .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('sonybravia'));
        $cmd    .= ' --sommeil ' . config::byKey('sommeil', 'sonybravia', '1');
        log::add('sonybravia', 'info', 'Lancement démon sonybravia : ' . $cmd);
        $result = exec($cmd . ' >> ' . log::getPathToLog('sonybravia_local') . ' 2>&1 &'); //variable inutilisé
        $i      = 0;
        while ($i < 30) {
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'ok') {
                break;
            }
            sleep(1);
            $i++;
        }
        if ($i >= 30) {
            log::add('sonybravia', 'error', __('Impossible de lancer le démon sonybravia, vérifiez la log', __FILE__), 'unableStartDeamon');
            return false;
        }
        message::removeAll('sonybravia', 'unableStartDeamon');
        return true;
    }

    /**
     *
     * @return boolean
     */
    public static function deamon_start()
    {
        foreach (eqLogic::byType('sonybravia', true) as $eqLogic) {
            self::tv_deamon_start($eqLogic->getConfiguration('ipadress'), $eqLogic->getLogicalId(), $eqLogic->getConfiguration('psk'), $eqLogic->getConfiguration('pin'));
            sleep(1);
        }
        return true;
    }

    /**
     *
     * @throws \Exception
     */
    public static function event()
    {
        $cmd = sonybraviaCmd::byId(init('id'));
        if (!is_object($cmd) || $cmd->getEqType() != 'sonybravia') {
            throw new \Exception(__('Commande ID virtuel inconnu, ou la commande n\'est pas de type virtuel : ', __FILE__) . init('id'));
        }
        $cmd->event(init('value'));
    }

    public static function changeLogLive($_level) {
		$value = array('apikey' => jeedom::getApiKey('sonybravia'), 'cmd' => $_level);
		$value = json_encode($value);
		self::socket_connection($value,True);
	}

    /**
     *
     * @return string
     */
    public static function deadCmd()
    {
        $return = array();
        foreach (eqLogic::byType('sonybravia') as $sonybravia) {
            foreach ($sonybravia->getCmd() as $cmd) {
                preg_match_all("/#([0-9]*)#/", $cmd->getConfiguration('infoName', ''), $matches);
                foreach ($matches[1] as $cmd_id) {
                    if (!cmd::byId(str_replace('#', '', $cmd_id))) {
                        $return[] = array('detail' => 'Virtuel ' . $sonybravia->getHumanName() . ' dans la commande ' . $cmd->getName(), 'help' => 'Nom Information', 'who' => '#' . $cmd_id . '#');
                    }
                }
                preg_match_all("/#([0-9]*)#/", $cmd->getConfiguration('calcul', ''), $matches);
                foreach ($matches[1] as $cmd_id) {
                    if (!cmd::byId(str_replace('#', '', $cmd_id))) {
                        $return[] = array('detail' => 'Virtuel ' . $sonybravia->getHumanName() . ' dans la commande ' . $cmd->getName(), 'help' => 'Calcul', 'who' => '#' . $cmd_id . '#');
                    }
                }
            }
        }
        return $return;
    }

    /**
     *
     * @param type $eqLogicId
     * @throws \Exception
     */
    public function copyFromEqLogic($eqLogicId)
    {
        $eqLogic = eqLogic::byId($eqLogicId);
        if (!is_object($eqLogic)) {
            throw new \Exception(__('Impossible de trouver l\'équipement : ', __FILE__) . $eqLogicId);
        }
        if ($eqLogic->getEqType_name() == 'sonybravia') {
            throw new \Exception(__('Vous ne pouvez importer la configuration d\'un équipement virtuel', __FILE__));
        }
        foreach ($eqLogic->getCategory() as $key => $value) {
            $this->setCategory($key, $value);
        }
        foreach ($eqLogic->getCmd() as $cmd_def) {
            $cmd_name = $cmd_def->getName();
            if ($cmd_name == __('Rafraichir')) {
                $cmd_name .= '_1';
            }
            $cmd = (new sonybraviaCmd())
                    ->setName($cmd_name)
                    ->setEqLogic_id($this->getId())
                    ->setIsVisible($cmd_def->getIsVisible())
                    ->setType($cmd_def->getType())
                    ->setUnite($cmd_def->getUnite())
                    ->setOrder($cmd_def->getOrder())
                    ->setDisplay('icon', $cmd_def->getDisplay('icon'))
                    ->setDisplay('invertBinary', $cmd_def->getDisplay('invertBinary'))
                    ->setConfiguration('listValue', $cmd_def->getConfiguration('listValue', ''));
            foreach ($cmd_def->getTemplate() as $key => $value) {
                $cmd->setTemplate($key, $value);
            }
            $cmd->setSubType($cmd_def->getSubType());
            if ($cmd->getType() == 'info') {
                $cmd->setConfiguration('calcul', '#' . $cmd_def->getId() . '#')
                        ->setValue($cmd_def->getId());
            } else {
                $cmd->setValue($cmd_def->getValue())
                        ->setConfiguration('infoName', '#' . $cmd_def->getId() . '#');
            }
            try {
                $cmd->save();
            } catch (\Exception $e) {

            }
        }
        $this->save();
    }

    public static function socket_connection($_value)
    {
        try {
            $socket = socket_create(AF_INET, SOCK_STREAM, 0);
            socket_connect($socket, '127.0.0.1', '55052');
            socket_write($socket, $_value, strlen($_value));
            socket_close($socket);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

}

/**
 *
 */
class sonybraviaCmd extends cmd
{

    /**
     *
     * @return boolean
     */
    public function dontRemoveCmd()
    {
        if ($this->getLogicalId() == 'refresh') {
            return true;
        }
        return false;
    }

    /**
     *
     */
    public function preSave()
    {
        if ($this->getConfiguration('sonybraviaAction') == 1) {
            $actionInfo = sonybraviaCmd::byEqLogicIdCmdName($this->getEqLogic_id(), $this->getName());
            if (is_object($actionInfo)) {
                $this->setId($actionInfo->getId());
            }
        }
    }

    /**
     *
     */
    public function postSave()
    {
        if ($this->getType() == 'info' && $this->getConfiguration('sonybraviaAction', 0) == '0' && $this->getConfiguration('calcul') != '') {
            $this->event($this->execute());
        }
    }

    /**
     *
     * @param type $options
     * @return type
     */
    public function execute($options = null)
    {
        switch ($this->getType()) {
            case 'info':
                if ($this->getConfiguration('sonybraviaAction', 0) == '0') {
                    try {
                        $result = jeedom::evaluateExpression($this->getConfiguration('calcul'));
                        if ($this->getSubType() == 'numeric') {
                            if (is_numeric($result)) {
                                $result = number_format($result, 2);
                            } else {
                                $result = str_replace('"', '', $result);
                            }
                            if (strpos($result, '.') !== false) {
                                $result = str_replace(',', '', $result);
                            } else {
                                $result = str_replace(',', '.', $result);
                            }
                        }
                        return $result;
                    } catch (\Exception $e) {
                        log::add('sonybravia', 'info', $e->getMessage());
                        return jeedom::evaluateExpression($this->getConfiguration('calcul'));
                    }
                }
                break;
            case 'action':
                try {
                    $sonybravia      = $this->getEqLogic();
                    $fulldata = array(
                        'apikey' => jeedom::getApiKey('sonybravia'),
                        'cmd' => 'action',
                        'device' => $sonybravia->getLogicalId(),
                        'command' => $this->getLogicalId(),
                        'commandparam' => $this->getConfiguration('param')
                    );
		            log::add('sonybravia','debug',"Envoi de la commande " . $this->getLogicalId() . " depuis Jeedom");
		            sonybravia::socket_connection( json_encode($fulldata) );



                } catch (\Exception $e) {
                    log::add('sonybravia', 'info', $e->getMessage());
                }
                break;
        }
    }

}
