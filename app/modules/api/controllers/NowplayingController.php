<?php
namespace Modules\Api\Controllers;

use \Entity\Station;
use \Entity\Song;
use \Entity\Schedule;

class NowplayingController extends BaseController
{
    public function indexAction()
    {
        $file_path_api = DF_INCLUDE_STATIC.'/api/nowplaying_api.json';
        $np_raw = file_get_contents($file_path_api);

        // Sanity check for now playing data.
        if (empty($np_raw))
            return $this->returnError('Now Playing data has not loaded into the cache. Wait for file reload.');

        if ($this->hasParam('id') || $this->hasParam('station'))
        {
            $np_arr = @json_decode($np_raw, TRUE);
            $np = $np_arr['result'];

            if ($this->hasParam('id'))
            {
                $id = (int)$this->getParam('id');
                foreach($np as $key => $np_row)
                {
                    if ($np_row['station']['id'] == $id)
                    {
                        $sc = $key;
                        break;
                    }
                }

                if (empty($sc))
                    return $this->returnError('Station not found!');
            }
            elseif ($this->hasParam('station'))
            {
                $sc = $this->getParam('station');
            }

            if (isset($np[$sc]))
                $this->returnSuccess($np[$sc]);
            else
                return $this->returnError('Station not found!');
        }
        else
        {
            $this->returnRaw($np_raw, 'json');
        }
    }
}