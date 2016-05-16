<?
namespace rutor;

use parser\BaseHtmlParser;

class RutorPage {
	public $torrentId;
	public $torrentName;

	public $kpMovieId;
	public $kpBanner;
	public $kpBannerUrl;
	public $details;
}

class RutorParser extends BaseHtmlParser{
	public $torrentId;
	public $torrentName;

	public $magnet;
	public $hash;

	public $kpMovieId;
	public $kpBanner;
	public $kpBannerUrl;

    public $imdbMovieId;

	public $details;

	public $error = false;

	public $torrentIds = array();

	public function __construct($torrentId) {
		//echo "torrentId: $torrentId<br />";

		//$url = "http://www.rutor.org/torrent/$torrentId/";
		$url = \Properties::get("rutor.torrent.url", $torrentId);

		parent::__construct($url);

        $this->torrentId = $torrentId;

        //dump($torrentId);
        //dump($this->getInfo());
        //dump($this->htmlText);
        $h1 = $this->html->find('#all h1', 0);
        $this->torrentName = $h1->plaintext;
        if ($this->torrentName == "Раздача не существует!") {
            $error = true;

            $this->clear();
            return null;
        }

        // hash and magnet

        try {
            $this->magnet = $this->html->find('#download a[href^="magnet:"]', 0)->href;
            $c = preg_match_all("/urn:btih:([^&]+)&/iU", $this->magnet, $a);
            if ($c > 0) {
                $this->hash = $a[1][0];
            }
        } catch (Exception $e) {}

        // kinopoisk banner

        $this->kpBannerUrl = $this->getKpBannerUrl();

        //$kpMovieId = preg_replace("/.*rating(\/)+(\d*).gif/", "\\2", $this->kpBannerUrl);
        $kpMovieId = $this->getKpMovieId();
        $kpMovieId = intval ($kpMovieId);
        if ($kpMovieId == 0) {
            $this->error = true;
            return;
        }
        $this->kpMovieId = $kpMovieId;

        $this->imdbMovieId = $this->getImdbMovieId();

        // details
		$details = $this->html->find('#details tr td.header');

		$this->transformDetails();

		$this->torrentIds = array();
		try{
			$links = $this->html->find("#index td a[href*='/torrent/']");
			foreach($links as $link) {
				$c = preg_match_all("/torrent\/([\d]+)\//iU", $link->href, $a);
				if ($c > 0) {
					$torrentId = $a[1][0];
					$this->torrentIds[] = $torrentId;
				}
			}
		} catch(Exception $e) {

		}


		$this->clear();
		unset($this->kpBannerUrl);
		unset($this->html);
		unset($this->htmlText);
	}

    public function getImdbMovieId() {
        try {
            $link = $this->html->find('a[href*="imdb.com\/title"]', 0);
            if ($link){
                $c = preg_match_all("/title\/(tt\d+)/i", $link->href, $a);
                if ($c > 0) {
                    return $a[1][0];
                }
            }
        } catch (Exception $e) {
        }
        return null;
    }

    public function getKpMovieId() {
        try {
            $link1 = $this->html->find('a[href*="kinopoisk.ru\/film"]', 0);
            $link2 = $this->html->find('a[href*="kinopoisk.ru\/level\/1\/film\/"]', 0);
            $link3 = $this->html->find('img[src*="kinopoisk.ru\/rating\/"]', 0);

            if ($link1) {
                $c = preg_match_all("/film\/(\d+)/i", $link1->href, $a);
                return $a[1][0];
            }
            if ($link2) {
                $c = preg_match_all("/film(\/){1,}(\d+)/i", $link2->href, $a);
                return $a[2][0];
            }
            if ($link3) {
                $c = preg_match_all("/rating\/(\d+)/i", $link3->href, $a);
                return $a[1][0];
            }
        } catch (Exception $e) {
        }
        return null;
    }

	public function getKpBannerUrl() {
		//$kpBanner1 = $html->find('a[href*="kinopoisk\.ru\/film"]');
		$this->kpBanner = $this->html->find('img[src*="kinopoisk\.ru(\/)+rating(\/)+(.*)\.gif"]', 0);

		if ($this->kpBanner) {
			//echo "kpBanner: ".$this->kpBanner."<br />";
			return $this->kpBanner->src;
		} else {
			// kp banner not found
		}
		return null;
	}

	private function getCategoryId($categoryLink) {
		if ($categoryLink[0] == "/") {
			$categoryLink = substr($categoryLink, 1);
		}

		/*
		$categories = array();
		$categories["kino"]					= 1;	// Зарубежные фильмы			/kino
		$categories["nashe_kino"]			= 5;	// Наши фильмы					/nashe_kino
		$categories["nauchno_popularnoe"]	= 12;	// Научно-популярные фильмы		/nauchno_popularnoe
		$categories["seriali"]				= 4;	// Сериалы						/seriali
		$categories["tv"]					= 6;	// Телевизор					/tv
		$categories["multiki"]				= 7;	// Мультипликация				/multiki
		$categories["anime"]				= 10;	// Аниме						/anime

		$categoryId = $categories[$categoryLink];
		//echo "categoryLink: $categoryLink, categoryId: $categoryId<br />";
		*/
		$rutorCategory = RutorCategories::getByName($categoryLink);

		return $rutorCategory->id;
	}

	private function transformDetails() {
        $details = $this->html->find('#details tr td.header');

        if (count($details) == 0) {
            $details = $this->html->find('tr td.header');
        }
        $detailsDom = array();
        foreach($details as $item) {
            $detailsDom[$item->plaintext] = $item->parent->find("td",1);
        }
        //dump($detailsDom);
        $this->details = array();
        if (array_key_exists("Залил", $detailsDom)) {
            $uploadUserTd = @$detailsDom["Залил"];
            if (@$uploadUserTd->parent) {
                $this->details["uploadUser"] = $uploadUserTd->plaintext;
                $this->details["uploadUserLink"] = $uploadUserTd->parent->find("a",0)->href;
                //echo $detailsDom["Залил"]->parent->find("a",0)->href;
            }
        }
        if (array_key_exists("Категория", $detailsDom)) {
            $this->details["categoryRu"] = $detailsDom["Категория"]->plaintext;
            $categoryLink = $detailsDom["Категория"]->parent->find("a",0)->href;
            $this->details["categoryLink"] = substr($categoryLink, 1);
            $this->details["categoryId"] = $this->getCategoryId($this->details["categoryLink"]);
        } else {
            echo "Категория not found in torrentId: {$this->torrentId} ({$this->torrentName})<br />";
        }
        $this->details["createDate"] = $detailsDom["Добавлен"]->plaintext;

        try {
            $fileSize = $detailsDom["Размер"]->parent->find("td", 1)->plaintext;
            $this->details["fileSize"] = preg_replace("/.*\((\d+) Bytes\)/", "\\1", $fileSize);
        } catch (Exception $e) {
            echo "размер не найден<br />";
        }
	}

	public function dump(){
		/*
		$array = array();
		$array["torrentId"] = $this->torrentId;
		$array["torrentName"] = $this->torrentName;
		$array["kpMovieId"] = $this->kpMovieId;
		$array["kpBannerUrl"] = $this->kpBannerUrl;
		$array["details"] = $this->details;
		*/
		$rutorPage = new RutorPage();
		$rutorPage->torrentId = $this->torrentId;
		$rutorPage->torrentName = $this->torrentName;
		$rutorPage->kpMovieId = $this->kpMovieId;
		$rutorPage->kpBannerUrl = $this->kpBannerUrl;
		$rutorPage->details = $this->details;

		echo "<pre>";
		print_r($rutorPage);
		echo "</pre>";
	}
}
?>