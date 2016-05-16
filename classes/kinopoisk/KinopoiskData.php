<?
namespace kinopoisk;

class KinopoiskData {
	public $kpId;
	public $title;
	public $originalTitle;
	public $year;
	public $duration;
	public $ratingValue;
	public $ratingCount;
	public $createDate;

	private $isNew = false;

	public static function create($kpId, $title, $originalTitle, $year, $duration, $ratingValue, $ratingCount) {
		$obj = new KinopoiskData();
		$obj->kpId =           $kpId;
		$obj->title =          $title;
		$obj->originalTitle =  $originalTitle;
		$obj->year =      	 $year;
		$obj->duration =       $duration;
		$obj->ratingValue =    $ratingValue;
		$obj->ratingCount =    $ratingCount;

		$obj->isNew = true;

		$obj = $obj->update();
		return $obj;
	}

    /**
     * @param $kpId
     * @return KinopoiskData
     */
	public static function getByKpId($kpId) {
		\Logger::debug("get kinopoisk data by kpId=$kpId");

		global $db2;
		$sql = "select *
				from rkp_kp_data
				where kpId=:kpId";

		$query = $db2->createQuery($sql);
		$query->resultClass(__CLASS__);
		$query->bind("kpId", $kpId);
		$res = $query->getList();
		$rc = $query->rowCount;
		if ($rc == 0) {
			return null;
		}
		return $res[0];
	}

	public static function getByTorrentIds($torrentIds) {
		global $db2;
		\Logger::debug("get kinopoisk data by torentsIds", ["torrentIds"=>$torrentIds]);
		$sql = "select k.*
				  from rkp_rutor r, rkp_db_link l, rkp_kp_data k
				 where r.torrentId in (:torrentIds)
				   and r.torrentId = l.torrentId
				   and l.kpId = k.kpId";

		$query = $db2->createQuery($sql);
		$query->resultClass(__CLASS__);
		$query->bindList("torrentIds", $torrentIds);
		$res = $query->getList();
		return $res;
	}

    /**
     * @return KinopoiskData
     */
    function update() {
		global $db2;

		$sql = "";
		if ($this->isNew) {
			\Logger::debug("insert kinopoisk data", ["kpId" => $this->kpId]);
			// insert
			$sql = "INSERT INTO rkp_kp_data (kpId, title, originalTitle, year, duration, ratingValue, ratingCount, createDate)
					VALUES (:kpId, :title, :originalTitle, :year, :duration, :ratingValue, :ratingCount, now())";
		} else {
			\Logger::debug("update kinopoisk data", ["kpId" => $this->kpId]);
			// update
			$sql = "update rkp_kp_data
				set
					title = :title,
					originalTitle = :originalTitle,
					year = :year,
					duration = :duration,
					ratingValue = :ratingValue,
					ratingCount = :ratingCount
				where kpId= :kpId";

		}
		$binds = array(
			"kpId" => $this->kpId,
			"title" => $this->title,
			"originalTitle" => $this->originalTitle,
			"year" => $this->year,
			"duration"  =>  $this->duration,
			"ratingValue" => $this->ratingValue,
			"ratingCount" => $this->ratingCount
		);
		$query = $db2->createQuery($sql);
		$query->bind($binds);
		$res = $query->execute();
		$this->isNew = false;

		$kinopoiskData = KinopoiskData::getByKpId($this->kpId);
		return $kinopoiskData;
	}

	/**
	 *
	 * @param int $kpId
	 * @param float $ratingValue
	 * @param int $ratingCount
	 * @throws Exception
	 * @return KinopoiskData
	 */
	public static function updateRating($kpId, $ratingValue, $ratingCount) {
		global $db2;

		$kpId = intval($kpId);

		$_ratingValue = floatval($ratingValue);
		if ($_ratingValue != $ratingValue) {
			throw new Exception("'$ratingValue' is not float value", 500, null);
		}
		$_ratingCount = intval($ratingCount);
		if ($_ratingCount != $ratingCount) {
			throw new Exception("'$ratingCount' is not integer value", 500, null);
		}

		\Logger::debug("update rating", ["kpId" => $kpId, "ratingValue"=>$ratingValue, "ratingCount" => $ratingCount]);

		$sql = "update rkp_kp_data
				set
					ratingValue = :ratingValue,
					ratingCount = :ratingCount,
					createDate = now()
				where kpId= :kpId";
		$binds = array(
			"kpId" => $kpId,
			"ratingValue" => $ratingValue,
			"ratingCount" => $ratingCount
		);
		$query = $db2->createQuery($sql);
		$query->bind($binds);
		$res = $query->execute();

		$kinopoiskData = KinopoiskData::getByKpId($kpId);
		return $kinopoiskData;
	}

	public static function search($binds, $orders, $start=-1, $end=-1) {
		\Logger::debug("search kpData", ["binds" => $binds, "orders" => $orders]);
		global $db2;
		$hasBinds = ($binds != null && count($binds) > 0);
		$hasOrders = ($orders != null && count($orders) > 0);
		$sql = "select *
				from rkp_kp_data ".
				(($hasBinds)? "where " : "").
				"";

		if ($hasBinds) {
			foreach($binds as $key=>$val) {
				$sql .= "($key = :$key ) ";
			}
		}

		if ($hasOrders) {
			$sql .= "order by ";
			foreach($orders as $key=>$direction) {
				$sql .= "$key $direction ";
			}
		}

		$query = $db2->createQuery($sql);
		$query->resultClass(__CLASS__);

		if ($hasBinds) {
			$query->bind($binds);
		}

		$res = $query->getList($start, $end);
		$rc = $query->rowCount;

		if ($rc == 0) {
			return null;
		}

		return $res;
	}

	public function getContent($name) {
		try {
			$path = DOCUMENT_ROOT."/kpData/".$this->kpId;
			$file = "{$path}/{$name}.txt";
			if (!file_exists($path) || !file_exists($file)) {
				//mkdir( $path, 0777, true);
				return null;
			}
			$content = file_get_contents($file);
			return $content;
		} catch (Exception $e){
			//echo "Error on save data";
			return null;
		}
	}

	public function dump(){
		echo "<pre>";
		print_r($this);
		echo "</pre>";
	}
}
?>