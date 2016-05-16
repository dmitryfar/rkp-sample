<?
namespace rutor;

class RutorNameParser {
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

	function RutorNameParser($name) {
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

        $matchPattern = "/(((.*) \/ (.*))|(.*)) \([\s]*([\d]+([\s]*[\|\,\-][\s]*[\d]+){0,})[\s]*\)/i";
        $c = preg_match_all($matchPattern, $name, $matches);
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

            $parameters = explode("|", $this->nameSuffix);
            $this->nameSuffix = trim(array_shift($parameters));
            $parameters2 = array();
            foreach($parameters as $parameter) {
                $parameter = trim($parameter);
                if ($parameter == "") {
                    continue;
                }
                $parameter = preg_replace('/\+/i', ',', $parameter);
                if (strpos($parameter, ",") > 0) {
                    $parameter = preg_replace('/[\s]*,[\s]*/i', ',', $parameter);
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
        if ($c > 0) {
            //dump($checkRelease);
            $this->nameSuffix = $checkRelease[1][0];

            $this->releaser = trim($checkRelease[3][0]);
        }

        $this->quality = trim($this->nameSuffix);

		// parse episodes

        $this->isSerie = false;
        $checkEpisodesPattern = "/\[(.*\d+.*)\]/i";
        $c = preg_match_all($checkEpisodesPattern, $this->originalName, $res);
        $c2 = preg_match_all($checkEpisodesPattern, $this->rusName, $res2);
        if (($this->rusName == null || $c2 == 0) && $c > 0) {
            $this->episodes = $res[1][0];
            $this->isSerie = true;
        }
	}

	public function dump(){
		echo "<pre>";
		print_r($this);
		echo "</pre>";
	}

}
?>