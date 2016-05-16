<?
namespace rutor;

class RutorNameParser {
	public $rssTitle;
	public $rutorName;
	public $torrentFile;
	public $rusName;
	public $originalName;
	public $releaser;
	public $properties = array();
	public $propertiesStr;
	public $translates = array();
	public $translatesStr;
	public $year;
	public $quality;
	public $episodes;
	public $isSerie = false;

	function RutorNameParser($name) {
		//echo "$name<br />";
		$this->parse($name);
	}

	function parse($name) {
		global $TRANSLATE_TYPE;

			$name = str_replace(" l ", " | ", $name); // hook contra estupidos

			$c = preg_match_all("/(.*) \(([^\(|\)]*\.torrent)\)$/iU", $name, $a);
			$details = array();
			if ($c > 0) {
				$this->rssTitle = $name;

				$name = $a[1][0];
				$this->rutorName = $name;
				$this->torrentFile = $a[2][0];
			} else {
				$this->rutorName = $name;
			}

			// parse parameters

			$parameters = explode("|", $name);
			$parameters2 = array();
			$name = trim(array_shift($parameters));
			foreach($parameters as $parameter) {
				$parameter = trim($parameter);
				if ($parameter == "") {
					continue;
				}
				if (strpos($parameter, ",") > 0) {
					$parameter = preg_replace('/, /iU', ',', $parameter);
					$parameters2 = array_merge($parameters2, explode(",", $parameter));
				} else {
					$parameters2[] = $parameter;
				}
			}
			$parameters = $parameters2;

			// split translates and other parameters

			$translates = array();
			$props = array();
			foreach($parameters as $t) {
				if (array_key_exists($t, $TRANSLATE_TYPE)) {
					$translates[] = $t;
				} else {
					$props[] = $t;
				}
			}
			$this->properties = $props;
			$this->propertiesStr = implode("|", $props);
			$this->translates = $translates;
			$this->translatesStr = implode("|", $translates);

			// parse releaser

			$checkReleasePattern = "/((.*)\([\s]*\d+(([\s]*-[\s]*|[\s]*–[\s]*)\d+)*[\s]*\)(.*)) (от|by) (.*)$/iU";
			$c = preg_match_all($checkReleasePattern, $name, $checkRelease);
			if ($c > 0) {
				$name = $checkRelease[1][0];

				$this->releaser = $checkRelease[7][0];
			}

			// parse name, year, quality

			$originalName = "";
			if (strpos($name, " / ") > 0) {
				// rus / en (years) quality
				$myRe = "/(.*) \/ (.*)\([\s]*(\d+(([\s]*-[\s]*|[\s]*–[\s]*)\d+)*)[\s]*\)[\s]{0,1}(.*)/i";
				$c = preg_match_all($myRe, $name, $myArray);

				$this->rusName =		$myArray[1][0];
				$this->originalName =	trim($myArray[2][0]);
				$this->year =			$myArray[3][0];
				$this->quality =		$myArray[6][0];

			} else {
				// rus (year) quality
				$myRe = "/(.*) \([\s]*(\d+(([\s]*-[\s]*|[\s]*–[\s]*)\d+)*)[\s]*\)[\s]{0,1}(.*)/i";
				$c = preg_match_all($myRe, $name, $myArray);

				$this->originalName =	trim($myArray[1][0]);
				$this->year =			$myArray[2][0];
				$this->quality =		$myArray[5][0];
			}
			if ($this->rusName != null && $this->originalName == "") {
				$this->originalName = $this->rusName;
				$this->rusName = null;
			}

			// parse episodes

			$this->isSerie = false;
			$checkEpisodesPattern = "/\[(.*\d+.*)\]/i";
			$c = preg_match_all($checkEpisodesPattern, $this->originalName, $res);
			$c2 = preg_match_all($checkEpisodesPattern, $this->rusName, $res2);
			if (($this->rusName == null || $c2 == 0) && $c > 0) {
				$this->episodes = $res[1][0];
				$this->isSerie = true;
			}

			//echo "name: $name, c: $c<br />";
			//echo "<pre>"; print_r($myArray); echo "</pre><br />";
		/*

		*/
	}

	public function dump(){
		echo "<pre>";
		print_r($this);
		echo "</pre>";
	}

}
?>