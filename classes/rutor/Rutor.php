<?
namespace rutor;

class Rutor {
	public $torrentId;
	public $torrentName;
	public $hash;
	public $title;
	public $originalTitle;
	public $releaser;
	public $properties;
	public $translates;
	public $translatesList = array();
	public $year;
	public $quality;
	public $episodes;

	public $categoryId;
	public $fileSize;

	public $createDate;
	public $modifiedDate;

	private $isNew = false;

	function __construct() {
		$this->addData();
	}
	static function create($torrentId, $hash, $torrentName, $title, $originalTitle, $releaser, $properties,
				$translates, $year, $quality, $episodes, $categoryId, $fileSize) {
		$obj = new Rutor();
		$obj->torrentId =      $torrentId;
		$obj->hash =           $hash;
		$obj->torrentName =    $torrentName;
		$obj->title =          $title;
		$obj->originalTitle =  $originalTitle;
		$obj->releaser =       $releaser;
		$obj->properties =     $properties;
		$obj->translates =     $translates;
		$obj->year =           $year;
		$obj->quality =        $quality;
		$obj->episodes =       $episodes;

		$obj->categoryId =     $categoryId;
		$obj->fileSize =       $fileSize;

		$obj->isNew = true;

		$obj = $obj->update();
		return $obj;
	}


	/* public static function transform(&$r) {
		global $TRANSLATE_TYPE;

		$obj = new Rutor();
		foreach($r as $key=>$val) {
			$obj->$key = $val;
		}
			$translates = $obj->translates;
			$parameters = explode("|", $translates);
			foreach($parameters as $parameter) {
				if (array_key_exists($parameter, $TRANSLATE_TYPE)) {
					$obj->translatesList[] = $TRANSLATE_TYPE[$parameter];
				}
			}

		return $obj;
	} */

	function addData() {
		global $TRANSLATE_TYPE;

		$translates = $this->translates;
		$parameters = explode("|", $translates);
		foreach($parameters as $parameter) {
			if (array_key_exists($parameter, $TRANSLATE_TYPE)) {
				$this->translatesList[] = $TRANSLATE_TYPE[$parameter];
			}
		}
	}

    /**
     * @param $torrentId
     * @return Rutor
     */
	public static function getByTorrentId($torrentId) {
		$db2 = \DataBase2::getInstance();
		\Logger::debug("get Rutor by torrentId=$torrentId");
		$sql = "select *
				from rkp_rutor
				where torrentId=:torrentId";

		$query = $db2->createQuery($sql);
		$query->resultClass(__CLASS__);
		$query->bind("torrentId", $torrentId);
		$res = $query->getList();
		$rc = $query->rowCount;
		if ($rc == 0) {
			return null;
		}
		return $res[0];
	}

	public static function delete($torrentId) {
		$db2 = \DataBase2::getInstance();
		$sql = "delete from rkp_rutor
				where torrentId = :torrentId";
		$binds = array("torrentId" => $torrentId);
		$rc=$db2->parametrizedQuery($sql, $binds, $res);
	}

	function update() {
		$db2 = \DataBase2::getInstance();

		if ($this->isNew) {
			\Logger::debug("insert new Rutor $this->torrentId");
			// insert
			$sql = "INSERT INTO rkp_rutor (torrentId, hash, torrentName, title, originalTitle,
						releaser, properties, translates,
						year, quality, episodes,
						categoryId, fileSize, createDate, modifiedDate)
					VALUES (:torrentId, :hash, :torrentName, :title, :originalTitle,
						:releaser, :properties, :translates,
						:year, :quality, :episodes,
						:categoryId, :fileSize, now(), now())";
		} else {
			\Logger::debug("update Rutor $this->torrentId");
			// update
			$sql = "update rkp_rutor
				set
					hash = :hash,
					torrentName = :torrentName,
					title = :title,
					originalTitle = :originalTitle,
					releaser = :releaser,
					properties = :properties,
					translates = :translates,
					year = :year,
					quality = :quality,
					episodes = :episodes,

					categoryId = :categoryId,
					fileSize = :fileSize,
					modifiedDate = now()
				where torrentId = :torrentId";
		}
		$binds = array(
			"torrentId" => $this->torrentId,
			"hash" => $this->hash,
			"torrentName" => $this->torrentName,
			"title" => $this->title,
			"originalTitle" => $this->originalTitle,
			"releaser" => $this->releaser,
			"properties" => $this->properties,
			"translates" => $this->translates,
			"year" => $this->year,
			"quality"  =>  $this->quality,
			"episodes" => $this->episodes,
			"categoryId" => $this->categoryId,
			"fileSize" => $this->fileSize
		);
		$query = $db2->createQuery($sql);
		$query->bind($binds);
		$res = $query->execute();
		$this->isNew = false;

		$rutor = Rutor::getByTorrentId($this->torrentId);
		return $rutor;
	}

	function updateCreateDate($createDate) {
		$db2 = \DataBase2::getInstance();
		\Logger::debug("update createDate for torrent $this->torrentId");
			// update
			$sql = "update rkp_rutor
				set
					createDate = :createDate
				where torrentId = :torrentId";
		$binds = array(
				"torrentId" => $this->torrentId,
				"createDate" => $createDate
		);
		$query = $db2->createQuery($sql);
		$query->bind($binds);
		$res = $query->execute();
		$this->isNew = false;

		$rutor = Rutor::getByTorrentId($this->torrentId);
		return $rutor;
	}

    /**
     * @param int $kpId
     * @return Rutor[]
     */
	public static function searchByKpId($kpId, $orders=array()) {
		$db2 = \DataBase2::getInstance();
        $hasOrders = ($orders != null && count($orders) > 0);
		$sql = "select r.*
				from rkp_rutor r, rkp_db_link l
				where r.torrentId = l.torrentId
				and l.kpId = :kpId";

        if ($hasOrders) {
            $sql .= " order by ";
            foreach($orders as $key=>$direction) {
                $sql .= "$key $direction ";
            }
        }

		$query = $db2->createQuery($sql);
		$query->resultClass(__CLASS__);
		$query->bind("kpId", $kpId);
		$res = $query->getList();
		$rc = $query->rowCount;

		return $res;
	}

	function getLastByCategory($categoryId = null) {
		$db2 = \DataBase2::getInstance();
		$sql = "select r.*
				from rkp_rutor r ".
				(($categoryId) ? "where r.categoryId = :categoryId " : "").
				"order by createDate desc
				limit 1";
		$binds = null;
		if ($categoryId) {
			$binds = array("categoryId" => $categoryId);
		}
		$rc = $db2->parametrizedQueryClass($sql, $binds, __CLASS__, $res);
		return $res;
	}

	function countByCategory($categoryId = null) {
		$db2 = \DataBase2::getInstance();
		$sql = "select count(*) as cnt
				from rkp_rutor r ".
				(($categoryId) ? "where categoryId = :categoryId ": "").
				"";
		$binds = null;
		if ($categoryId) {
			$binds = array("categoryId" => $categoryId);
		}
		$count = $db2->getParametrizedCount($sql, $binds);
		return $count;
	}

	public function dump(){
		echo "<pre>";
		print_r($this);
		echo "</pre>";
	}
}
?>