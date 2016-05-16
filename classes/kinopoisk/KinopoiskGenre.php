<?
namespace kinopoisk;

class KinopoiskGenre {
	public $id;
	public $name;

	public static function getById($id) {
		global $db2;
		$sql = "select *
				from rkp_kp_genre
				where id=:id";

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
				from rkp_kp_genre
				where name=:name";

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

	public static function getByNames($names) {
		global $db2;
		$sql = "select *
				from rkp_kp_genre
				where name in (:names)";

		$query = $db2->createQuery($sql);
		$query->resultClass(__CLASS__);
		$query->bindList("names", $names);
		$res = $query->getList();
		$rc = $query->rowCount;
		return $res;
	}
}
?>