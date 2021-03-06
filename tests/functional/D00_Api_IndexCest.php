<?php
class D00_Api_IndexCest extends CestAbstract
{
    /**
     * @before setupComplete
     */
    public function checkApiIndex(FunctionalTester $I)
    {
        $I->wantTo('Check basic API functions.');

        $I->sendGET('/api');
        $I->seeResponseContainsJson([
            'status' => 'success',
        ]);

        $I->sendGET('/api/status');
        $I->seeResponseContainsJson([
            'online' => 'true',
        ]);

        $I->sendGET('/api/time');
        $I->seeResponseContainsJson([
            'gmt_timezone' => 'GMT',
        ]);
    }
}
