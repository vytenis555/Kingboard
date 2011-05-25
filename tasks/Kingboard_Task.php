<?php
require_once 'conf/config.php';
class Kingboard_Task extends King23_CLI_Task
{
    /**
     * documentation for the single tasks
     * @var array
     */
    protected $tasks = array(
        "info" => "General Informative Task",
        "import" => "import",
        "key_add" => "add an apikey, requires userid, apikey",
        "feed_add" => "add a feed to the feeds to be pulled",
        "feed_pull" => "pull feeds",
        "file_import" => "import kills from files named *.txt, 1 parameter == directory",
        "ek_import" => "import range from <param1> to <param2> from eve-kill"
    );

    /**
     * Name of the module
     */
    protected $name = "Kingboard";

    /**
     * add an apikey to be used in imports
     * @deprecated keys should be added through users interface
     * @param array $options
     * @return void
     */
    public function key_add(array $options)
    {
        if(isset($options[0]) && !empty($options[0]) && isset($options[1]) && !empty($options[1]))
        {
            // Load the key if it exists (we might already know it)
            $key = Kingboard_EveApiKey::getByUserId($options[0]);
            if (!is_null($key))
            {
                $key['apikey'] = $options[1];
                $key->save();
                $this->cli->positive("key updated");
            }
            else
            {
                $key = new Kingboard_EveApiKey();
                $key['userid'] = $options[0];
                $key['apikey'] = $options[1];
                $key->save();
                $this->cli->positive("key saved");
            }

        } else {
            $this->cli->error('two parameters needed (userid, apikey)');
        }
    }


    public function import(array $options)
    {
        $this->cli->message("import running");
        $newkills = 0;
        $oldkills = 0;
        $keys = Kingboard_EveApiKey::find();
        foreach($keys as $key)
        {
            $pheal = new Pheal($key['userid'], $key['apikey']);
            $pheal->scope = "account";
            try {
                foreach($pheal->Characters()->characters as $char)
                {
                    try {
                        $this->cli->message('trying corp import on ' . $char->name ."...");
                        $pheal->scope = 'corp';
                        $kills = $pheal->Killlog(array('characterID' => $char->characterID))->kills;
                    } catch(PhealAPIException $e) {
                        $this->cli->message('corp failed, trying char import now..');
                        $pheal->scope = 'char';
                        try {
                            $kills = $pheal->Killlog(array('characterID' => $char->characterID))->kills;
                        } catch (PhealAPIException $e) {
                            continue;
                        }
                    }
                    foreach($kills as $kill)
                    {
                        $this->cli->message("import of " . $kill->killID);
                        if(!is_null(Kingboard_Kill::getByKillId($kill->killID)))
                        {
    	        		    $oldkills++;
                            $this->cli->message("kill allready in database");	
    			            continue;
        	    		}
		            	$killdata = array(
                            "killID" => $kill->killID,
                            "solarSystemID" => $kill->solarSystemID,
                            "location" => array(
                                "solarSystem" => Kingboard_EveSolarSystem::getBySolarSystemId($kill->solarSystemID)->itemName,
                                "security" => Kingboard_EveSolarSystem::getBySolarSystemId($kill->solarSystemID)->security,
                                "region" => Kingboard_EveSolarSystem::getBySolarSystemId($kill->solarSystemID)->Region['itemName'],
                            ),
                            "killTime" => new MongoDate(strtotime($kill->killTime)),
                            "moonID" => $kill->moonID,
                            "victim" => array(
                                "characterID" => (int) $kill->victim->characterID,
                                "characterName" => $kill->victim->characterName,
                                "corporationID" => (int) $kill->victim->corporationID,
                                "corporationName" => $kill->victim->corporationName,
                                "allianceID" => (int) $kill->victim->allianceID,
                                "allianceName" => $kill->victim->allianceName,
                                "factionID" => (int) $kill->victim->factionID,
                                "factionName" => $kill->victim->factionName,
                                "damageTaken" => $kill->victim->damageTaken,
                                "shipTypeID"  => (int)$kill->victim->shipTypeID,
                                "shipType"  => Kingboard_EveItem::getByItemId($kill->victim->shipTypeID)->typeName
                            )
                        );
                        $killdata['attackers'] = array();
                        foreach($kill->attackers as $attacker)
                        {
                            $killdata['attackers'][] = array(
                                "characterID" => (int) $attacker->characterID,
                                "characterName" => $attacker->characterName,
                                "entityType" => Kingboard_Helper_EntityType::getEntityTypeByEntityId((int) $attacker->characterID),
                                "corporationID" => (int) $attacker->corporationID,
                                "corporationName" => $attacker->corporationName,
                                "allianceID" => (int) $attacker->allianceID,
                                "allianceName" => $attacker->allianceName,
                                "factionID" => (int) $attacker->factionID,
                                "factionName" => $attacker->factionName,
                                "securityStatus" => $attacker->securityStatus,
                                "damageDone" => $attacker->damageDone,
                                "finalBlow"  => $attacker->finalBlow,
                                "weaponTypeID" => (int) $attacker->weaponTypeID,
                                "weaponType" => Kingboard_EveItem::getByItemId($attacker->weaponTypeID)->typeName,
                                "shipTypeID" => (int) $attacker->shipTypeID,
                                "shipType"  => Kingboard_EveItem::getByItemId($attacker->shipTypeID)->typeName
                            );
                        }
                        $killdata['items'] = array();
                        
                        foreach($kill->items as $item)
                        {
                            $killdata['items'][] = $this->ParseItem($item);
                        }
                        
                        $hash = Kingboard_KillmailHash_IdHash::getByData($killdata);
                        $killdata['idHash'] = (String) $hash;

                        if(is_null(Kingboard_Kill::getInstanceByIdHash($killdata['idHash'])))
                        {
                            $this->cli->message("new kill, saving");
                            $killObject = new Kingboard_Kill();
                            $killObject->injectDataFromMail($killdata);
                            $killObject->save();
                            $newkills++;
                        } else {
                            $oldkills++;
                            $this->cli->message("kill allready in database");
                        }
                    }
                }
            } catch (PhealApiException $e) {
                if(!isset($key['failed']))
                    $key->failed = 0;
                $key->failed++;
                $key->save();
            } catch (PhealException $pe) {
        		$this->cli->message("PhealException caught, auch!");
    	    	continue;
	        }
        }
        $totalkills = $oldkills + $newkills;
        $this->cli->message("found $totalkills kills, $oldkills where allready in database, $newkills added");
    }

    private function ParseItem($row)
    {
        // Build the standard item
        $item = array(
            "typeID" => $row->typeID,
            "typeName" => Kingboard_EveItem::getByItemId($row->typeID)->typeName,
            "flag" => $row->flag,
            "qtyDropped" => $row->qtyDropped,
            "qtyDestroyed" => $row->qtyDestroyed
        );

        // Check for nested items (container)
        if (isset($row['items']))
        {
            $item['items'] = array();
            foreach($row['items'] as $innerRow)
            {
                $item['items'][] = $this->ParseItem($innerRow);
            }
        }
        return $item;
    }

    public function feed_add($options)
    {
        $this->cli->header('adding new feed');
        if(count($options) != 1)
        {
            $this->cli->error('exactly one parameter (url) should be given');
            return;
        }

        if(!is_null(Kingboard_EdkFeed::findByUrl($options[0])))
        {
            $this->cli->error('a feed by this url allready exists!');
            return;
        }

        $feed = new Kingboard_EdkFeed();
        $feed->url = $options[0];
        $feed->save();
        $this->cli->positive('done');
    }

    public function feed_pull($options)
    {
        $this->cli->header('pulling all feeds');
        $feeds = Kingboard_EdkFeed::find();
        foreach($feeds as $feed)
        {

            $url = $feed->url;
            $this->cli->message('pulling ' . $url);
            $sxo =simplexml_load_file($url);
            $processed = 0;
            $killcount = count($sxo->channel->item);
            $this->cli->message("processing $killcount kills.");
            foreach($sxo->channel->item as $item)
            {
                $this->cli->message('processing ' . ++$processed . ' out of ' .  $killcount);
                $mailtext = trim((string) $item->description);
                if(isset($item->apiID))
                    $apiId = (string) $item->apiID;
                else
                    $apiId = null;

                try {
                    $mail = Kingboard_KillmailParser_Factory::parseTextMail($mailtext);
                    $mail->killID = $apiId;
                    $mail->save();
                } catch(Kingboard_KillmailParser_KillmailErrorException $e) {
                    $this->cli->error("Exception caught, mail was not processed");
                    $this->cli->error($e->getMessage());
                    $this->cli->error($e->getFile() . '::' . $e->getLine());
                }

            }
            $this->cli->positive('done');
        }
    }

    public function file_import(array $options)
    {
        if(count($options) != 1 || !is_dir($options[0]))
        {
            $this->cli->error('file_import requires 1 parameter, which needs to be a directory');
            return;
        }

        $files = glob($options[0] . "*.txt");
        $processed = 0;
        $killcount = count($files);
        foreach($files as $file)
        {
            $this->cli->message('processing ' . ++$processed . ' out of ' .  $killcount);
            $mailtext = trim(join('', file($file)));

            $apiId = null;

            try {
                $mail = Kingboard_KillmailParser_Factory::parseTextMail($mailtext);
                $mail->killID = $apiId;
                $mail->save();
            } catch(Kingboard_KillmailParser_KillmailErrorException $e) {
                $this->cli->error("Exception caught, mail was not processed");
                $this->cli->error($e->getMessage());
                $this->cli->error($e->getFile() . '::' . $e->getLine());
            }

        }
    }

    public function ek_import(array $options)
    {
        if(count($options) != 2 || !is_numeric($options[0]) || !is_numeric($options[1]))
        {
            $this->cli->error('need two numbers as parameters');
            return;
        }

        $processed = 0;
        $killcount = $options[1] - $options[0] +1;
        for($i = $options[0]; $i <= $options[1]; $i++)
        {
            $this->cli->message('processing ' . ++$processed . ' out of ' .  $killcount);
            $mailtext = trim(join('', file("http://eve-kill.net/kingboard.php?kllid=" . $i)));

            $apiId = null;

            try {
                $mail = Kingboard_KillmailParser_Factory::parseTextMail($mailtext);
                $mail->killID = $apiId;
                $mail->save();
            } catch(Kingboard_KillmailParser_KillmailErrorException $e) {
                $this->cli->error("Exception caught, mail was not processed");
                $this->cli->error($e->getMessage());
                $this->cli->error($e->getFile() . '::' . $e->getLine());
            }

        }
    }

}