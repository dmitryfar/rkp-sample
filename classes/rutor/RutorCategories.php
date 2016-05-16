<?
namespace rutor;

class RutorCategories {
	public $id;
	public $name;
	public $title;
	public $lastDate;

	public static function create($id, $name, $title, $lastDate = null) {
		$obj = new RutorCategories();
		$obj->id = $id;
		$obj->name = $name;
		$obj->title = $title;
		if ($lastDate) {
			$obj->title = $title;
		}
		return $obj;
	}

	/* public static function transform($r) {
		$obj = new RutorCategories();
		foreach($r as $key=>$val) {
			$obj->$key = $val;
		}
		return $obj;
	} */

    /**
     * @param int $id
     * @return RutorCategories
     */
	public static function getById($id) {
		global $db2;
		$sql = "select *
				from rkp_rutor_categories
				where id = :id";
		$query = $db2->createQuery($sql);
		$query->resultClass(__CLASS__);
		$query->bind("id", $id);
		$res = $query->getList();
		$rc = $query->rowCount;
		if ($rc == 0) {
			return null;
		}
		return $res[0];
	}

	public static function getByName($name) {
		global $db2;
		$sql = "select *
				from rkp_rutor_categories
				where name = :name";
		$query = $db2->createQuery($sql);
		$query->resultClass(__CLASS__);
		$query->bind("name", $name);
		$res = $query->getList();
		$rc = $query->rowCount;
		if ($rc == 0) {
			return null;
		}
		return $res[0];
	}

	public static function getByTitle($title) {
		global $db2;
		$sql = "select *
				from rkp_rutor_categories
				where title = :title
				limit 1";
		$query = $db2->createQuery($sql);
		$query->resultClass(__CLASS__);
		$query->bind("title", $title);
		$res = $query->getList();
		$rc = $query->rowCount;
		if ($rc == 0) {
			return null;
		}
		return $res[0];
	}

	public static function getCategories() {
		global $db2;
		$res = array();
		$sql = "select rc.id, rc.name, rc.title, FROM_UNIXTIME(rss.ts) lastDate
				from rkp_rutor_categories as rc left join rkp_rss as rss on rc.id = rss.categoryId
				order by id";

		try {
			//$rc = $db2->SelectRecords($sql, &$categories);
			$query = $db2->createQuery($sql);
			$query->resultClass(__CLASS__);
			$res = $query->getList();
			$rc = $query->rowCount;
		} catch (Exception $e) {
			echo "RutorCategories.getCategories error\n".$e->getMessage()."\n";
			echo "SQL: $sql<br />";
		};
		return $res;
	}

	public function dump(){
		echo "<pre>";
		print_r($this);
		echo "</pre>";
	}
}
?>