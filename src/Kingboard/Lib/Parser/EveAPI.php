<?php
namespace Kingboard\Lib\Parser;
class EveAPI
{
    public function parseKills($kills)
    {
        $oldkills = 0;
        $newkills = 0;
        $errors = 0;
        $lastID = 0;
        $lastIntID = 0;
        foreach($kills as $kill)
        {
            try {
                // this needs to be run before exit of loop, otherwise having all kills of this run
                // will cause the lastID not being updated
                if(!is_null($kill->killID) && $kill->killID > 0)
                    $lastID=$kill->killID;

                if(!is_null(@$kill->killInternalID) && @$kill->killInternalID > 0)
                    $lastIntID = $kill->killInternalID;

                /*if(!is_null(Kingboard_Kill::getByKillId($kill->killID)))
                {
                    $oldkills++;
                    continue;
                }*/
                $killdata = array(
                    "killID" => $kill->killID,
                    "solarSystemID" => $kill->solarSystemID,
                    "location" => array(
                        "solarSystem" => \Kingboard\Model\EveSolarSystem::getBySolarSystemId($kill->solarSystemID)->itemName,
                        "security" => \Kingboard\Model\EveSolarSystem::getBySolarSystemId($kill->solarSystemID)->security,
                        "region" => \Kingboard\Model\EveSolarSystem::getBySolarSystemId($kill->solarSystemID)->Region['itemName'],
                    ),
                    "killTime" => new \MongoDate(strtotime($kill->killTime)),
                    "moonID" => $kill->moonID,
                    "victim" => array(
                        "characterID" => (int) $this->ensureEveEntityID($kill->victim->characterID, $kill->victim->characterName),
                        "characterName" => $kill->victim->characterName,
                        "corporationID" => (int) $this->ensureEveEntityID($kill->victim->corporationID, $kill->victim->corporationName),
                        "corporationName" => $kill->victim->corporationName,
                        "allianceID" => (int) $kill->victim->allianceID,
                        "allianceName" => $kill->victim->allianceName,
                        "factionID" => (int) $kill->victim->factionID,
                        "factionName" => $kill->victim->factionName,
                        "damageTaken" => $kill->victim->damageTaken,
                        "shipTypeID"  => (int)$kill->victim->shipTypeID,
                        "shipType"  => \Kingboard\Model\EveItem::getByItemId($kill->victim->shipTypeID)->typeName
                    )
                );
                $killdata['attackers'] = array();
                foreach($kill->attackers as $attacker)
                {
                    $killdata['attackers'][] = array(
                        "characterID" => (int)$attacker->characterID,
                        "characterName" => $attacker->characterName,
                        "corporationID" => (int) $this->ensureEveEntityID($attacker->corporationID, $attacker->corporationName),
                        "corporationName" => $attacker->corporationName,
                        "allianceID" => (int) $attacker->allianceID,
                        "allianceName" => $attacker->allianceName,
                        "factionID" => (int) $attacker->factionID,
                        "factionName" => $attacker->factionName,
                        "securityStatus" => $attacker->securityStatus,
                        "damageDone" => $attacker->damageDone,
                        "finalBlow"  => $attacker->finalBlow,
                        "weaponTypeID" => (int) $attacker->weaponTypeID,
                        "weaponType" => \Kingboard\Model\EveItem::getByItemId($attacker->weaponTypeID)->typeName,
                        "shipTypeID" => (int) $attacker->shipTypeID,
                        "shipType"  => \Kingboard\Model\EveItem::getByItemId($attacker->shipTypeID)->typeName
                    );
                }
                $killdata['items'] = array();

                if(@!is_null($kill->items))
                {
                    foreach($kill->items as $item)
                    {
                        $killdata['items'][] = $this->ParseItem($item);
                    }
                }

                $hash = \Kingboard\Lib\IdHash::getByData($killdata);
                $killdata['idHash'] = $hash->generateHash();
                if(is_null(\Kingboard\Model\Kill::getInstanceByIdHash($killdata['idHash'])))
                {
                    $killObject = new \Kingboard\Model\Kill();
                    $killObject->injectDataFromMail($killdata);
                    $killObject->save();
                    $newkills++;
                } else {
                    $oldkills++;
                }
            } catch (\Exception $e)
            {
                $errors++;
            }
        }
        return array('oldkills' => $oldkills, 'newkills' => $newkills, 'lastID' => $lastID, 'lastIntID' => $lastIntID, 'errors' => $errors);
    }


    private function ParseItem($row)
    {
        // Build the standard item
        $item = array(
            "typeID" => $row->typeID,
            "typeName" => \Kingboard\Model\EveItem::getByItemId($row->typeID)->typeName,
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

    public function ensureEveEntityID($id, $charname)
    {
        $id = (int) $id;
        if($id == 0)
        {
            if($id = \Kingboard\Model\MapReduce\NameSearch::getEveIdByName($charname))
                return $id;

            $pheal = new \Pheal();
            $result = $pheal->eveScope->typeName(array('names' => $charname))->toArray();
            if ((int) $result[0]['characterID'] > 0)
                return (int) $result[0]['characterID'];

            throw new \Exception("No such characterID");
        }
        return $id;
    }
}