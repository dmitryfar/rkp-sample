<?
namespace rutor;

class RutorRss {
	public $categoryId;
	public $lastDate;

	public static function create($categoryId, $lastDate) {
		return RutorRss::updateLastDate($categoryId, $lastDate);
	}

	function createTable() {
		global $db2;

		$sql = "CREATE TABLE `rkp_rutor_rss` (
		  `categoryId` INT(3),
		  `lastDate` DATETIME NOT NULL,
		  PRIMARY KEY (`categoryId`)
		)";
		$db2->query($sql, $res);
	}

	public static function getRutorRss($categoryId=null) {
		global $db2;
		$binds = null;
		if ($categoryId != null) {
			$sql = "select *
					from rkp_rutor_rss
					where categoryId = :categoryId";
			$binds = array("categoryId" => $categoryId);
		} else {
			$sql = "select max(lastDate) as lastDate, 0 as categoryId
				from rkp_rutor_rss";
		}
		$rc = $db2->parametrizedQueryClass($sql, $binds, __CLASS__, $res);

		if ($rc == 0) {
			$obj = new RutorRss();
			$obj->categoryId = $categoryId;
			$obj->lastDate = RutorRss::getZeroDate();
			return $obj;
		}
		$obj = $res[0];
		$obj->lastDate = new ExtDateTime($obj->lastDate);
		//$obj->lastDate->setTimezone(new DateTimeZone('UTC'));
		return $obj;
	}

	public static function resetLastDate($categoryId) {
		$lastDate = RutorRss::getZeroDate();
		$obj = RutorRss::updateLastDate($categoryId, $lastDate);
		return $obj;
	}

	public static function getZeroDate() {
		$lastDate = new ExtDateTime(); // utc
		$lastDate->setTimestamp(0);
		//$lastDate->setTimezone(new DateTimeZone('Europe/Moscow'));
		//$lastDate->setTimezone(new DateTimeZone('UTC'));
		//return $lastDate->format("Y-m-d H:i:s");
		return $lastDate;
	}

	public static function getLastDate($categoryId=null) {
		$obj = RutorRss::getRutorRss($categoryId);
		return $obj->lastDate;
	}

	public static function updateLastDate($categoryId, $lastDate) {
		global $db2;

		$rutorRss = RutorRss::getRutorRss($categoryId);
		if ($rutorRss->lastDate == RutorRss::getZeroDate()) {
			$sql = "insert into rkp_rutor_rss(categoryId, lastDate) values (:categoryId, :lastDate)";
		} else {
			$sql = "update rkp_rutor_rss set lastDate = :lastDate where categoryId = :categoryId";
		}

		$binds = array(
			"categoryId" => $categoryId,
			"lastDate" => $lastDate->format("Y-m-d H:i:s") // ->format(DateTime::ISO8601)
		);
		$query = $db2->createQuery($sql);
		$query->bind($binds);
		$query->debug();
		$res = $query->execute();
		//$this->isNew = false;

		$rutorRss = RutorRss::getRutorRss($categoryId);
		return $rutorRss;
	}
}
?>