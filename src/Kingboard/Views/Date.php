<?php
namespace Kingboard\Views;

use DateTime;
use Kingboard\Lib\Paginator;
use Kingboard\Model\Kill;
use Kingboard\Model\MapReduce\KillsByDay;
use MongoDate;


class Date extends Base
{
    public function index(array $params)
    {

        if (!isset($params['date'])) {
            $context["date"] = date("Y-m-d");
        } else {
            $context["date"] = $params['date'];
        }

        // get previous day
        $dt = new DateTime($context['date']);
        $context["previousDate"] = date("Y-m-d", $dt->sub(new \DateInterval("P1D"))->getTimestamp());

        $dt = new DateTime($context['date']);
        $ts = $dt->add(new \DateInterval("P1D"))->getTimestamp();
        if ($ts < time()) {
            $context["nextDate"] = date("Y-m-d", $ts);
        } else {
            $context["nextDate"] = date("Y-m-d");
        }


        if (!isset($params['page']) || empty($params['page'])) {
            $page = 1;
        } else {
            $page = (int) $params['page'];
        }

        // reset date
        $dt = new DateTime($context['date']);
        $mdt = new MongoDate($dt->getTimestamp());

        $stats = KillsByDay::findOne($mdt);
        $context['stats'] = $stats['value'];

        $paginator = new Paginator($page, $context['stats']['total']);
        $context['page']= $paginator->getNavArray();

        // reset date
        $dt = new DateTime($context['date']);

        $kills = Kill::find(array(
            '$and' => array(
                array("killTime" => array('$gt' => new MongoDate($dt->getTimestamp()))),
                array("killTime" => array('$lt' => new MongoDate($dt->add(new \DateInterval("P1D"))->getTimestamp())))
            )
        ))->hint(array("killTime" => 1 ))->sort(array("killTime" => -1))->skip($paginator->getSkip())->limit(10);

        $context['kills'] = $kills;
        $context['action'] = "/day/" . $context['date'];
        $this->render("date/daily.html", $context);



    }
}