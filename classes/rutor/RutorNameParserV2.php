<?
namespace rutor;

class RutorNameParserV2 {
	public $rssTitle;
	public $rutorName;
	public $torrentFile;
	private $fullName;
	public $rusName;
	public $originalName;
	private $nameSuffix;
	public $releaser;
	public $properties = array();
	public $propertiesStr;
	public $translates = array();
	public $translatesStr;
	public $year;
	public $quality;
	public $episodes;
	public $isSerie = false;

	function RutorNameParserV2($name) {
		//echo "$name<br />";
		$this->parse($name);
	}

	function parse($name) {
		global $TRANSLATE_TYPE;

        // parse torrent file name

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

        // parse name, year

        //echo "name: $name<br />";
        $matchPattern = "/(((.*) \/ (.*))|(.*)) \([\s]*([\d]+([\s]*[\|\,\-][\s]*[\d]+){0,})[\s]*\)/i";
        $c = preg_match_all($matchPattern, $name, $matches);
        //dump($matches);
        if ($c == 0) {
            throw new RutorNameParserException("Can not parse name: " + $name);
        }
        $this->nameSuffix = str_replace($matches[0][0], "", trim($name));
        $this->fullName = $matches[1][0];
        $this->rusName = $matches[3][0];
        $this->originalName = $matches[4][0];
        $this->year = trim($matches[6][0]);
        $this->year = preg_replace('/[\s]*([\-\,\/\|])[\s]*/i', '\\1', $this->year);


        if (strpos($this->fullName, " / ") > 0) {

        } else {
            $this->originalName = $matches[5][0];
        }

        // parse suffix parameters

            $this->nameSuffix = str_replace(" l ", " | ", $this->nameSuffix); // hook contra estupidos

            // parse parameters

            $parameters = explode("|", $this->nameSuffix);
            $this->nameSuffix = trim(array_shift($parameters));
            $parameters2 = array();
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


        // parse releaser, quality

        $checkReleasePattern = "/(.*) (от|by) (.*)/i";
        $c = preg_match_all($checkReleasePattern, $this->nameSuffix, $checkRelease);
        //$checkRelease = checkReleasePattern.exec(this.nameSuffix);
        if ($c > 0) {
            //dump($checkRelease);
            $this->nameSuffix = $checkRelease[1][0];

            $this->releaser = trim($checkRelease[3][0]);
        }

        $this->quality = trim($this->nameSuffix);

        /*
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
		*/
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