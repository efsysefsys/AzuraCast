<?php
namespace App\Radio\Frontend;

use App\Service\Curl;
use Entity\Station;
use Interop\Container\ContainerInterface;

abstract class FrontendAbstract extends \App\Radio\AdapterAbstract
{
    protected $supports_mounts = true;

    public function supportsMounts()
    {
        return $this->supports_mounts;
    }

    protected $supports_streamers = true;

    public function supportsStreamers()
    {
        return $this->supports_streamers;
    }

    abstract public function getStreamUrl();

    abstract public function getAdminUrl();

    /* Fetch a remote URL. */
    protected function getUrl($url, $c_opts = null)
    {
        if ($c_opts === null)
            $c_opts = array();

        if (!isset($c_opts['url']))
            $c_opts['url'] = $url;

        if (!isset($c_opts['timeout']))
            $c_opts['timeout'] = 4;

        return Curl::request($c_opts);
    }

    public function getNowPlaying()
    {
        // Now Playing defaults.
        $np = array(
            'current_song' => array(
                'text'          => 'Stream Offline',
                'title'         => '',
                'artist'        => '',
            ),
            'listeners' => array(
                'current'       => 0,
                'unique'        => null,
                'total'         => null,
            ),
            'meta' => array(
                'status'        => 'offline',
                'bitrate'       => 0,
                'format'        => '',
            ),
        );

        // Merge station-specific info into defaults.
        $this->_getNowPlaying($np);

        // Update status code for offline stations, clean up song info for online ones.
        if ($np['current_song']['text'] == 'Stream Offline')
            $np['meta']['status'] = 'offline';
        else
            array_walk($np['current_song'], array($this, '_cleanUpString'));

        // Fill in any missing listener info.
        if ($np['listeners']['unique'] === null)
            $np['listeners']['unique'] = $np['listeners']['current'];

        if ($np['listeners']['total'] === null)
            $np['listeners']['total'] = $np['listeners']['current'];

        return $np;
    }

    /* Stub function for the process internal handler. */
    abstract protected function _getNowPlaying(&$np);

    protected function _cleanUpString(&$value)
    {
        $value = htmlspecialchars_decode($value);
        $value = trim($value);
    }

    /* Calculate listener count from unique and current totals. */
    protected function getListenerCount($unique_listeners = 0, $current_listeners = 0)
    {
        $unique_listeners = (int)$unique_listeners;
        $current_listeners = (int)$current_listeners;

        if ($unique_listeners == 0 || $current_listeners == 0)
            return max($unique_listeners, $current_listeners);
        else
            return min($unique_listeners, $current_listeners);
    }

    /* Return the artist and title from a string in the format "Artist - Title" */
    protected function getSongFromString($song_string, $delimiter = '-')
    {
        // Filter for CR AutoDJ
        $song_string = str_replace('AutoDJ - ', '', $song_string);

        // Fix ShoutCast 2 bug where 3 spaces = " - "
        $song_string = str_replace('   ', ' - ', $song_string);

        // Remove dashes or spaces on both sides of the name.
        $song_string = trim($song_string, " \t\n\r\0\x0B-");

        $string_parts = explode($delimiter, $song_string);

        // If not normally delimited, return "text" only.
        if (count($string_parts) == 1)
            return array('text' => $song_string, 'artist' => '', 'title' => $song_string);

        // Title is the last element, artist is all other elements (artists are far more likely to have hyphens).
        $title = trim(array_pop($string_parts));
        $artist = trim(implode($delimiter, $string_parts));

        return array(
            'text' => $song_string,
            'artist' => $artist,
            'title' => $title,
        );
    }

    /**
     * Log a message to console or to flash (if interactive session).
     *
     * @param $message
     */
    public function log($message, $class = 'info')
    {
        if (!empty(trim($message)))
            parent::log(str_pad('Radio Frontend: ', 20, ' ', STR_PAD_RIGHT).$message, $class);
    }
}