<?php
namespace App\Radio\Frontend;

use App\Debug;
use App\Utilities;
use Entity\Station;
use Doctrine\ORM\EntityManager;

class ShoutCast2 extends FrontendAbstract
{
    protected $supports_mounts = false;

    /* Process a nowplaying record. */
    protected function _getNowPlaying(&$np)
    {
        $fe_config = (array)$this->station->frontend_config;
        $radio_port = $fe_config['port'];

        $np_url = 'http://localhost:'.$radio_port.'/stats';
        $return_raw = $this->getUrl($np_url);

        if (empty($return_raw))
            return false;

        $current_data = \App\Export::xml_to_array($return_raw);

        Debug::print_r($return_raw);

        $song_data = $current_data['SHOUTCASTSERVER'];

        Debug::print_r($song_data);

        $np['meta']['status'] = 'online';
        $np['meta']['bitrate'] = $song_data['BITRATE'];
        $np['meta']['format'] = $song_data['CONTENT'];

        $np['current_song'] = $this->getSongFromString($song_data['SONGTITLE'], '-');

        $u_list = (int)$song_data['UNIQUELISTENERS'];
        $t_list = (int)$song_data['CURRENTLISTENERS'];
        $np['listeners'] = array(
            'current'       => $this->getListenerCount($u_list, $t_list),
            'unique'        => $u_list,
            'total'         => $t_list,
        );

        return true;
    }

    public function read()
    {

        $config = $this->_getConfig();

        $this->station->frontend_config = $this->_loadFromConfig($config);
        return true;
    }

    public function write()
    {
        $config = $this->_getDefaults();

        $frontend_config = (array)$this->station->frontend_config;

        if (!empty($frontend_config['port']))
            $config['portbase'] = $frontend_config['port'];

        if (!empty($frontend_config['source_pw']))
            $config['password'] = $frontend_config['source_pw'];

        if (!empty($frontend_config['admin_pw']))
            $config['adminpassword'] = $frontend_config['admin_pw'];

        if (!empty($frontend_config['custom_config']))
        {
            $custom_conf = $this->_processCustomConfig($frontend_config['custom_config']);
            if (!empty($custom_conf))
                $config = array_merge($config, $custom_conf);
        }

        // Set any unset values back to the DB config.
        $this->station->frontend_config = $this->_loadFromConfig($config);

        $em = $this->di['em'];
        $em->persist($this->station);
        $em->flush();

        $config_path = $this->station->getRadioConfigDir();
        $sc_path = $config_path.'/sc_serv.conf';

        $sc_file = '';
        foreach($config as $config_key => $config_value)
            $sc_file .= $config_key.'='.str_replace("\n", "", $config_value)."\n";

        file_put_contents($sc_path, $sc_file);
    }

    /*
     * Process Management
     */

    public function isRunning()
    {
        return $this->_isPidRunning($this->station->getRadioConfigDir().'/sc_serv.pid');
    }

    public function stop()
    {
        $this->_killPid($this->station->getRadioConfigDir().'/sc_serv.pid');
    }

    public function start()
    {
        $config_path = $this->station->getRadioConfigDir();

        $sc_binary = realpath(APP_INCLUDE_ROOT.'/..').'/servers/sc_serv';
        $sc_config = $config_path.'/sc_serv.conf';

        if ($this->isRunning())
        {
            $this->log(_('Not starting, process is already running.'));
            return;
        }

        $cmd = \App\Utilities::run_command($sc_binary.' daemon '.$sc_config.' > '.$config_path.'/sc_pid_raw.txt');

        if (file_exists($config_path.'/sc_pid_raw.txt'))
        {
            $pid_raw = file_get_contents($config_path.'/sc_pid_raw.txt');
            $this->log($pid_raw);

            preg_match('#\[(.*?)\]#', $pid_raw, $match);
            $pid = (int)$match[1];

            if ($pid != 0)
                file_put_contents($config_path.'/sc_serv.pid', $pid);

            @unlink($config_path.'/sc_pid_raw.txt');
        }

        if (!empty($cmd['output']))
            $this->log($cmd['output']);

        if (!empty($cmd['error']))
            $this->log($cmd['error'], 'red');
    }

    public function getStreamUrl()
    {
        return $this->getUrlForMount('/stream/1/');
    }

    public function getStreamUrls()
    {
        return [$this->getUrlForMount('/stream/1/')];
    }

    public function getUrlForMount($mount_name)
    {
        return $this->getPublicUrl().$mount_name.'?'.time();
    }

    public function getAdminUrl()
    {
        return $this->getPublicUrl().'/admin.cgi';
    }

    public function getPublicUrl()
    {
        $fe_config = (array)$this->station->frontend_config;
        $radio_port = $fe_config['port'];

        $base_url = $this->di['em']->getRepository('Entity\Settings')->getSetting('base_url', 'localhost');

        // Vagrant port-forwarding mode.
        if (APP_APPLICATION_ENV == 'development')
            return 'http://'.$base_url.':8080/radio/'.$radio_port;
        else
            return 'http://'.$base_url.':'.$radio_port;
    }

    /*
     * Configuration
     */

    protected function _getConfig()
    {
        $config_dir = $this->station->getRadioConfigDir();
        $config = @parse_ini_file($config_dir.'/sc_serv.conf', false, INI_SCANNER_RAW);

        return $config;
    }

    protected function _loadFromConfig($config)
    {
        return [
            'port' => $config['portbase'],
            'source_pw' => $config['password'],
            'admin_pw' => $config['adminpassword'],
        ];
    }

    protected function _getDefaults()
    {
        $config_path = $this->station->getRadioConfigDir();

        $defaults = [
            'password'      => Utilities::generatePassword(),
            'adminpassword' => Utilities::generatePassword(),
            'logfile'       => $config_path.'/sc_serv.log',
            'w3clog'        => $config_path.'/sc_w3c.log',
            'publicserver'  => 'never',
            'banfile'       => $config_path.'/sc_serv.ban',
            'ripfile'       => $config_path.'/sc_serv.rip',
            'maxuser'       => 500,
            'portbase'      => $this->_getRadioPort(),
        ];

        return $defaults;
    }
}