<?php
class Kingboard_Homepage_View extends Kingboard_Base_View
{
    public function index($request)
    {
        $currentPage = 1;
        if (!empty($request['page']))
        {
            $currentPage = ((int) $request['page'] <1) ?  1 : (int) $request['page'];
        }


        $info = array();
        $templateVars =array();
        // differences for owned boards
        if($this->_context['ownerID'])
        {
            $killList = new Kingboard_KillList($this->_context["ownerType"], $this->_context["ownerID"]);
            $templateVars['killstats'] = $killList->getKillStats();
            $templateVars['lossstats'] = $killList->getLossStats();
            $templateVars['totalstats'] = $killList->getTotalStats();
            $template = "index_owned_board.html";
        } else {
            // this is the only case of a list without owner/type, which is open list.
            $killList = new Kingboard_KillList(null, null);
            $stats = Kingboard_Kill_MapReduce_KillsByShip::find();
            $stats = $stats->sort(array("value.value" => -1));
            $templateVars['stats'] = $stats;
            $template = "index.html";
        }

        $paginator = new Kingboard_Paginator($currentPage, $killList->getCount());
        // fetch kill data
        $templateVars['data'] = $killList->getKills($paginator ->getSkip(), $paginator->getKillsPerPage());

        // merge in pagination data
        $templateVars= array_merge($templateVars, $paginator->getNavArray());

        $templateVars['action'] = '/home';
        $templateVars['count'] = $killList->getCount();
        $templateVars['info'] = $info;

        // battles
        $templateVars['reports'] = Kingboard_BattleSettings::find()->limit(20)->sort(array('enddate' => -1));

        return $this->render($template, $templateVars);
    }

    public function killlist($request)
    {

        if(empty($request['ownerType']) || empty($request['ownerID']))
            die("type / id not given");

        $ownerType = $request['ownerType'];
        $ownerID = $request['ownerID'];

        $currentPage = 1;
        if (!empty($request['page']))
        {
            $currentPage = ((int) $request['page'] <1) ?  1 : (int) $request['page'];
        }

        $templateVars =array();

        // kill list
        $killList = new Kingboard_KillList($ownerType, $ownerID);
        $templateVars['killstats'] = $killList->getKillStats();
        $templateVars['lossstats'] = $killList->getLossStats();
        $templateVars['totalstats'] = $killList->getTotalStats();

        $paginator = new Kingboard_Paginator($currentPage, $killList->getCount());
        // fetch kill data
        $templateVars['data'] = $killList->getKills($paginator ->getSkip(), $paginator->getKillsPerPage());

        // merge in pagination data
        $templateVars= array_merge($templateVars, $paginator->getNavArray());

        $templateVars['count'] = $killList->getCount();

        switch($ownerType)
        {
            case "character":
            case "char":
            case "pilot":
                $template = "pilot/index.html";
                $info = Kingboard_Kill::getPilotInfoFromId($ownerID);
                break;
            case "corp":
            case "corporation":
                $template = "corporation/index.html";
                $info = Kingboard_Kill::getCorporationInfoFromId($ownerID);
                break;
            case "faction":
                $template = "faction/index.html";
                $info = Kingboard_Kill::getFactionInfoFromId($ownerID);
                break;
            case "alliance":
                $template = "alliance/index.html";
                $info = Kingboard_Kill::getAllianceInfoFromId($ownerID);
                break;
        }
        $templateVars['info'] = $info;

        // we replace the defaults with the ones of the current look
        $templateVars['ownerID'] = $ownerID;
        $templateVars['ownerType'] = $ownerType;
        $templateVars['action'] = "/details/$ownerType/$ownerID";

        return $this->render($template, $templateVars);
    }
}
