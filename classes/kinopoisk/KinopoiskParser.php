<?
namespace kinopoisk;

use parser\BaseHtmlParser;
use classes\Properties;

class KinopoiskPage {
	public $movieId;
	public $title;
	public $originalTitle;
	public $year;
	public $duration;
	public $enInfo;

	public $kinopoiskGenres;
	public $description;

	public $rating = array();
}
class KinopoiskParser extends BaseHtmlParser{
	public $movieId;
	public $title;
	public $originalTitle;
	public $year;
	public $duration;
	public $enInfo;

	public $kinopoiskGenres;
	public $countries;
	public $description;

	public $rating = array();

	function KinopoiskParser($movieId) {
		if (!$movieId){
			return;
		}
		echo "movieId: $movieId<br />\n";

		//$url = "http://m.kinopoisk.ru/movie/$movieId/";
		$url = Properties::get("kinopoisk.movie.url", $movieId);

		parent::__construct($url, "cp1251");

		$this->movieId = $movieId;

		/*
		echo ">>cnt: ".count($this->html->find('#content div[class="block film"] '))."<br />";
		echo ">>".$this->html->find('#content div[class="block film"] p', 0)->innertext."<br />\n\n";
		$t = $this->html->find('#content div[class="block film"] p', 0)->innertext;
		$c = preg_match_all("/<b>(.*)<\/b>(.*)<br>/U", $t, $a);
		$details = array();
		if ($c > 0) {
			for($i=0; $i<$c; $i++) {
				$key = $a[1][$i];
				$val = $a[2][$i];
				$details[$key] = $val;
			}
		}
		echo "<pre>"; print_r($details); echo "</pre>";
		*/

		/*
		$t = $this->html->find('#content div[class="block film"] span', 4)->innertext;
		$c = preg_match_all("/<b>(.*)<\/b>(.*)(<br \/>|<br\/>|<br>)*   /U", $t, $a);
		$details = array();
		if ($c > 0) {
			for($i=0; $i<$c; $i++) {
				$key = $a[1][$i];
				$val = $a[2][$i];
				$details[$key] = $val;
			}
		}
		echo "<pre>"; print_r($details); echo "</pre>";
		*/

		// parse details on page
			// жанр
			try {
				$t = $this->html->find('#content div[class="block film"] span', 0)->innertext;
				$genres = KinopoiskParser::parseDetailsList($t);
				$this->kinopoiskGenres = KinopoiskGenre::getByNames($genres);
                KinopoiskParser::saveContent($this->movieId, "genre", $t);
			// echo "genres: "; dump($genres); dump($this->kinopoiskGenres);
			} catch (\Exception $e) {}

			// страны
			try {
				$t = $this->html->find('#content div[class="block film"] span', 1)->innertext;
				$this->countries = KinopoiskParser::parseDetailsList($t);
                KinopoiskParser::saveContent($this->movieId, "country", $t);
			} catch (\Exception $e) {}

			// премьера
			try {
				$t = $this->html->find('#content div[class="block film"] p', 0)->innertext;
				$premiere = KinopoiskParser::parseDetailsArray($t);
				// echo "premiere: "; dump($premiere);
			} catch (\Exception $e) {}

			// рейтинг
			try {
				//$t = $this->html->find('#content div[class="block film"] span', 4)->innertext;
				//$a = KinopoiskParser::parseDetailsArray($t);
				//dump($a);
			} catch (\Exception $e) {}

			// description
			try {
				$this->description = $this->html->find('#content div[class="block film"] p.descr', 0)->innertext;
				//echo "{$this->description}\n\n\n";
                KinopoiskParser::saveContent($this->movieId, "description", $this->description);
			} catch (\Exception $e) {}
		//

		$this->title = $this->html->find("#content p.title b", 0)->innertext;
		$this->enInfo = $this->html->find("#content p.title span", 0)->plaintext;

		$c = preg_match_all("/((.*), ){0,1}([\d]{4})(, ){0,1}([^,]*)$/U", $this->enInfo, $a);
		if ($c > 0) {
			$this->originalTitle = $a[2][0];
			$this->year = $a[3][0];
			$this->duration = $a[5][0];
			//echo "<pre>"; print_r($a); echo "</pre>";
			//dump($a);
		} else {
			echo "parse error enInfo: #{$this->enInfo}#<br />";
		}

		//$this->parseKpRating();
		$rating = KinopoiskParser::parseKpRating($this->movieId);
		if ($rating) {
			$this->rating = $rating;
		}


		////
		//mkdir( $path );
		//chmod( $path, 777 );

		$this->html->clear();
		unset($this->html);
	}

	public static function saveContent($movieId, $name, $content) {
		try {
		$path = DOCUMENT_ROOT."/kpData/".$movieId;
		if (!file_exists($path)) {
			mkdir( $path, 0777, true);
		}
		$file = "{$path}/{$name}.txt";
		file_put_contents($file, $content);
		} catch (\Exception $e){
			//echo "Error on save data";
		}
	}

	public function getContent($name) {
		try {
			$path = DOCUMENT_ROOT."/kpData/".$this->movieId;
			if (!file_exists($path)) {
				//mkdir( $path, 0777, true);
				return null;
			}
			$file = "{$path}/{$name}.txt";
			$content = file_get_contents($file);
			return $content;
		} catch (\Exception $e){
			//echo "Error on save data";
			return null;
		}
	}


	public static function parseKpRating($kpId) {
		//echo "get rating by kpId=$kpId\n";
		//$url = "http://rating.kinopoisk.ru/".$this->movieId.".xml";
		$url = Properties::get("kinopoisk.rating.url", $kpId);
		$xmlRating = simplexml_load_file($url);

		$rating = array(
				"kpRatingValue" => 0,
				"kpRatingCount" => 0,
				"imdbRatingValue" => 0,
				"imdbRatingCount" => 0
		);
		try {
			if (!$xmlRating) {
				return $xmlRating;
			}

			$rating["kpRatingValue"] = (float)$xmlRating->kp_rating;
			$rating["imdbRatingValue"] = (float)$xmlRating->imdb_rating;

			if ($xmlRating->kp_rating) {
				$rating["kpRatingCount"] =  (int)$xmlRating->kp_rating->attributes()->num_vote;
			}
			if ($xmlRating->imdb_rating) {
				$rating["imdbRatingCount"] =  (int)$xmlRating->imdb_rating->attributes()->num_vote;
			}
		} catch (\Exception $e) {
			echo "get rating error\n".$e->getMessage()."\n";
		}
		return $rating;
	}

	public static function getDetailsRows($str) {
		$rows = preg_split("/(<br \/>|<br\/>|<br>)/iU", $str);
		$rows = array_map('trim', $rows);
		$rows = array_map('strip_tags', $rows);
		$rows = array_filter($rows);
		return $rows;
	}

	public static function parseDetailsArray($str) {
		$rows = KinopoiskParser::getDetailsRows($str);
		$array = array();
		if (count($rows) > 0) {
			foreach ($rows as $key => $row) {
				$pairs = preg_split("/\:/iU", $row);
				//$pairs = array_map('htmlspecialchars_decode', $pairs);
				$pairs = array_map('nbspReplace', $pairs);
				$pairs = array_map('trim', $pairs);
				$key = $pairs[0];
				$val = $pairs[1];
				$array[$key] = $val;
			}
		}
		return $array;
	}

	public static function parseDetailsList($str, $delimiter = ",") {
		$rows = KinopoiskParser::getDetailsRows($str);
		$list = array();
		if (count($rows) > 0) {
			foreach ($rows as $key => $row) {
				$elements = explode($delimiter, $row);
// 				$elements = preg_split("/\,/iU", $row);
				//$pairs = array_map('htmlspecialchars_decode', $pairs);
				$elements = array_map('nbspReplace', $elements);
				$elements = array_map('trim', $elements);
				$list = array_merge($list, $elements);
			}
		}
		return $list;
	}

	public function dump(){
		$array = array();
		$array["movieId"] = $this->movieId;
		$array["title"] = $this->title;
		$array["originalTitle"] = $this->originalTitle;
		$array["year"] = $this->year;
		$array["duration"] = $this->duration;
		$array["enInfo"] = $this->enInfo;

		$kinopoiskPage = new KinopoiskPage();
		$kinopoiskPage->movieId = $this->movieId;
		$kinopoiskPage->title = $this->title;
		$kinopoiskPage->originalTitle = $this->originalTitle;
		$kinopoiskPage->year = $this->year;
		$kinopoiskPage->duration = $this->duration;
		$kinopoiskPage->enInfo = $this->enInfo;
		$kinopoiskPage->rating = $this->rating;

		$kinopoiskPage->kinopoiskGenres = $this->kinopoiskGenres;
		$kinopoiskPage->description = $this->description;

		echo "<pre>";
		print_r($kinopoiskPage);
		echo "</pre>\n";
	}
}
?>